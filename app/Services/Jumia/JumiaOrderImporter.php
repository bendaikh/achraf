<?php

namespace App\Services\Jumia;

use App\Models\Client;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Product;
use App\Services\OrderToInvoiceConverter;
use App\Support\OrderSource;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class JumiaOrderImporter
{
    public function __construct(
        protected JumiaApiClient $client,
        protected JumiaStatusMapper $statusMapper,
        protected OrderToInvoiceConverter $orderToInvoiceConverter
    ) {}

    /**
     * @param  array<string, mixed>  $order
     */
    public function import(array $order): PosSale
    {
        return DB::transaction(function () use ($order) {
            $externalId = (string) ($order['OrderId'] ?? $order['OrderNumber'] ?? '');
            if ($externalId === '') {
                throw new \InvalidArgumentException('Missing Jumia order id.');
            }

            $orderItems = $this->client->getOrderItems($externalId);
            if ($orderItems === []) {
                throw new \InvalidArgumentException('Jumia order has no line items.');
            }

            $jumiaStatus = $this->resolveOrderStatus($order, $orderItems);
            $mapped = $this->statusMapper->fromJumia($jumiaStatus);

            $existing = PosSale::query()
                ->where('source', OrderSource::JUMIA)
                ->where('external_id', $externalId)
                ->first();

            $client = $this->resolveClient($order);
            $lineRows = [];
            $subtotalHt = 0.0;
            $taxTotal = 0.0;
            $orderItemIds = [];

            foreach ($orderItems as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $qty = max(1, (int) ($item['Quantity'] ?? 1));
                $sku = trim((string) ($item['ShopSku'] ?? $item['Sku'] ?? ''));
                $product = $sku !== '' ? Product::query()->where('ref', $sku)->first() : null;

                $lineTotalTtc = round((float) ($item['PaidPrice'] ?? $item['ItemPrice'] ?? 0) * $qty, 2);
                $taxRate = $this->defaultTaxRate($product);
                $base = round($lineTotalTtc / (1 + ($taxRate / 100)), 2);
                $tax = round($lineTotalTtc - $base, 2);
                $unitPrice = $qty > 0 ? round($base / $qty, 2) : 0.0;

                $subtotalHt += $base;
                $taxTotal += $tax;

                if (! empty($item['OrderItemId'])) {
                    $orderItemIds[] = (string) $item['OrderItemId'];
                }

                $lineRows[] = [
                    'product' => $product,
                    'ref' => $sku !== '' ? $sku : null,
                    'designation' => (string) ($item['Name'] ?? $item['ProductName'] ?? 'Article Jumia'),
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'tax_rate' => $taxRate,
                    'discount' => 0.0,
                    'line_total' => $lineTotalTtc,
                ];
            }

            $total = round($subtotalHt + $taxTotal, 2);
            $orderNumber = (string) ($order['OrderNumber'] ?? $externalId);
            $ticketNumber = 'JUM-'.$orderNumber;
            $soldAt = $this->parseDate($order['CreatedAt'] ?? null) ?? now();
            $currency = strtoupper((string) ($order['Currency'] ?? 'MAD')).' — Jumia';

            $metadata = [
                'jumia_status' => $jumiaStatus,
                'order_item_ids' => $orderItemIds,
                'order_number' => $orderNumber,
            ];

            $payload = [
                'client_id' => $client?->id,
                'sold_at' => $soldAt,
                'currency' => $currency,
                'subtotal' => $subtotalHt,
                'discount' => 0,
                'tax_total' => $taxTotal,
                'total' => $total,
                'payment_method' => PosSale::PAYMENT_CARD,
                'amount_received' => $mapped['payment_status'] === 'paid' ? $total : null,
                'change_amount' => 0,
                'status' => $mapped['status'],
                'payment_status' => $mapped['payment_status'],
                'fulfillment_status' => $mapped['fulfillment_status'],
                'jumia_synced_at' => now(),
                'external_metadata' => $metadata,
                'notes' => 'Commande Jumia #'.$orderNumber.' (id '.$externalId.')',
            ];

            if ($existing) {
                $existing->update($payload);
                PosSaleItem::where('pos_sale_id', $existing->id)->delete();
                $sale = $existing;
            } else {
                $sale = PosSale::create(array_merge($payload, [
                    'ticket_number' => $ticketNumber,
                    'user_id' => null,
                    'source' => OrderSource::JUMIA,
                    'external_id' => $externalId,
                ]));
            }

            foreach ($lineRows as $row) {
                PosSaleItem::create([
                    'pos_sale_id' => $sale->id,
                    'product_id' => $row['product']?->id,
                    'ref' => $row['ref'],
                    'designation' => $row['designation'],
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'tax_rate' => $row['tax_rate'],
                    'discount' => $row['discount'],
                    'line_total' => $row['line_total'],
                ]);
            }

            $sale->refresh();
            $this->orderToInvoiceConverter->tryAutoGenerate($sale);

            return $sale;
        });
    }

    /**
     * @param  array<string, mixed>  $order
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function resolveOrderStatus(array $order, array $items): string
    {
        if (! empty($order['Statuses']['Status'])) {
            $statuses = $order['Statuses']['Status'];
            if (is_array($statuses) && isset($statuses[0])) {
                return (string) ($statuses[0] ?? 'pending');
            }

            return (string) $statuses;
        }

        if (! empty($order['Status'])) {
            return (string) $order['Status'];
        }

        return (string) ($items[0]['Status'] ?? 'pending');
    }

    /**
     * @param  array<string, mixed>  $order
     */
    protected function resolveClient(array $order): ?Client
    {
        $firstName = trim((string) ($order['CustomerFirstName'] ?? ''));
        $lastName = trim((string) ($order['CustomerLastName'] ?? ''));
        $name = trim($firstName.' '.$lastName);

        $address = $order['AddressShipping'] ?? $order['AddressBilling'] ?? [];
        if (is_array($address)) {
            $name = $name !== '' ? $name : trim((string) ($address['FirstName'] ?? '').' '.(string) ($address['LastName'] ?? ''));
            $phone = trim((string) ($address['Phone'] ?? $address['Phone2'] ?? ''));
            $city = trim((string) ($address['City'] ?? ''));
            $country = trim((string) ($address['Country'] ?? ''));
            $street = trim((string) ($address['Address1'] ?? $address['Address'] ?? ''));
        } else {
            $phone = '';
            $city = '';
            $country = '';
            $street = '';
        }

        if ($phone !== '') {
            $client = Client::query()->where('phone', $phone)->first();
            if ($client) {
                return $client;
            }
        }

        if ($name === '') {
            $name = 'Client Jumia';
        }

        return Client::create([
            'name' => $name,
            'email' => null,
            'phone' => $phone !== '' ? $phone : null,
            'address' => $street !== '' ? $street : null,
            'city' => $city !== '' ? $city : null,
            'country' => $country !== '' ? $country : null,
        ]);
    }

    protected function parseDate(?string $value): ?Carbon
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

    protected function defaultTaxRate(?Product $product): float
    {
        if (! $product) {
            return 20.0;
        }

        $vat = strtolower((string) ($product->vat_category ?? ''));
        if (str_contains($vat, '10') || str_contains($vat, 'réduit') || str_contains($vat, 'reduit')) {
            return 10.0;
        }
        if (str_contains($vat, '0') || str_contains($vat, 'exempt')) {
            return 0.0;
        }

        return 20.0;
    }
}
