<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\User;
use App\Support\LineItemCalculator;
use App\Support\OrderSource;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesReturnService
{
    public function __construct(
        protected InvoiceSituationService $situation,
        protected StockMovementService $stockMovement
    ) {}

    /**
     * Process a Shopify refund webhook payload and create avoir if invoice exists.
     */
    public function processShopifyRefund(array $refund, array $shopifyOrder): ?CreditNote
    {
        $refundId = (string) ($refund['id'] ?? '');
        if ($refundId === '') {
            return null;
        }

        if (CreditNote::query()->where('source', OrderSource::SHOPIFY)->where('external_id', $refundId)->exists()) {
            return CreditNote::query()
                ->where('source', OrderSource::SHOPIFY)
                ->where('external_id', $refundId)
                ->first();
        }

        $orderId = (string) ($refund['order_id'] ?? $shopifyOrder['id'] ?? '');
        $sale = PosSale::query()
            ->where('source', OrderSource::SHOPIFY)
            ->where(function ($q) use ($orderId) {
                $q->where('external_id', $orderId)
                    ->orWhere('shopify_order_id', $orderId);
            })
            ->first();

        if (! $sale) {
            Log::info('Shopify refund: order not found locally', ['order_id' => $orderId, 'refund_id' => $refundId]);

            return null;
        }

        $invoice = Invoice::query()->where('pos_sale_id', $sale->id)->first();
        if (! $invoice) {
            Log::info('Shopify refund: no invoice for order', ['order_id' => $orderId, 'refund_id' => $refundId]);

            return null;
        }

        $lines = $this->buildLinesFromShopifyRefund($refund, $shopifyOrder, $invoice);
        if ($lines === []) {
            $amount = $this->refundAmountFromTransactions($refund);
            if ($amount <= 0) {
                return null;
            }

            $lines = $this->buildProportionalLines($invoice, $amount);
        }

        $returnType = $this->detectReturnType($invoice, $lines);

        return $this->createCreditNote([
            'invoice' => $invoice,
            'sale' => $sale,
            'lines' => $lines,
            'source' => OrderSource::SHOPIFY,
            'external_id' => $refundId,
            'return_type' => $returnType,
            'credit_note_date' => isset($refund['created_at'])
                ? Carbon::parse($refund['created_at'])->toDateString()
                : now()->toDateString(),
            'remarks' => 'Avoir auto — remboursement Shopify #'.$refundId,
            'physical_return' => true,
            'restock' => false,
        ]);
    }

    /**
     * Process a Jumia return/refund from import data.
     *
     * @param  array{order_id?: string, amount?: float, external_id?: string, date?: string, notes?: string}  $data
     */
    public function processJumiaReturn(array $data): ?CreditNote
    {
        $externalId = (string) ($data['external_id'] ?? '');
        if ($externalId !== '' && CreditNote::query()->where('source', OrderSource::JUMIA)->where('external_id', $externalId)->exists()) {
            return CreditNote::query()
                ->where('source', OrderSource::JUMIA)
                ->where('external_id', $externalId)
                ->first();
        }

        $orderId = (string) ($data['order_id'] ?? '');
        if ($orderId === '') {
            return null;
        }

        $sale = PosSale::query()
            ->where('source', OrderSource::JUMIA)
            ->where('external_id', $orderId)
            ->first();

        if (! $sale) {
            return null;
        }

        $invoice = Invoice::query()->where('pos_sale_id', $sale->id)->first();
        if (! $invoice) {
            return null;
        }

        $amount = round((float) ($data['amount'] ?? 0), 2);
        $lines = $amount > 0
            ? $this->buildProportionalLines($invoice, $amount)
            : $this->buildFullInvoiceLines($invoice);

        return $this->createCreditNote([
            'invoice' => $invoice,
            'sale' => $sale,
            'lines' => $lines,
            'source' => OrderSource::JUMIA,
            'external_id' => $externalId ?: null,
            'return_type' => $this->detectReturnType($invoice, $lines),
            'credit_note_date' => $data['date'] ?? now()->toDateString(),
            'remarks' => $data['notes'] ?? 'Avoir auto — retour Jumia',
            'physical_return' => (bool) ($data['physical_return'] ?? true),
            'restock' => (bool) ($data['restock'] ?? false),
        ]);
    }

    /**
     * @param  array{
     *   invoice: Invoice,
     *   sale?: PosSale|null,
     *   lines: array<int, array{product_id?: int|null, ref?: string|null, designation: string, quantity: int, unit_price: float, tax_rate: float, discount?: float, discount_type?: string}>,
     *   source?: string|null,
     *   external_id?: string|null,
     *   return_type?: string,
     *   credit_note_date: string,
     *   remarks?: string|null,
     *   physical_return?: bool,
     *   restock?: bool,
     *   product_condition?: string|null,
     *   return_location?: string|null,
     *   actor?: User|null,
     * }  $params
     */
    public function createCreditNote(array $params): CreditNote
    {
        return DB::transaction(function () use ($params) {
            /** @var Invoice $invoice */
            $invoice = $params['invoice'];
            $sale = $params['sale'] ?? $invoice->posSale;

            $creditNote = CreditNote::create([
                'credit_note_number' => DocumentNumberService::generate('avoir'),
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,
                'pos_sale_id' => $sale?->id,
                'source' => $params['source'] ?? $sale?->source,
                'external_id' => $params['external_id'] ?? null,
                'return_type' => $params['return_type'] ?? 'partial_return',
                'credit_note_date' => $params['credit_note_date'],
                'currency' => $invoice->currency,
                'stock_location' => $invoice->stock_location,
                'remarks' => $params['remarks'] ?? null,
                'physical_return' => (bool) ($params['physical_return'] ?? false),
                'restock' => (bool) ($params['restock'] ?? false),
                'product_condition' => $params['product_condition'] ?? null,
                'return_location' => $params['return_location'] ?? $invoice->stock_location,
                'created_by' => $params['actor']?->id,
                'subtotal' => 0,
                'discount' => 0,
                'adjustment' => 0,
                'total' => 0,
            ]);

            $subtotal = 0.0;
            foreach ($params['lines'] as $line) {
                $computed = LineItemCalculator::compute([
                    'quantity' => (int) $line['quantity'],
                    'unit_price' => (float) $line['unit_price'],
                    'tax_rate' => (float) ($line['tax_rate'] ?? 0),
                    'discount' => (float) ($line['discount'] ?? 0),
                    'discount_type' => $line['discount_type'] ?? 'fixed',
                ]);
                $lineTotal = $computed['line_total'];

                InvoiceItem::create([
                    'itemable_type' => CreditNote::class,
                    'itemable_id' => $creditNote->id,
                    'product_id' => $line['product_id'] ?? null,
                    'ref' => $line['ref'] ?? null,
                    'designation' => $line['designation'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'tax_rate' => $line['tax_rate'] ?? 0,
                    'discount' => $line['discount'] ?? 0,
                    'discount_type' => $line['discount_type'] ?? 'fixed',
                    'line_total' => $lineTotal,
                ]);

                $subtotal += $lineTotal;
            }

            $creditNote->update([
                'subtotal' => $subtotal,
                'total' => $subtotal,
            ]);

            if ($creditNote->restock && $creditNote->physical_return) {
                $creditNote->load('items');
                $this->stockMovement->increaseFromItems($creditNote->items, $creditNote->stock_location);
            }

            $this->situation->syncCommercialStatus($invoice);

            $invoice->recordActivity(
                'credit_note_created',
                'Avoir '.$creditNote->credit_note_number.' créé (retour/remboursement)',
                $params['actor']?->id,
                ['credit_note_id' => $creditNote->id]
            );

            return $creditNote->fresh(['items', 'invoice', 'posSale']);
        });
    }

    /**
     * @return array<int, array{product_id?: int|null, ref?: string|null, designation: string, quantity: int, unit_price: float, tax_rate: float, discount?: float, discount_type?: string}>
     */
    private function buildLinesFromShopifyRefund(array $refund, array $shopifyOrder, Invoice $invoice): array
    {
        $invoice->loadMissing('items');
        $lines = [];

        $orderLineItems = collect($shopifyOrder['line_items'] ?? [])->keyBy('id');

        foreach ($refund['refund_line_items'] ?? [] as $rli) {
            if (! is_array($rli)) {
                continue;
            }

            $lineItemId = $rli['line_item_id'] ?? ($rli['line_item']['id'] ?? null);
            $qty = (int) ($rli['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $shopifyLine = $lineItemId ? $orderLineItems->get($lineItemId) : null;
            $sku = trim((string) ($shopifyLine['sku'] ?? ''));
            $product = $sku !== '' ? Product::query()->where('ref', $sku)->first() : null;

            $invoiceItem = $invoice->items->first(function ($item) use ($sku, $shopifyLine) {
                if ($sku !== '' && $item->ref === $sku) {
                    return true;
                }

                $name = (string) ($shopifyLine['name'] ?? $shopifyLine['title'] ?? '');

                return $name !== '' && str_contains(strtolower($item->designation), strtolower($name));
            });

            $unitPrice = $invoiceItem
                ? (float) $invoiceItem->display_unit_price_ht
                : $this->unitPriceHtFromShopifyLine($shopifyLine, $qty);

            $taxRate = $invoiceItem
                ? (float) $invoiceItem->tax_rate
                : $this->lineTaxRate($shopifyLine, $product);

            $lines[] = [
                'product_id' => $invoiceItem?->product_id ?? $product?->id,
                'ref' => $sku ?: $invoiceItem?->ref,
                'designation' => $invoiceItem?->designation ?? (string) ($shopifyLine['name'] ?? $shopifyLine['title'] ?? 'Article retourné'),
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'discount' => (float) ($invoiceItem?->discount ?? 0),
                'discount_type' => $invoiceItem?->discount_type ?? 'fixed',
            ];
        }

        return $lines;
    }

    /**
     * @param  array<int, array{quantity: int, unit_price: float, tax_rate: float, discount?: float, discount_type?: string}>  $newLines
     */
    private function detectReturnType(Invoice $invoice, array $newLines): string
    {
        $invoice->loadMissing('creditNotes.items', 'items');

        $existingCredits = (float) $invoice->creditNotes->sum(fn (CreditNote $cn) => (float) $cn->computed_total);
        $newCredit = 0.0;
        foreach ($newLines as $line) {
            $computed = LineItemCalculator::compute([
                'quantity' => (int) $line['quantity'],
                'unit_price' => (float) $line['unit_price'],
                'tax_rate' => (float) ($line['tax_rate'] ?? 0),
                'discount' => (float) ($line['discount'] ?? 0),
                'discount_type' => $line['discount_type'] ?? 'fixed',
            ]);
            $newCredit += $computed['line_total'];
        }

        $totalCredits = $existingCredits + $newCredit;
        $invoiceTotal = (float) $invoice->computed_total;

        if ($totalCredits >= $invoiceTotal - 0.009) {
            return 'total_return';
        }

        return 'partial_return';
    }

    /**
     * @return array<int, array{product_id?: int|null, ref?: string|null, designation: string, quantity: int, unit_price: float, tax_rate: float, discount?: float, discount_type?: string}>
     */
    private function buildProportionalLines(Invoice $invoice, float $amount): array
    {
        $invoice->loadMissing('items');
        if ($invoice->items->isEmpty()) {
            return [[
                'designation' => 'Remboursement',
                'quantity' => 1,
                'unit_price' => $amount,
                'tax_rate' => 0,
            ]];
        }

        $invoiceTotal = (float) $invoice->computed_total;
        if ($invoiceTotal <= 0) {
            return $this->buildFullInvoiceLines($invoice);
        }

        $ratio = min(1, $amount / $invoiceTotal);
        $lines = [];

        foreach ($invoice->items as $item) {
            $qty = max(1, (int) round($item->quantity * $ratio));
            if ($qty <= 0) {
                continue;
            }

            $lines[] = [
                'product_id' => $item->product_id,
                'ref' => $item->ref,
                'designation' => $item->designation,
                'quantity' => $qty,
                'unit_price' => (float) $item->display_unit_price_ht,
                'tax_rate' => (float) $item->tax_rate,
                'discount' => (float) $item->discount,
                'discount_type' => $item->discount_type ?? 'fixed',
            ];
        }

        return $lines;
    }

    /**
     * @return array<int, array{product_id?: int|null, ref?: string|null, designation: string, quantity: int, unit_price: float, tax_rate: float, discount?: float, discount_type?: string}>
     */
    private function buildFullInvoiceLines(Invoice $invoice): array
    {
        $invoice->loadMissing('items');

        return $invoice->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'ref' => $item->ref,
            'designation' => $item->designation,
            'quantity' => $item->quantity,
            'unit_price' => (float) $item->display_unit_price_ht,
            'tax_rate' => (float) $item->tax_rate,
            'discount' => (float) $item->discount,
            'discount_type' => $item->discount_type ?? 'fixed',
        ])->values()->all();
    }

    private function refundAmountFromTransactions(array $refund): float
    {
        $total = 0.0;
        foreach ($refund['transactions'] ?? [] as $tx) {
            if (! is_array($tx)) {
                continue;
            }
            if (($tx['kind'] ?? '') === 'refund' && ($tx['status'] ?? '') === 'success') {
                $total += abs((float) ($tx['amount'] ?? 0));
            }
        }

        return round($total, 2);
    }

    private function unitPriceHtFromShopifyLine(?array $line, int $qty): float
    {
        if (! $line || $qty <= 0) {
            return 0.0;
        }

        $unitPriceTtc = (float) ($line['price'] ?? 0);
        $taxRate = $this->lineTaxRate($line, null);

        return round($unitPriceTtc / (1 + ($taxRate / 100)), 2);
    }

    private function lineTaxRate(?array $line, ?Product $product): float
    {
        if ($product && $product->tax_rate) {
            return (float) $product->tax_rate;
        }

        foreach ($line['tax_lines'] ?? [] as $taxLine) {
            if (isset($taxLine['rate'])) {
                return round((float) $taxLine['rate'] * 100, 2);
            }
        }

        return 20.0;
    }
}
