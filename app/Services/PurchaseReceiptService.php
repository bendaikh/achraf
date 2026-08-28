<?php

namespace App\Services;

use App\Models\PurchaseItem;
use App\Models\PurchaseStockAllocation;
use App\Models\Reception;
use App\Models\SupplierDeliveryNote;
use App\Models\SupplierInvoice;
use App\Models\SupplierPurchaseOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Moteur unique de suivi de réception pour BC / BL / BR / Facture fournisseur.
 * Source de vérité stock : allocations des BR validés (stock_applied_at).
 */
class PurchaseReceiptService
{
    public const STATUS_NONE = 'non_receptionne';

    public const STATUS_PARTIAL = 'partiel';

    public const STATUS_COMPLETE = 'receptionne';

    /**
     * @return Collection<int, array{
     *     line_key: string,
     *     product_id: int,
     *     product_variant_id: ?int,
     *     ref: ?string,
     *     designation: ?string,
     *     document_qty: int,
     *     received: int,
     *     remaining: int,
     *     warehouse: ?string,
     *     status: string,
     *     status_label: string
     * }>
     */
    public function progressForDocument(Model $document): Collection
    {
        $document->loadMissing(['items.product', 'items.variant']);

        $family = $this->collectFamilyIds($document);
        $receivedByLine = $this->receivedQuantities($family, $document);
        $warehouseByLine = $this->warehouseByLine($family, $document);

        return $document->items->map(function (PurchaseItem $item) use ($receivedByLine, $warehouseByLine) {
            $key = $this->lineKey($item->product_id, $item->product_variant_id);
            $documentQty = (int) $item->quantity;
            $received = min($documentQty, (int) $receivedByLine->get($key, 0));
            $remaining = max(0, $documentQty - $received);
            $status = $this->lineStatus($received, $documentQty);

            return [
                'line_key' => $key,
                'product_id' => (int) ($item->product_id ?? 0),
                'product_variant_id' => $item->product_variant_id ? (int) $item->product_variant_id : null,
                'ref' => $item->ref,
                'designation' => $item->designation,
                'document_qty' => $documentQty,
                'received' => $received,
                'remaining' => $remaining,
                'warehouse' => $warehouseByLine->get($key),
                'status' => $status,
                'status_label' => $this->statusLabel($status),
            ];
        });
    }

    public function documentReceptionStatus(Model $document): string
    {
        $progress = $this->progressForDocument($document);

        if ($progress->isEmpty()) {
            return self::STATUS_NONE;
        }

        if ($progress->every(fn (array $row) => $row['status'] === self::STATUS_COMPLETE)) {
            return self::STATUS_COMPLETE;
        }

        if ($progress->contains(fn (array $row) => $row['received'] > 0)) {
            return self::STATUS_PARTIAL;
        }

        return self::STATUS_NONE;
    }

    public function documentReceptionStatusLabel(Model $document): string
    {
        return $this->statusLabel($this->documentReceptionStatus($document));
    }

    /**
     * @return Collection<int, Reception>
     */
    public function linkedReceptions(Model $document): Collection
    {
        $family = $this->collectFamilyIds($document);
        $receptions = $this->familyReceptions($family);

        if ($document instanceof Reception && $document->stock_applied_at && ! $receptions->contains('id', $document->id)) {
            $document->loadMissing(['stockAllocations.warehouse', 'stockAllocations.location']);
            $receptions = $receptions->push($document);
        }

        return $receptions->sortBy('reception_date')->values();
    }

    /**
     * @return array{
     *     supplier_id: int,
     *     supplier_purchase_order_id: ?int,
     *     supplier_delivery_note_id: ?int,
     *     source_supplier_invoice_id: ?int,
     *     warehouse_id: ?int,
     *     stock_location: ?string,
     *     currency: string,
     *     reference: ?string,
     *     items: list<array<string, mixed>>
     * }
     */
    public function prefillFromDocument(Model $document): array
    {
        $document->loadMissing(['items', 'supplier', 'warehouse']);
        $progress = $this->progressForDocument($document);

        $items = [];
        foreach ($document->items as $index => $item) {
            $row = $progress->get($index);
            if (! $row || $row['remaining'] <= 0) {
                continue;
            }

            $items[] = [
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'ref' => $item->ref,
                'designation' => $item->designation,
                'quantity' => $row['remaining'],
                'unit_price' => $item->unit_price,
                'tax_rate' => $item->tax_rate,
                'discount' => $item->discount,
                'discount_type' => $item->discount_type ?? 'fixed',
            ];
        }

        $meta = $this->sourceMeta($document);

        return [
            'supplier_id' => (int) $document->supplier_id,
            'supplier_purchase_order_id' => $meta['supplier_purchase_order_id'],
            'supplier_delivery_note_id' => $meta['supplier_delivery_note_id'],
            'source_supplier_invoice_id' => $meta['source_supplier_invoice_id'],
            'warehouse_id' => $document->warehouse_id ?? null,
            'stock_location' => $document->stock_location ?? null,
            'currency' => $document->currency ?? 'dh - MAD',
            'reference' => $meta['reference'],
            'items' => $items,
        ];
    }

