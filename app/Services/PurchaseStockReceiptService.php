<?php

namespace App\Services;

use App\Models\Reception;
use App\Models\StockMovement;
use App\Models\StockMovementDocument;
use App\Models\SupplierDeliveryNote;
use App\Models\SupplierInvoice;
use App\Models\SupplierPurchaseOrder;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class PurchaseStockReceiptService
{
    public function __construct(
        protected StockMovementService $stockMovement,
    ) {}

    public function resolveDefaultWarehouse(?int $warehouseId, ?string $stockLocation): Warehouse
    {
        $warehouse = $warehouseId
            ? Warehouse::find($warehouseId)
            : (Warehouse::findByStockLocation($stockLocation) ?? Warehouse::fulfillmentWarehouse());

        if (! $warehouse) {
            throw new \RuntimeException('Veuillez choisir un dépôt de réception.');
        }

        return $warehouse;
    }

    /**
     * @return array<string, mixed>
     */
    public function validationRules(): array
    {
        return [
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'stock_location' => 'nullable|string',
            'supplier_purchase_order_id' => 'nullable|exists:supplier_purchase_orders,id',
            'items.*.allocations' => 'nullable|array',
            'items.*.allocations.*.warehouse_id' => 'nullable|exists:warehouses,id',
            'items.*.allocations.*.warehouse_location_id' => 'nullable|exists:warehouse_locations,id',
            'items.*.allocations.*.quantity' => 'nullable|integer|min:0',
            'items.*.warehouse_id' => 'nullable|exists:warehouses,id',
            'items.*.warehouse_location_id' => 'nullable|exists:warehouse_locations,id',
        ];
    }

    /**
     * Entrée en stock unique (idéalement via BR). Idempotente via stock_applied_at.
     *
     * @param  list<array<string, mixed>>  $items
     */
    public function applyIfNeeded(Model $document, array $items, Warehouse $defaultWarehouse): void
    {
        if ($document->getAttribute('stock_applied_at')) {
            return;
        }

        $meta = $this->documentMeta($document);
        $poId = $document->getAttribute('supplier_purchase_order_id');

        $supplierName = $document->supplier?->name;

        foreach ($items as $item) {
            $productId = $item['product_id'] ?? null;
            $variantId = $item['product_variant_id'] ?? null;
            $qty = (int) ($item['quantity'] ?? 0);
            if (! $productId || $qty <= 0) {
                continue;
            }

            $lineWarehouseId = isset($item['warehouse_id']) && $item['warehouse_id']
                ? (int) $item['warehouse_id']
                : (int) $defaultWarehouse->id;
            $lineLocationId = isset($item['warehouse_location_id']) && $item['warehouse_location_id']
                ? (int) $item['warehouse_location_id']
                : null;

            $allocations = $this->normalizeAllocations(
                $item['allocations'] ?? [],
                $qty,
                $lineWarehouseId,
                $lineLocationId
            );

            foreach ($allocations as $allocation) {
                if ($allocation['quantity'] <= 0) {
                    continue;
                }

                $document->stockAllocations()->create([
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'warehouse_id' => $allocation['warehouse_id'],
                    'warehouse_location_id' => $allocation['warehouse_location_id'],
                    'quantity' => $allocation['quantity'],
                ]);

                $warehouse = Warehouse::find($allocation['warehouse_id']);
                $this->stockMovement->increaseForPurchase(
                    [[
                        'product_id' => $productId,
                        'product_variant_id' => $variantId,
                        'quantity' => $allocation['quantity'],
                        'warehouse_id' => $allocation['warehouse_id'],
                        'warehouse_location_id' => $allocation['warehouse_location_id'],
                        'notes' => $supplierName ? 'Fournisseur '.$supplierName : null,
                    ]],
                    $warehouse?->name,
                    $meta['type'],
                    (int) $document->getKey(),
                    $meta['reference'],
                    $allocation['warehouse_id']
                );
            }
        }

        $document->update(['stock_applied_at' => now()]);
        $this->linkMovementsToDocument($this->movementsForDocument($meta['type'], (int) $document->getKey()), $meta['type'], (int) $document->getKey(), $meta['reference']);

        if ($poId) {
            $po = SupplierPurchaseOrder::find($poId);
            if ($po) {
                $this->linkMovementsToDocument(
                    $this->movementsForDocument($meta['type'], (int) $document->getKey()),
                    'supplier_purchase_order',
                    (int) $po->id,
                    $po->order_number
                );
            }
        }
    }

    /**
     * Relie un document commercial (facture, etc.) au mouvement déjà créé, sans recréer de stock.
     *
     * @param  Collection<int, Model>  $sources
     */
    public function attachConvertedDocument(Collection $sources, Model $target): void
    {
        $targetMeta = $this->documentMeta($target);
        $movements = collect();
        $anyApplied = false;

        foreach ($sources as $source) {
            $sourceMeta = $this->documentMeta($source);
            $movements = $movements->merge($this->movementsForDocument($sourceMeta['type'], (int) $source->getKey()));
            if ($source->getAttribute('stock_applied_at')) {
                $anyApplied = true;
            }
        }

        $this->linkMovementsToDocument($movements, $targetMeta['type'], (int) $target->getKey(), $targetMeta['reference']);

        foreach ($movements as $movement) {
            $refs = collect(explode(' | ', (string) $movement->document_reference))
                ->filter()
                ->push($targetMeta['reference'])
                ->unique()
                ->implode(' | ');
            $movement->update(['document_reference' => $refs]);
        }

        // Conversion ne recrée pas de stock : si l’entrée a déjà eu lieu (BR),
        // marquer la facture comme « Stock réceptionné » pour bloquer une 2ᵉ entrée.
        if ($anyApplied && ! $target->getAttribute('stock_applied_at')) {
            $target->update(['stock_applied_at' => now()]);
        }
    }

    /**
     * @param  mixed  $raw
     * @return list<array{warehouse_id:int, warehouse_location_id:?int, quantity:int}>
     */
    public function normalizeAllocations(mixed $raw, int $lineQty, int $defaultWarehouseId, ?int $defaultLocationId = null): array
    {
        $rows = [];
        if (is_array($raw)) {
            foreach ($raw as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $wid = (int) ($row['warehouse_id'] ?? 0);
                $lid = isset($row['warehouse_location_id']) && $row['warehouse_location_id']
                    ? (int) $row['warehouse_location_id']
                    : null;
                $q = (int) ($row['quantity'] ?? 0);
                if ($wid > 0 && $q > 0) {
                    $rows[] = [
                        'warehouse_id' => $wid,
                        'warehouse_location_id' => $lid,
                        'quantity' => $q,
                    ];
                }
            }
        }

        $allocated = array_sum(array_column($rows, 'quantity'));
        if ($allocated === 0) {
            return [[
                'warehouse_id' => $defaultWarehouseId,
                'warehouse_location_id' => $defaultLocationId,
                'quantity' => $lineQty,
            ]];
        }

        if ($allocated !== $lineQty) {
            throw new \RuntimeException(
                'La répartition des dépôts ('.$allocated.') doit être égale à la quantité reçue ('.$lineQty.').'
            );
        }

        return $rows;
    }

    /**
     * @return array{type:string, reference:string}
     */
    public function documentMeta(Model $document): array
    {
        if ($document instanceof Reception) {
            return ['type' => 'reception', 'reference' => (string) $document->reception_number];
        }
        if ($document instanceof SupplierDeliveryNote) {
            return ['type' => 'supplier_delivery_note', 'reference' => (string) $document->delivery_number];
        }
        if ($document instanceof SupplierInvoice) {
            return ['type' => 'supplier_invoice', 'reference' => (string) $document->invoice_number];
        }

        throw new \InvalidArgumentException('Document d’achat non pris en charge pour le stock.');
    }

    /**
     * @return Collection<int, StockMovement>
     */
    protected function movementsForDocument(string $type, int $id): Collection
    {
        $direct = StockMovement::query()
            ->where('type', StockMovement::TYPE_PURCHASE)
            ->where('document_type', $type)
            ->where('document_id', $id)
            ->get();

        $linkedIds = StockMovementDocument::query()
            ->where('document_type', $type)
            ->where('document_id', $id)
            ->pluck('stock_movement_id');

        $linked = $linkedIds->isEmpty()
            ? collect()
            : StockMovement::query()->whereIn('id', $linkedIds)->get();

        return $direct->merge($linked)->unique('id');
    }

    /**
     * @param  Collection<int, StockMovement>|iterable<int, StockMovement>  $movements
     */
    protected function linkMovementsToDocument(iterable $movements, string $type, int $id, string $reference): void
    {
        foreach ($movements as $movement) {
            StockMovementDocument::query()->firstOrCreate(
                [
                    'stock_movement_id' => $movement->id,
                    'document_type' => $type,
                    'document_id' => $id,
                ],
                ['document_reference' => $reference]
            );
        }
    }
}
