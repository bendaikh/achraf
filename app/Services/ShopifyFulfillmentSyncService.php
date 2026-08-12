<?php

namespace App\Services;

use App\Models\OrderFulfillment;
use App\Models\OrderTracking;
use App\Models\PosSale;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ShopifyFulfillmentSyncService
{
    /**
     * Sync fulfillments embedded in a Shopify order payload.
     */
    public function syncFromOrderPayload(PosSale $sale, array $order): void
    {
        $shopifyOrderId = (string) ($order['id'] ?? $sale->external_id ?? '');
        $fulfillments = $order['fulfillments'] ?? [];

        if (! is_array($fulfillments)) {
            return;
        }

        $seenIds = [];

        foreach ($fulfillments as $fulfillment) {
            if (! is_array($fulfillment)) {
                continue;
            }

            $synced = $this->upsertFulfillment($sale, $fulfillment, $shopifyOrderId);
            if ($synced?->shopify_fulfillment_id) {
                $seenIds[] = $synced->shopify_fulfillment_id;
            }
        }

        // Also pick up tracking from shipping_lines / fulfillments_orders style fields if present
        $this->syncLegacyTrackingFields($sale, $order, $shopifyOrderId, $seenIds);

        if ($seenIds !== []) {
            OrderFulfillment::query()
                ->where('pos_sale_id', $sale->id)
                ->whereNotNull('shopify_fulfillment_id')
                ->whereNotIn('shopify_fulfillment_id', $seenIds)
                ->delete();
        }
    }

    /**
     * Handle fulfillments/create|update webhook payload.
     */
    public function syncFromFulfillmentWebhook(array $fulfillment): ?OrderFulfillment
    {
        $orderId = (string) ($fulfillment['order_id'] ?? '');
        if ($orderId === '') {
            Log::warning('Shopify fulfillment webhook missing order_id');

            return null;
        }

        $sale = PosSale::query()
            ->where('source', 'shopify')
            ->where('external_id', $orderId)
            ->first();

        if (! $sale) {
            Log::info('Shopify fulfillment for unknown order', ['order_id' => $orderId]);

            return null;
        }

        $record = $this->upsertFulfillment($sale, $fulfillment, $orderId);

        $status = strtolower((string) ($fulfillment['status'] ?? $sale->fulfillment_status ?? 'fulfilled'));
        if ($status !== '' && $sale->fulfillment_status !== $status) {
            $sale->update([
                'fulfillment_status' => $status === 'success' ? 'fulfilled' : $status,
                'shopify_synced_at' => now(),
            ]);
        }

        return $record;
    }

    public function upsertFulfillment(PosSale $sale, array $fulfillment, ?string $shopifyOrderId = null): ?OrderFulfillment
    {
        $fulfillmentId = isset($fulfillment['id']) ? (string) $fulfillment['id'] : null;

        $trackingNumbers = $this->extractTrackingNumbers($fulfillment);
        $company = $this->firstNonEmpty([
            $fulfillment['tracking_company'] ?? null,
            $fulfillment['tracking_company_code'] ?? null,
        ]);
        $url = $this->firstNonEmpty([
            $fulfillment['tracking_url'] ?? null,
            is_array($fulfillment['tracking_urls'] ?? null) ? ($fulfillment['tracking_urls'][0] ?? null) : null,
        ]);
        $status = isset($fulfillment['status']) ? strtolower((string) $fulfillment['status']) : null;

        // One fulfillment can carry multiple tracking numbers — store one row per tracking when possible
        if ($trackingNumbers === [] && $fulfillmentId) {
            $trackingNumbers = [null];
        }

        if ($trackingNumbers === []) {
            return null;
        }

        $first = null;

        foreach ($trackingNumbers as $index => $trackingNumber) {
            $keyFulfillmentId = $fulfillmentId;
            if ($fulfillmentId && count($trackingNumbers) > 1) {
                $keyFulfillmentId = $fulfillmentId.'#'.$index;
            }

            $attributes = [
                'pos_sale_id' => $sale->id,
                'shopify_order_id' => $shopifyOrderId ?: (string) ($fulfillment['order_id'] ?? $sale->external_id),
                'shopify_fulfillment_id' => $keyFulfillmentId,
                'tracking_number' => $trackingNumber,
                'tracking_company' => $company,
                'tracking_url' => $url,
                'status' => $status,
                'shopify_created_at' => $this->parseDate($fulfillment['created_at'] ?? null),
                'shopify_updated_at' => $this->parseDate($fulfillment['updated_at'] ?? null),
                'raw_payload' => $fulfillment,
            ];

            if ($keyFulfillmentId) {
                $record = OrderFulfillment::query()->updateOrCreate(
                    [
                        'pos_sale_id' => $sale->id,
                        'shopify_fulfillment_id' => $keyFulfillmentId,
                    ],
                    $attributes
                );
            } elseif ($trackingNumber) {
                $record = OrderFulfillment::query()->updateOrCreate(
                    [
                        'pos_sale_id' => $sale->id,
                        'tracking_number' => $trackingNumber,
                    ],
                    $attributes
                );
            } else {
                continue;
            }

            $first ??= $record;
        }

        // Keep legacy order_trackings in sync for older payment links
        if ($first && $first->tracking_number) {
            OrderTracking::query()->updateOrCreate(
                [
                    'pos_sale_id' => $sale->id,
                    'shopify_fulfillment_id' => $fulfillmentId ?: ('legacy-'.$sale->external_id),
                ],
                [
                    'tracking_number' => $first->tracking_number,
                    'carrier' => $company,
                    'status' => $status,
                    'shopify_created_at' => $this->parseDate($fulfillment['created_at'] ?? null),
                    'shopify_updated_at' => $this->parseDate($fulfillment['updated_at'] ?? null),
                ]
            );
        }

        return $first;
    }

    protected function syncLegacyTrackingFields(PosSale $sale, array $order, string $shopifyOrderId, array &$seenIds): void
    {
        // Some payloads expose top-level tracking on fulfillment status without full fulfillments array
        $fulfillments = $order['fulfillments'] ?? [];
        if (is_array($fulfillments) && count($fulfillments) > 0) {
            return;
        }

        $legacyTracking = $this->firstNonEmpty([
            $order['tracking_number'] ?? null,
            data_get($order, 'fulfillment.tracking_number'),
        ]);

        if (! $legacyTracking) {
            return;
        }

        $record = OrderFulfillment::query()->updateOrCreate(
            [
                'pos_sale_id' => $sale->id,
                'shopify_fulfillment_id' => 'legacy-'.$shopifyOrderId,
            ],
            [
                'shopify_order_id' => $shopifyOrderId,
                'tracking_number' => (string) $legacyTracking,
                'tracking_company' => $this->firstNonEmpty([
                    $order['tracking_company'] ?? null,
                    data_get($order, 'fulfillment.tracking_company'),
                ]),
                'tracking_url' => $this->firstNonEmpty([
                    $order['tracking_url'] ?? null,
                    data_get($order, 'fulfillment.tracking_url'),
                ]),
                'status' => strtolower((string) ($order['fulfillment_status'] ?? 'fulfilled')),
                'raw_payload' => ['legacy' => true, 'order_id' => $shopifyOrderId],
            ]
        );

        if ($record->shopify_fulfillment_id) {
            $seenIds[] = $record->shopify_fulfillment_id;
        }
    }

    /**
     * @return list<string|null>
     */
    protected function extractTrackingNumbers(array $fulfillment): array
    {
        $numbers = [];

        if (! empty($fulfillment['tracking_number'])) {
            $numbers[] = trim((string) $fulfillment['tracking_number']);
        }

        if (! empty($fulfillment['tracking_numbers']) && is_array($fulfillment['tracking_numbers'])) {
            foreach ($fulfillment['tracking_numbers'] as $n) {
                $n = trim((string) $n);
                if ($n !== '') {
                    $numbers[] = $n;
                }
            }
        }

        return array_values(array_unique(array_filter($numbers, fn ($n) => $n !== null && $n !== '')));
    }

    protected function parseDate(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function firstNonEmpty(array $values): ?string
    {
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