    /**
     * @param  iterable<int, array{product_id?:int|null, product_variant_id?:int|null, quantity:int}>  $incoming
     */
    public function assertNotOverReceiving(Model $document, iterable $incoming): void
    {
        $progress = $this->progressForDocument($document)->keyBy('line_key');

        foreach ($incoming as $row) {
            $key = $this->lineKey($row['product_id'] ?? null, $row['product_variant_id'] ?? null);
            $qty = (int) ($row['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $remaining = (int) ($progress->get($key)['remaining'] ?? 0);
            if ($qty > $remaining) {
                $label = $progress->get($key)['designation'] ?? $key;
                throw new \RuntimeException(
                    'Réception partielle dépassée pour « '.$label.' » : reste à recevoir '.$remaining.', demandé '.$qty.'.'
                );
            }
        }
    }

    /** @deprecated Use assertNotOverReceiving() */
    public function assertNotOverReceivingForOrder(SupplierPurchaseOrder $order, iterable $incoming): void
    {
        $this->assertNotOverReceiving($order, $incoming);
    }

    /** @deprecated Use progressForDocument() */
    public function progressForOrder(SupplierPurchaseOrder $order): Collection
    {
        return $this->progressForDocument($order)->map(fn (array $row) => [
            'product_id' => $row['product_id'],
            'product_variant_id' => $row['product_variant_id'],
            'designation' => $row['designation'],
            'ordered' => $row['document_qty'],
            'received' => $row['received'],
            'remaining' => $row['remaining'],
        ]);
    }

    public function lineKey(?int $productId, ?int $variantId): string
    {
        return ((int) ($productId ?? 0)).':'.((int) ($variantId ?? 0));
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_COMPLETE => 'Réceptionné',
            self::STATUS_PARTIAL => 'Partiellement réceptionné',
            default => 'Non réceptionné',
        };
    }

    public function lineStatus(int $received, int $documentQty): string
    {
        if ($received <= 0) {
            return self::STATUS_NONE;
        }

        if ($received >= $documentQty) {
            return self::STATUS_COMPLETE;
        }

        return self::STATUS_PARTIAL;
    }

    /**
     * @return array{
     *     purchase_order_ids: Collection<int, int>,
     *     delivery_note_ids: Collection<int, int>,
     *     invoice_ids: Collection<int, int>
     * }
     */
    public function collectFamilyIds(Model $document): array
    {
        $poIds = collect();
        $blIds = collect();
        $invoiceIds = collect();

        if ($document instanceof SupplierPurchaseOrder) {
            $poIds->push($document->id);
        } elseif ($document instanceof SupplierDeliveryNote) {
            $blIds->push($document->id);
            if ($document->supplier_purchase_order_id) {
                $poIds->push($document->supplier_purchase_order_id);
            }
            if ($document->converted_supplier_invoice_id) {
                $invoiceIds->push($document->converted_supplier_invoice_id);
            }
        } elseif ($document instanceof Reception) {
            if ($document->supplier_purchase_order_id) {
                $poIds->push($document->supplier_purchase_order_id);
            }
            if ($document->supplier_delivery_note_id) {
                $blIds->push($document->supplier_delivery_note_id);
            }
            if ($document->source_supplier_invoice_id) {
                $invoiceIds->push($document->source_supplier_invoice_id);
            }
            if ($document->converted_supplier_invoice_id) {
                $invoiceIds->push($document->converted_supplier_invoice_id);
            }
        } elseif ($document instanceof SupplierInvoice) {
            $invoiceIds->push($document->id);
            if ($document->supplier_purchase_order_id) {
                $poIds->push($document->supplier_purchase_order_id);
            }
        }

        if ($poIds->isNotEmpty()) {
            $blIds = $blIds->merge(
                SupplierDeliveryNote::query()->whereIn('supplier_purchase_order_id', $poIds)->pluck('id')
            );
            $invoiceIds = $invoiceIds->merge(
                SupplierInvoice::query()->whereIn('supplier_purchase_order_id', $poIds)->pluck('id')
            );
        }

        if ($blIds->isNotEmpty()) {
            $invoiceIds = $invoiceIds->merge(
                SupplierDeliveryNote::query()
                    ->whereIn('id', $blIds)
                    ->whereNotNull('converted_supplier_invoice_id')
                    ->pluck('converted_supplier_invoice_id')
            );
            $invoiceIds = $invoiceIds->merge(
                Reception::query()
                    ->whereIn('supplier_delivery_note_id', $blIds)
                    ->whereNotNull('converted_supplier_invoice_id')
                    ->pluck('converted_supplier_invoice_id')
            );
        }

        if ($invoiceIds->isNotEmpty()) {
            $blIds = $blIds->merge(
                SupplierDeliveryNote::query()
                    ->whereIn('converted_supplier_invoice_id', $invoiceIds)
                    ->pluck('id')
            );
            $poIds = $poIds->merge(
                SupplierInvoice::query()->whereIn('id', $invoiceIds)->pluck('supplier_purchase_order_id')
            )->merge(
                SupplierDeliveryNote::query()->whereIn('converted_supplier_invoice_id', $invoiceIds)->pluck('supplier_purchase_order_id')
            )->merge(
                Reception::query()->whereIn('converted_supplier_invoice_id', $invoiceIds)->pluck('supplier_purchase_order_id')
            );
        }

        return [
            'purchase_order_ids' => $poIds->unique()->filter()->values(),
            'delivery_note_ids' => $blIds->unique()->filter()->values(),
            'invoice_ids' => $invoiceIds->unique()->filter()->values(),
        ];
    }

    /**
     * @param  array{purchase_order_ids: Collection<int, int>, delivery_note_ids: Collection<int, int>, invoice_ids: Collection<int, int>}  $family
     */
    protected function familyReceptions(array $family): Collection
    {
        if ($family['purchase_order_ids']->isEmpty()
            && $family['delivery_note_ids']->isEmpty()
            && $family['invoice_ids']->isEmpty()) {
            return collect();
        }

        $query = Reception::query()->whereNotNull('stock_applied_at');

        $query->where(function ($q) use ($family) {
            $hasCondition = false;

            if ($family['purchase_order_ids']->isNotEmpty()) {
                $q->whereIn('supplier_purchase_order_id', $family['purchase_order_ids']);
                $hasCondition = true;
            }

            if ($family['delivery_note_ids']->isNotEmpty()) {
                $hasCondition
                    ? $q->orWhereIn('supplier_delivery_note_id', $family['delivery_note_ids'])
                    : $q->whereIn('supplier_delivery_note_id', $family['delivery_note_ids']);
                $hasCondition = true;
            }

            if ($family['invoice_ids']->isNotEmpty()) {
                $method = $hasCondition ? 'orWhere' : 'where';
                $q->{$method}(function ($sub) use ($family) {
                    $sub->whereIn('source_supplier_invoice_id', $family['invoice_ids'])
                        ->orWhereIn('converted_supplier_invoice_id', $family['invoice_ids']);
                });
            }
        });

        return $query
            ->with(['stockAllocations.warehouse', 'stockAllocations.location'])
            ->orderBy('reception_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array{purchase_order_ids: Collection<int, int>, delivery_note_ids: Collection<int, int>, invoice_ids: Collection<int, int>}  $family
     */
    protected function receivedQuantities(array $family, ?Model $document = null): Collection
    {
        $receptionIds = $this->familyReceptions($family)->pluck('id');

        if ($receptionIds->isEmpty() && $document instanceof Reception && $document->stock_applied_at) {
            $receptionIds = collect([(int) $document->id]);
        }

        if ($receptionIds->isEmpty()) {
            return collect();
        }

        return PurchaseStockAllocation::query()
            ->where('allocatable_type', Reception::class)
            ->whereIn('allocatable_id', $receptionIds)
            ->get()
            ->groupBy(fn (PurchaseStockAllocation $a) => $this->lineKey($a->product_id, $a->product_variant_id))
            ->map(fn (Collection $rows) => (int) $rows->sum('quantity'));
    }

    /**
     * @param  array{purchase_order_ids: Collection<int, int>, delivery_note_ids: Collection<int, int>, invoice_ids: Collection<int, int>}  $family
     */
    protected function warehouseByLine(array $family, ?Model $document = null): Collection
    {
        $receptions = $this->familyReceptions($family);

        if ($receptions->isEmpty() && $document instanceof Reception && $document->stock_applied_at) {
            $document->loadMissing(['stockAllocations.warehouse']);
            $receptions = collect([$document]);
        }
        $byLine = collect();

        foreach ($receptions->reverse() as $reception) {
            foreach ($reception->stockAllocations as $allocation) {
                $key = $this->lineKey($allocation->product_id, $allocation->product_variant_id);
                if (! $byLine->has($key)) {
                    $byLine->put($key, $allocation->warehouse?->name);
                }
            }
        }

        return $byLine;
    }

    /**
     * @return array{supplier_purchase_order_id: ?int, supplier_delivery_note_id: ?int, source_supplier_invoice_id: ?int, reference: ?string}
     */
    protected function sourceMeta(Model $document): array
    {
        if ($document instanceof SupplierPurchaseOrder) {
            return [
                'supplier_purchase_order_id' => (int) $document->id,
                'supplier_delivery_note_id' => null,
                'source_supplier_invoice_id' => null,
                'reference' => $document->order_number,
            ];
        }

        if ($document instanceof SupplierDeliveryNote) {
            return [
                'supplier_purchase_order_id' => $document->supplier_purchase_order_id,
                'supplier_delivery_note_id' => (int) $document->id,
                'source_supplier_invoice_id' => null,
                'reference' => $document->delivery_number,
            ];
        }

        if ($document instanceof SupplierInvoice) {
            return [
                'supplier_purchase_order_id' => $document->supplier_purchase_order_id,
                'supplier_delivery_note_id' => null,
                'source_supplier_invoice_id' => (int) $document->id,
                'reference' => $document->invoice_number,
            ];
        }

        return [
            'supplier_purchase_order_id' => null,
            'supplier_delivery_note_id' => null,
            'source_supplier_invoice_id' => null,
            'reference' => null,
        ];
    }
}
