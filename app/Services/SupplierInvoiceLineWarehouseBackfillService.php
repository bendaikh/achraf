<?php

namespace App\Services;

use App\Models\PurchaseItem;
use App\Models\Reception;
use App\Models\SupplierDeliveryNote;
use App\Models\SupplierInvoice;
use Illuminate\Support\Collection;

class SupplierInvoiceLineWarehouseBackfillService
{
    public function __construct(
        protected PurchaseStockReceiptService $purchaseStockReceipt,
    ) {}

    /**
     * Remplit dépôt/emplacement des lignes de factures converties depuis BL/BR.
     * Idempotent. Ne crée aucun mouvement de stock.
     *
     * @return array{scanned:int, updated_lines:int, mirrored_allocations:int, stamped_stock:int, details:list<array<string,mixed>>}
     */
    public function backfill(bool $dryRun = true, ?int $invoiceId = null): array
    {
        $query = SupplierInvoice::query()
            ->with(['items', 'stockAllocations'])
            ->where(function ($q) {
                $q->whereHas('items', fn ($items) => $items->whereNull('warehouse_id'))
                    ->orWhereDoesntHave('stockAllocations')
                    ->orWhereNull('stock_applied_at');
            });

        if ($invoiceId) {
            $query->where('id', $invoiceId);
        }

        $scanned = 0;
        $updatedLines = 0;
        $mirroredAllocations = 0;
        $stampedStock = 0;
        $details = [];

        $query->orderBy('id')->chunkById(50, function ($invoices) use (
            $dryRun,
            &$scanned,
            &$updatedLines,
            &$mirroredAllocations,
            &$stampedStock,
            &$details
        ) {
            foreach ($invoices as $invoice) {
                $scanned++;
                $sources = $this->sourcesForInvoice($invoice);
                if ($sources->isEmpty()) {
                    continue;
                }

                $lineChanges = 0;
                foreach ($invoice->items as $item) {
                    if ($item->warehouse_id) {
                        continue;
                    }

                    $source = $this->matchSourceForItem($sources, $item);
                    if (! $source) {
                        continue;
                    }

                    $sourceItem = $this->matchSourceItem($source, $item);
                    $context = $sourceItem
                        ? $this->purchaseStockReceipt->resolveLineStockContext($source, $sourceItem)
                        : [
                            'warehouse_id' => $source->warehouse_id ? (int) $source->warehouse_id : null,
                            'warehouse_location_id' => null,
                        ];

                    if (! $context['warehouse_id']) {
                        continue;
                    }

                    $lineChanges++;
                    $details[] = [
                        'invoice' => $invoice->invoice_number,
                        'item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'warehouse_id' => $context['warehouse_id'],
                        'warehouse_location_id' => $context['warehouse_location_id'],
                        'source' => $source instanceof SupplierDeliveryNote
                            ? $source->delivery_number
                            : $source->reception_number,
                    ];

                    if (! $dryRun) {
                        $item->update([
                            'warehouse_id' => $context['warehouse_id'],
                            'warehouse_location_id' => $context['warehouse_location_id'],
                        ]);
                    }
                }
                $updatedLines += $lineChanges;

                $needsMirror = ! $invoice->stockAllocations()->exists()
                    && $sources->contains(fn ($source) => $source->stockAllocations()->exists());
                if ($needsMirror) {
                    $mirroredAllocations++;
                }

                $anySourceApplied = $sources->contains(fn ($source) => (bool) $source->stock_applied_at);
                $needsStockStamp = $anySourceApplied && ! $invoice->stock_applied_at;
                if ($needsStockStamp) {
                    $stampedStock++;
                }

                if (! $dryRun && ($lineChanges > 0 || $needsMirror || $needsStockStamp)) {
                    // Relie mouvements + miroir allocations + stock_applied_at (idempotent, 0 entrée stock).
                    $this->purchaseStockReceipt->attachConvertedDocument($sources, $invoice);
                }
            }
        });

        return [
            'scanned' => $scanned,
            'updated_lines' => $updatedLines,
            'mirrored_allocations' => $mirroredAllocations,
            'stamped_stock' => $stampedStock,
            'details' => $details,
        ];
    }

    /**
     * @return Collection<int, SupplierDeliveryNote|Reception>
     */
    protected function sourcesForInvoice(SupplierInvoice $invoice): Collection
    {
        $deliveryNotes = SupplierDeliveryNote::query()
            ->with(['items', 'stockAllocations'])
            ->where('converted_supplier_invoice_id', $invoice->id)
            ->get();

        $receptions = Reception::query()
            ->with(['items', 'stockAllocations'])
            ->where('converted_supplier_invoice_id', $invoice->id)
            ->get();

        return $deliveryNotes->concat($receptions)->values();
    }

    /**
     * @param  Collection<int, SupplierDeliveryNote|Reception>  $sources
     */
    protected function matchSourceForItem(Collection $sources, PurchaseItem $item): SupplierDeliveryNote|Reception|null
    {
        $ref = trim((string) $item->source_document_reference);
        if ($ref !== '') {
            $matched = $sources->first(function ($source) use ($ref) {
                $number = $source instanceof SupplierDeliveryNote
                    ? (string) $source->delivery_number
                    : (string) $source->reception_number;

                return $number !== '' && str_contains($ref, $number);
            });
            if ($matched) {
                return $matched;
            }
        }

        return $sources->first(function ($source) use ($item) {
            return $source->items->contains(function ($sourceItem) use ($item) {
                return (int) $sourceItem->product_id === (int) $item->product_id
                    && (int) ($sourceItem->product_variant_id ?? 0) === (int) ($item->product_variant_id ?? 0);
            }) || $source->stockAllocations->contains(function ($allocation) use ($item) {
                return (int) $allocation->product_id === (int) $item->product_id
                    && (int) ($allocation->product_variant_id ?? 0) === (int) ($item->product_variant_id ?? 0);
            });
        });
    }

    protected function matchSourceItem(SupplierDeliveryNote|Reception $source, PurchaseItem $item): ?PurchaseItem
    {
        return $source->items->first(function ($sourceItem) use ($item) {
            return (int) $sourceItem->product_id === (int) $item->product_id
                && (int) ($sourceItem->product_variant_id ?? 0) === (int) ($item->product_variant_id ?? 0)
                && (int) $sourceItem->quantity === (int) $item->quantity;
        }) ?? $source->items->first(function ($sourceItem) use ($item) {
            return (int) $sourceItem->product_id === (int) $item->product_id
                && (int) ($sourceItem->product_variant_id ?? 0) === (int) ($item->product_variant_id ?? 0);
        });
    }
}
