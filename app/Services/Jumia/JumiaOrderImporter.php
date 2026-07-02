<?php

namespace App\Services\Jumia;

use App\Models\Client;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Product;
use App\Services\MarketplaceStockSyncService;
use App\Services\OrderToInvoiceConverter;
use App\Support\OrderSource;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class JumiaOrderImporter
{
    public function __construct(
        protected JumiaApiClient $client,
        protected JumiaStatusMapper $statusMapper,
        protected OrderToInvoiceConverter $orderToInvoiceConverter,
        protected MarketplaceStockSyncService $stockSync
    ) {}

    /**
     * @param  array<string, mixed>  $order
     */
    public function import(array $order): PosSale
    {
        return DB::transaction(function () use ($order) {
            $externalId = (string) ($order['id'] ?? $order['orderId'] ?? $order['OrderId'] ?? $order['OrderNumber'] ?? '');
            if ($externalId === '') {
                throw new \InvalidArgumentException('Missing Jumia order id.');
            }

            $orderItems = $this->flattenOrderItems($this->resolveOrderItems($order, $externalId));
            if ($orderItems === []) {
                throw new \InvalidArgumentException('Jumia order has no line items.');
            }

            $jumiaStatus = $this->resolveOrderStatus($order, $orderItems);
            $mapped = $this->statusMapper->fromJumia($jumiaStatus);

            $existing = PosSale::query()
                ->where('source', OrderSource::JUMIA)
                ->where('external_id', $externalId)
                ->first();

            $previousStockApplied = $this->stockSync->previousAppliedFromSale($existing);

            $client = $this->resolveClient($order, $orderItems);
            $lineRows = [];
            $subtotalHt = 0.0;
            $taxTotal = 0.0;
            $shippingTotal = 0.0;
            $orderItemIds = [];

            foreach ($orderItems as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $qty = max(1, (int) ($item['quantity'] ?? $item['Quantity'] ?? 1));
                $sku = trim((string) ($item['product']['sellerSku'] ?? $item['ShopSku'] ?? $item['Sku'] ?? ''));
                $product = $sku !== '' ? Product::query()->where('ref', $sku)->first() : null;

                $unitPriceTtc = $this->resolveItemUnitPriceTtc($item);
                $lineProductTtc = round($unitPriceTtc * $qty, 2);
                $lineShippingTtc = round($this->resolveItemShippingAmount($item), 2);
                $lineTotalTtc = round($lineProductTtc + $lineShippingTtc, 2);
                $taxRate = $this->resolveItemTaxRate($item, $product);
                $base = $taxRate > 0
                    ? round($lineTotalTtc / (1 + ($taxRate / 100)), 2)
                    : $lineTotalTtc;
                $tax = round($lineTotalTtc - $base, 2);
                $unitPriceHt = $qty > 0 ? round(($lineProductTtc / (1 + ($taxRate / 100))) / $qty, 2) : 0.0;

                $subtotalHt += $base;
                $taxTotal += $tax;
                $shippingTotal += $lineShippingTtc;

                $orderItemId = $item['id'] ?? $item['OrderItemId'] ?? null;
                if (! empty($orderItemId)) {
                    $orderItemIds[] = (string) $orderItemId;
                }

                $lineRows[] = [
                    'product' => $product,
                    'ref' => $sku !== '' ? $sku : null,
                    'designation' => $this->resolveItemName($item),
                    'quantity' => $qty,
                    'unit_price' => $unitPriceHt,
                    'tax_rate' => $taxRate,
                    'discount' => 0.0,
                    'line_total' => $lineTotalTtc,
                ];
            }

            $computedTotal = round($subtotalHt + $taxTotal, 2);
            $orderTotal = $this->resolveOrderGrandTotal($order, $computedTotal, $shippingTotal);
            $orderNumber = (string) ($order['number'] ?? $order['orderNumber'] ?? $order['OrderNumber'] ?? $externalId);
            $ticketNumber = 'JUM-'.$orderNumber;
            $soldAt = $this->parseDate($order['createdAt'] ?? $order['CreatedAt'] ?? null) ?? now();
            $currency = strtoupper($this->resolveCurrency($order)).' — Jumia';

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
                'total' => $orderTotal,
                'payment_method' => PosSale::PAYMENT_CARD,
                'amount_received' => $mapped['payment_status'] === 'paid' ? $orderTotal : null,
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

            $this->stockSync->syncOrderStock(
                $sale,
                $previousStockApplied,
                $this->stockSync->quantitiesFromLineRows($lineRows),
                OrderSource::JUMIA
            );

            return $sale;
        });
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<int, array<string, mixed>>
     */
    protected function resolveOrderItems(array $order, string $externalId): array
    {
        if (! empty($order['items']) && is_array($order['items'])) {
            return array_values(array_filter($order['items'], 'is_array'));
        }

        return $this->client->getOrderItems($externalId);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function flattenOrderItems(array $items): array
    {
        $flat = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (isset($item['items']) && is_array($item['items'])) {
                foreach ($item['items'] as $lineItem) {
                    if (is_array($lineItem)) {
                        $flat[] = $lineItem;
                    }
                }

                continue;
            }

            $flat[] = $item;
        }

        return array_values($flat);
    }

    /**
     * @param  array<string, mixed>  $order
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function resolveOrderStatus(array $order, array $items): string
    {
        if (! empty($order['status'])) {
            return (string) $order['status'];
        }

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

        return (string) ($items[0]['status'] ?? $items[0]['Status'] ?? 'pending');
    }

    /**
     * @param  array<string, mixed>  $order
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function resolveClient(array $order, array $items = []): ?Client
    {
        $address = $order['shippingAddress'] ?? $order['AddressShipping'] ?? $order['AddressBilling'] ?? [];

        if (! is_array($address) || $address === []) {
            $firstItem = $items[0] ?? null;
            if (is_array($firstItem)) {
                $address = $firstItem['shippingAddress'] ?? $firstItem['ShippingAddress'] ?? [];
            }
        }

        if (! is_array($address)) {
            $address = [];
        }

        $firstName = trim((string) ($address['firstName'] ?? $address['FirstName'] ?? $order['CustomerFirstName'] ?? ''));
        $lastName = trim((string) ($address['lastName'] ?? $address['LastName'] ?? $order['CustomerLastName'] ?? ''));
        $name = trim($firstName.' '.$lastName);
        $phone = trim((string) ($address['phone'] ?? $address['Phone'] ?? $address['Phone2'] ?? ''));
        $city = trim((string) ($address['city'] ?? $address['City'] ?? ''));
        $country = trim((string) ($address['countryName'] ?? $address['Country'] ?? $order['country']['name'] ?? ''));
        $street = trim((string) ($address['address'] ?? $address['Address1'] ?? $address['Address'] ?? ''));

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

    /**
     * @param  array<string, mixed>  $item
     */
    protected function resolveItemUnitPriceTtc(array $item): float
    {
        foreach ([
            'paidPriceLocal',
            'itemPriceLocal',
            'paidPrice',
            'itemPrice',
            'PaidPrice',
            'ItemPrice',
            'Price',
            'price',
        ] as $key) {
            $value = $this->extractMoneyValue($item[$key] ?? null);

            if ($value !== null) {
                return $value;
            }
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function resolveItemShippingAmount(array $item): float
    {
        foreach ([
            'shippingAmountLocal',
            'shippingAmount',
            'ShippingAmount',
        ] as $key) {
            $value = $this->extractMoneyValue($item[$key] ?? null);

            if ($value !== null) {
                return max(0, $value);
            }
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function resolveItemTaxRate(array $item, ?Product $product): float
    {
        $productPrice = $this->resolveItemUnitPriceTtc($item);
        $taxAmount = $this->extractMoneyValue($item['taxAmount'] ?? $item['TaxAmount'] ?? null);

        if ($productPrice > 0 && $taxAmount !== null && $taxAmount > 0) {
            $impliedRate = ($taxAmount / max(0.01, $productPrice - $taxAmount)) * 100;

            if ($impliedRate >= 0 && $impliedRate <= 30) {
                return round($impliedRate, 2);
            }
        }

        return $this->defaultTaxRate($product);
    }

    /**
     * @param  array<string, mixed>  $order
     */
    protected function resolveOrderGrandTotal(array $order, float $computedTotal, float $shippingTotal): float
    {
        foreach ([
            'totalAmountLocal',
            'grandTotalLocal',
            'orderTotalLocal',
            'totalAmount',
            'grandTotal',
            'GrandTotal',
            'Price',
        ] as $key) {
            $value = $this->extractMoneyValue($order[$key] ?? null);

            if ($value !== null && $value > 0) {
                return round($value, 2);
            }
        }

        if ($computedTotal > 0) {
            return round($computedTotal, 2);
        }

        return round($shippingTotal, 2);
    }

    protected function extractMoneyValue(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (! is_array($value)) {
            return null;
        }

        foreach (['value', 'amount', 'localValue'] as $key) {
            if (isset($value[$key]) && is_numeric($value[$key])) {
                return (float) $value[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @deprecated Use resolveItemUnitPriceTtc()
     */
    protected function resolveItemPrice(array $item): float
    {
        return $this->resolveItemUnitPriceTtc($item);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function resolveItemName(array $item): string
    {
        return (string) (
            $item['product']['name']
            ?? $item['Name']
            ?? $item['ProductName']
            ?? 'Article Jumia'
        );
    }

    /**
     * @param  array<string, mixed>  $order
     */
    protected function resolveCurrency(array $order): string
    {
        return (string) (
            $order['totalAmountLocal']['currency']
            ?? $order['country']['currencyCode']
            ?? $order['items'][0]['country']['currencyCode']
            ?? $order['Currency']
            ?? 'MAD'
        );
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
