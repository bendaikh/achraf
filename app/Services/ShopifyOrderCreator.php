<?php

namespace App\Services;

use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Product;
use App\Models\ShopifyIntegration;
use Illuminate\Support\Facades\DB;

class ShopifyOrderCreator
{
    public function sync(PosSale $order, ?int $actorUserId = null): PosSale
    {
        $claimed = DB::transaction(function () use ($order) {
            $locked = PosSale::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->shopify_order_id
                || ($locked->source === 'shopify' && $locked->external_id)) {
                return false;
            }

            if (! $locked->creation_token) {
                throw new \RuntimeException('Seules les commandes créées dans Libromart peuvent être envoyées vers Shopify.');
            }

            if ($locked->sync_status === PosSale::SYNC_IN_PROGRESS
                && $locked->sync_attempted_at?->isAfter(now()->subMinutes(5))) {
                return false;
            }

            $locked->update([
                'sync_status' => PosSale::SYNC_IN_PROGRESS,
                'sync_error' => null,
                'sync_attempted_at' => now(),
            ]);

            return true;
        });

        if (! $claimed) {
            return $order->fresh();
        }

        try {
            $integration = ShopifyIntegration::query()->first();
            if (! $integration || ! $integration->enabled
                || ! ($integration->oauth_access_token || $integration->api_access_token)) {
                throw new \RuntimeException('L’intégration Shopify n’est pas configurée ou activée.');
            }

            $order->loadMissing(['client', 'items.variant', 'items.product.variants', 'creator', 'assignedUser']);
            $client = new ShopifyApiClient($integration);

            // Recover safely after an ambiguous timeout: the remote order may
            // exist even though the previous request never returned locally.
            $shopifyOrder = $client->findOrderByNoteAttribute(
                'libromart_creation_token',
                (string) $order->creation_token
            );

            if (! $shopifyOrder) {
                $shopifyOrder = $client->createOrder($this->payload($order));
            }

            $this->linkOrder($order, $shopifyOrder, $actorUserId);

            return $order->fresh();
        } catch (\Throwable $e) {
            $order->forceFill([
                'sync_status' => PosSale::SYNC_ERROR,
                'sync_error' => mb_substr($e->getMessage(), 0, 4000),
                'sync_attempted_at' => now(),
            ])->save();

            $order->recordActivity(
                'shopify_sync_failed',
                'Échec de la synchronisation Shopify',
                $actorUserId,
                ['error' => mb_substr($e->getMessage(), 0, 1000)]
            );

            throw $e;
        }
    }

    /**
     * Build the Shopify Admin API order payload for a Libromart-created order.
     *
     * @return array<string, mixed>
     */
    public function payload(PosSale $order): array
    {
        $lineItems = $order->items->map(function (PosSaleItem $item) {
            $taxRate = (float) $item->tax_rate;
            $lineTotalTtc = (float) $item->line_total;
            // Libromart line_total is TTC; Shopify line price must be HT when taxes are exclusive.
            $lineTotalHt = $taxRate > 0
                ? $lineTotalTtc / (1 + ($taxRate / 100))
                : $lineTotalTtc;
            $unitPriceHt = $item->quantity > 0
                ? $lineTotalHt / $item->quantity
                : 0.0;

            $line = [
                'quantity' => $item->quantity,
                'price' => number_format($unitPriceHt, 2, '.', ''),
                'title' => $item->designation,
                'sku' => $item->ref,
                'taxable' => $taxRate > 0,
            ];

            $shopifyVariantId = $this->resolveShopifyVariantId($item);
            if ($shopifyVariantId) {
                // Keep the real Shopify product/variant link so the order shows the catalog photo.
                $line['variant_id'] = (int) $shopifyVariantId;
            }

            if ($taxRate > 0) {
                $line['tax_lines'] = [[
                    'price' => number_format($lineTotalTtc - $lineTotalHt, 2, '.', ''),
                    'rate' => $taxRate / 100,
                    'title' => 'TVA',
                ]];
            }

            return $line;
        })->all();

        $noteAttributes = [
            ['name' => 'libromart_order_id', 'value' => (string) $order->id],
            ['name' => 'libromart_order_number', 'value' => (string) $order->ticket_number],
            ['name' => 'libromart_creation_token', 'value' => (string) $order->creation_token],
            ['name' => 'libromart_created_by_user_id', 'value' => (string) $order->created_by_user_id],
            ['name' => 'libromart_assigned_user_id', 'value' => (string) $order->assigned_user_id],
            ['name' => 'Créée depuis Libromart par', 'value' => (string) ($order->creator?->name ?: '—')],
            ['name' => 'Commercial', 'value' => (string) ($order->assignedUser?->name ?: '—')],
            ['name' => 'N° commande Libromart', 'value' => (string) $order->ticket_number],
        ];

        $payload = [
            'line_items' => $lineItems,
            'taxes_included' => false,
            'currency' => $this->currencyCode($order->currency),
            'financial_status' => $this->financialStatus($order->payment_status),
            'send_receipt' => false,
            'send_fulfillment_receipt' => false,
            'inventory_behaviour' => 'decrement_obeying_policy',
            'tags' => implode(', ', array_filter(array_merge(
                $order->tags ?? [],
                ['Libromart', 'Libromart-'.$order->ticket_number]
            ))),
            'note' => $order->delivery_note ?: $order->internal_note,
            'note_attributes' => $noteAttributes,
        ];

        if ($order->client) {
            $payload['email'] = $order->client->email;
            $payload['phone'] = $order->client->phone;
            $payload['shipping_address'] = array_filter([
                'first_name' => $order->client->first_name ?: $order->client->name,
                'last_name' => $order->client->last_name,
                'address1' => $order->shipping_address ?: $order->client->address,
                'city' => $order->shipping_city ?: $order->client->city,
                'zip' => $order->shipping_postal_code ?: $order->client->postal_code,
                'country' => $order->shipping_country ?: $order->client->country,
                'phone' => $order->client->phone,
            ], fn ($value) => filled($value));
        }

        if ((float) $order->shipping_amount > 0) {
            $payload['shipping_lines'] = [[
                'title' => $order->shipping_method ?: 'Livraison',
                'price' => number_format((float) $order->shipping_amount, 2, '.', ''),
            ]];
        }

        if ((float) $order->discount > 0) {
            $payload['discount_codes'] = [[
                'code' => $order->discount_reason ?: 'REMISE-LIBROMART',
                'amount' => number_format((float) $order->discount, 2, '.', ''),
                'type' => 'fixed_amount',
            ]];
        }

        return array_filter($payload, fn ($value) => $value !== null && $value !== '');
    }

    private function resolveShopifyVariantId(PosSaleItem $item): ?string
    {
        if (filled($item->shopify_variant_id)) {
            return (string) $item->shopify_variant_id;
        }

        if (filled($item->variant?->shopify_variant_id)) {
            return (string) $item->variant->shopify_variant_id;
        }

        $product = $item->relationLoaded('product')
            ? $item->product
            : ($item->product_id ? Product::query()->with('variants')->find($item->product_id) : null);

        if (! $product) {
            return null;
        }

        $variants = $product->relationLoaded('variants')
            ? $product->variants
            : $product->variants()->get();

        if ($item->ref) {
            $bySku = $variants->first(
                fn ($variant) => filled($variant->shopify_variant_id)
                    && strcasecmp((string) $variant->sku, (string) $item->ref) === 0
            );
            if ($bySku) {
                return (string) $bySku->shopify_variant_id;
            }
        }

        $withShopifyId = $variants->filter(fn ($variant) => filled($variant->shopify_variant_id));
        if ($withShopifyId->count() === 1) {
            return (string) $withShopifyId->first()->shopify_variant_id;
        }

        return null;
    }

    private function linkOrder(PosSale $order, array $shopifyOrder, ?int $actorUserId): void
    {
        $shopifyId = (string) ($shopifyOrder['id'] ?? '');
        if ($shopifyId === '') {
            throw new \RuntimeException('Shopify n’a retourné aucun identifiant de commande.');
        }

        $number = ltrim((string) ($shopifyOrder['name'] ?? $shopifyOrder['order_number'] ?? ''), '#');

        $order->forceFill([
            'shopify_order_id' => $shopifyId,
            'shopify_order_number' => $number ?: null,
            'external_id' => $shopifyId,
            'sync_status' => PosSale::SYNC_SYNCED,
            'sync_error' => null,
            'shopify_synced_at' => now(),
            'sync_attempted_at' => now(),
        ])->save();

        $order->recordActivity(
            'shopify_synced',
            'Commande synchronisée avec Shopify'.($number ? ' ('.$number.')' : ''),
            $actorUserId,
            ['shopify_order_id' => $shopifyId, 'shopify_order_number' => $number]
        );
    }

    private function currencyCode(?string $currency): string
    {
        preg_match('/\b[A-Z]{3}\b/', strtoupper((string) $currency), $matches);

        return $matches[0] ?? 'MAD';
    }

    private function financialStatus(?string $status): string
    {
        return match ($status) {
            'paid' => 'paid',
            'partially_paid' => 'partially_paid',
            'refunded' => 'refunded',
            default => 'pending',
        };
    }
}
