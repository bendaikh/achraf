<?php

namespace App\Services;

use App\Models\CustomerReturn;
use App\Models\CustomerReturnLine;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PosSale;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\StockSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CustomerReturnService
{
    public function __construct(
        protected SalesReturnService $salesReturn,
        protected StockMovementService $stockMovement,
    ) {}

    /**
     * Find an order for scan/search without creating stock movements.
     *
     * @return list<PosSale>
     */
    public function searchOrders(string $query): array
    {
        $q = trim($query);
        if ($q === '') {
            return [];
        }

        return PosSale::query()
            ->with(['client', 'items.product', 'items.variant', 'invoice', 'fulfillments', 'trackings'])
            ->where(function ($builder) use ($q) {
                $builder->where('ticket_number', 'like', '%'.$q.'%')
                    ->orWhere('external_id', 'like', '%'.$q.'%')
                    ->orWhere('shopify_order_id', 'like', '%'.$q.'%')
                    ->orWhere('shopify_order_number', 'like', '%'.$q.'%')
                    ->orWhereHas('fulfillments', fn ($f) => $f->where('tracking_number', 'like', '%'.$q.'%'))
                    ->orWhereHas('trackings', fn ($t) => $t->where('tracking_number', 'like', '%'.$q.'%'))
                    ->orWhereHas('client', function ($c) use ($q) {
                        $c->where('name', 'like', '%'.$q.'%')
                            ->orWhere('phone', 'like', '%'.$q.'%')
                            ->orWhere('email', 'like', '%'.$q.'%');
                    });
            })
            ->orderByDesc('id')
            ->limit(25)
            ->get()
            ->all();
    }

    /**
     * Create a draft return from an order (scan → draft). No stock / credit note yet.
     */
    public function createDraft(PosSale $order, ?string $searchKey = null): CustomerReturn
    {
        $existing = CustomerReturn::query()
            ->draft()
            ->where('pos_sale_id', $order->id)
            ->first();

        if ($existing) {
            return $existing->load(['lines', 'order.items.product', 'invoice', 'client']);
        }

        $order->loadMissing(['items.product', 'items.variant', 'client', 'invoice', 'fulfillments', 'trackings']);
        $fulfillment = Warehouse::fulfillmentWarehouse();
        $sav = Warehouse::savWarehouse();

        return DB::transaction(function () use ($order, $searchKey, $fulfillment, $sav) {
            $return = CustomerReturn::create([
                'reference' => $this->nextReference(),
                'status' => CustomerReturn::STATUS_DRAFT,
                'pos_sale_id' => $order->id,
                'client_id' => $order->client_id,
                'invoice_id' => $order->invoice?->id,
                'source' => $order->source,
                'search_key' => $searchKey,
                'tracking_number' => $order->primaryTrackingNumber(),
                'create_credit_note' => (bool) $order->invoice,
                'created_by' => Auth::id(),
            ]);

            foreach ($order->items as $item) {
                $product = $item->product;
                if ($product && ! $product->tracksStock()) {
                    continue;
                }

                CustomerReturnLine::create([
                    'customer_return_id' => $return->id,
                    'pos_sale_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'sku' => $item->variant?->sku ?: $product?->ref,
                    'designation' => $item->designation ?: $product?->name,
                    'quantity_ordered' => (int) $item->quantity,
                    'quantity_expected' => (int) $item->quantity,
                    'quantity_received' => (int) $item->quantity,
                    'condition' => CustomerReturnLine::CONDITION_VENDABLE,
                    'warehouse_id' => $fulfillment?->id,
                    'warehouse_location_id' => null,
                ]);
            }

            // Keep $sav referenced for future defaulting of damaged lines in UI.
            unset($sav);

            return $return->load(['lines.product', 'lines.variant', 'order.items', 'invoice', 'client']);
        });
    }

    /**
     * Update draft control lines (still no stock movement).
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function updateDraft(CustomerReturn $return, array $lines, ?string $notes = null, bool $createCreditNote = true): CustomerReturn
    {
        if (! $return->isDraft()) {
            throw new RuntimeException('Seul un retour en brouillon peut être modifié.');
        }

        return DB::transaction(function () use ($return, $lines, $notes, $createCreditNote) {
            $return->update([
                'notes' => $notes,
                'create_credit_note' => $createCreditNote,
            ]);

            foreach ($lines as $lineId => $data) {
                $line = CustomerReturnLine::query()
                    ->where('customer_return_id', $return->id)
                    ->where('id', $lineId)
                    ->first();
                if (! $line) {
                    continue;
                }

                $condition = (string) ($data['condition'] ?? $line->condition);
                if (! array_key_exists($condition, CustomerReturnLine::CONDITIONS)) {
                    $condition = CustomerReturnLine::CONDITION_VENDABLE;
                }

                $qtyReceived = max(0, (int) ($data['quantity_received'] ?? $line->quantity_received));
                if ($condition === CustomerReturnLine::CONDITION_MISSING) {
                    $qtyReceived = 0;
                }

                $warehouseId = ! empty($data['warehouse_id']) ? (int) $data['warehouse_id'] : $line->warehouse_id;
                $locationId = ! empty($data['warehouse_location_id']) ? (int) $data['warehouse_location_id'] : null;

                // Non-vendable → force SAV warehouse when available.
                if (in_array($condition, [
                    CustomerReturnLine::CONDITION_DAMAGED,
                    CustomerReturnLine::CONDITION_TO_CHECK,
                ], true)) {
                    $sav = Warehouse::savWarehouse();
                    if ($sav) {
                        $warehouseId = $sav->id;
                        $locationId = null;
                    }
                }

                $line->update([
                    'quantity_received' => $qtyReceived,
                    'condition' => $condition,
                    'warehouse_id' => $warehouseId,
                    'warehouse_location_id' => $locationId,
                    'notes' => $data['notes'] ?? $line->notes,
                ]);
            }

            return $return->fresh(['lines', 'order', 'invoice', 'client']);
        });
    }

    /**
     * Validate return: stock movements + optional credit note. Idempotent.
     */
    public function validate(CustomerReturn $return): CustomerReturn
    {
        if ($return->isValidated()) {
            return $return->fresh(['lines', 'creditNote', 'order', 'invoice']);
        }
        if (! $return->isDraft()) {
            throw new RuntimeException('Ce retour ne peut pas être validé.');
        }

        return DB::transaction(function () use ($return) {
            $return = CustomerReturn::query()->lockForUpdate()->findOrFail($return->id);
            if ($return->isValidated()) {
                return $return->fresh(['lines', 'creditNote', 'order', 'invoice']);
            }

            $return->load(['lines.product', 'order', 'invoice.items', 'client']);

            foreach ($return->lines as $line) {
                if ($line->stock_applied || ! $line->shouldRestock() || ! $line->product_id) {
                    continue;
                }

                $product = $line->product;
                if (! $product || ! $product->tracksStock()) {
                    continue;
                }

                $warehouseId = (int) ($line->warehouse_id ?: Warehouse::fulfillmentWarehouse()?->id);
                if (! $warehouseId) {
                    throw new RuntimeException('Aucun dépôt de destination pour le retour.');
                }

                $this->stockMovement->increase(
                    $product,
                    (int) $line->quantity_received,
                    'magasin',
                    true,
                    StockMovement::TYPE_CUSTOMER_RETURN,
                    'customer_return',
                    $return->id,
                    $return->reference,
                    $warehouseId,
                    $line->warehouse_location_id ? (int) $line->warehouse_location_id : null,
                    'Retour client '.$return->reference.' ('.$line->conditionLabel().')',
                    null,
                    $line->product_variant_id ? (int) $line->product_variant_id : null
                );

                $line->update(['stock_applied' => true]);
            }

            $creditNote = null;
            if ($return->create_credit_note && $return->invoice_id) {
                $invoice = Invoice::query()->find($return->invoice_id);
                if ($invoice && $this->invoiceIsValidated($invoice)) {
                    $creditLines = $this->creditLinesFromReturn($return, $invoice);
                    if ($creditLines !== []) {
                        $creditNote = $this->salesReturn->createCreditNote([
                            'invoice' => $invoice,
                            'sale' => $return->order,
                            'lines' => $creditLines,
                            'source' => $return->source,
                            'external_id' => 'customer_return:'.$return->id,
                            'return_type' => count($creditLines) >= $invoice->items->count() ? 'total_return' : 'partial_return',
                            'credit_note_date' => now()->toDateString(),
                            'remarks' => 'Avoir depuis retour '.$return->reference,
                            'physical_return' => true,
                            'restock' => false, // stock already applied above once
                            'product_condition' => $return->lines->pluck('condition')->unique()->implode(','),
                            'return_location' => Warehouse::fulfillmentWarehouse()?->name,
                            'actor' => Auth::user(),
                        ]);
                    }
                }
            }

            $return->update([
                'status' => CustomerReturn::STATUS_VALIDATED,
                'credit_note_id' => $creditNote?->id,
                'validated_by' => Auth::id(),
                'validated_at' => now(),
            ]);

            $return->order?->recordActivity(
                'customer_return_validated',
                'Retour '.$return->reference.' validé',
                Auth::id(),
                ['customer_return_id' => $return->id, 'credit_note_id' => $creditNote?->id]
            );

            return $return->fresh(['lines', 'creditNote', 'order', 'invoice', 'client']);
        });
    }

    public function cancelDraft(CustomerReturn $return): CustomerReturn
    {
        if (! $return->isDraft()) {
            throw new RuntimeException('Seul un brouillon peut être annulé.');
        }

        $return->update(['status' => CustomerReturn::STATUS_CANCELLED]);

        return $return->fresh();
    }

    /**
     * Context payload for the control screen.
     *
     * @return array<string, mixed>
     */
    public function orderContext(PosSale $order): array
    {
        $order->loadMissing([
            'client',
            'items.product',
            'items.variant',
            'invoice.items',
            'invoice.payments',
            'fulfillments',
            'trackings',
            'activities',
        ]);

        $deliveryNotes = collect();
        if ($order->invoice) {
            $deliveryNotes = DeliveryNote::query()
                ->where('converted_invoice_id', $order->invoice->id)
                ->limit(10)
                ->get(['id', 'delivery_note_number', 'delivery_date']);
        }

        $payments = $order->invoice
            ? InvoicePayment::query()->where('invoice_id', $order->invoice->id)->get()
            : collect();

        return [
            'order' => $order,
            'client' => $order->client,
            'channel' => $order->source,
            'tracking' => $order->primaryTrackingNumber(),
            'invoice' => $order->invoice,
            'payments' => $payments,
            'delivery_notes' => $deliveryNotes,
            'paid' => $order->invoice ? $order->invoice->isPaid() : ((float) ($order->amount_received ?? 0) > 0),
            'cod_uncollected' => $this->isCodUncollected($order),
        ];
    }

    protected function isCodUncollected(PosSale $order): bool
    {
        $method = strtolower((string) ($order->payment_method ?? ''));
        $isCod = str_contains($method, 'cod')
            || str_contains($method, 'cash_on_delivery')
            || $method === 'cash_on_delivery';

        if (! $isCod && $order->source === 'shopify') {
            // Many Shopify COD orders stay pending until carrier remittance.
            $isCod = ($order->payment_status ?? '') === 'pending' && (float) ($order->amount_received ?? 0) <= 0;
        }

        return $isCod && (float) ($order->amount_received ?? 0) <= 0
            && (! $order->invoice || ! $order->invoice->isPaid());
    }

    protected function invoiceIsValidated(Invoice $invoice): bool
    {
        return filled($invoice->invoice_number);
    }

    /**
     * @return list<array{product_id?: int|null, product_variant_id?: int|null, ref?: string|null, designation: string, quantity: int, unit_price: float, tax_rate: float, discount?: float, discount_type?: string}>
     */
    protected function creditLinesFromReturn(CustomerReturn $return, Invoice $invoice): array
    {
        $invoice->loadMissing('items');
        $lines = [];

        foreach ($return->lines as $line) {
            if ($line->condition === CustomerReturnLine::CONDITION_MISSING) {
                continue;
            }
            $qty = (int) $line->quantity_received;
            if ($qty <= 0) {
                continue;
            }

            $invoiceItem = $invoice->items->first(function ($item) use ($line) {
                if ($line->product_id && (int) $item->product_id === (int) $line->product_id) {
                    return true;
                }

                return $line->sku && $item->ref === $line->sku;
            });

            $lines[] = [
                'product_id' => $line->product_id,
                'product_variant_id' => $line->product_variant_id,
                'ref' => $line->sku,
                'designation' => $line->designation ?: ($invoiceItem?->designation ?? 'Article retourné'),
                'quantity' => $qty,
                'unit_price' => (float) ($invoiceItem?->display_unit_price_ht ?? $invoiceItem?->unit_price ?? 0),
                'tax_rate' => (float) ($invoiceItem?->tax_rate ?? 20),
                'discount' => (float) ($invoiceItem?->discount ?? 0),
                'discount_type' => $invoiceItem?->discount_type ?? 'fixed',
            ];
        }

        return $lines;
    }

    protected function nextReference(): string
    {
        try {
            return DocumentNumberService::generate('retour_client');
        } catch (\Throwable) {
            return 'RET-'.now()->format('Ymd').'-'.str_pad((string) (CustomerReturn::query()->count() + 1), 4, '0', STR_PAD_LEFT);
        }
    }

    public function assertFeatureEnabled(): void
    {
        if (! StockSettings::customerReturnsEnabled()) {
            throw new RuntimeException('Les retours clients / scan retours sont désactivés (Paramètres → Stock).');
        }
    }
}
