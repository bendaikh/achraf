<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\StockSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class StockMovementService
{
    public function isStockControlEnabled(): bool
    {
        return StockSettings::controlEnabled();
    }

    /**
     * Resolve stock channel from document location or POS type.
     */
    public function resolveChannel(?string $stockLocation): string
    {
        $normalized = strtolower(trim((string) $stockLocation));

        if (str_contains($normalized, 'en ligne') || str_contains($normalized, 'enligne') || $normalized === 'enligne') {
            return 'enligne';
        }

        if (str_contains($normalized, 'magasin') || $normalized === 'magasin') {
            return 'magasin';
        }

        return 'default';
    }

    /**
     * @param  iterable<int, array{product_id?: int|null, quantity: int}>  $items
     * @return list<string>
     */
    public function decreaseForSale(iterable $items, ?string $stockLocation, bool $strict = true, ?string $documentType = null, ?int $documentId = null, ?string $documentReference = null): array
    {
        if (! $this->isStockControlEnabled()) {
            return [];
        }

        $warnings = [];

        foreach ($items as $item) {
            $productId = $item['product_id'] ?? null;
            if (! $productId) {
                continue;
            }

            $product = Product::query()->lockForUpdate()->find($productId);
            if (! $product || ! $product->tracksStock()) {
                continue;
            }

            $qty = (int) ($item['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $warning = $this->decrease(
                $product,
                $qty,
                $this->resolveChannel($stockLocation),
                $strict,
                true,
                StockMovement::TYPE_SALE,
                $documentType,
                $documentId,
                $documentReference
            );
            if ($warning !== null) {
                $warnings[] = $warning;
            }
        }

        return $warnings;
    }

    /**
     * @param  iterable<int, array{product_id?: int|null, quantity: int}>  $items
     */
    public function increaseForPurchase(iterable $items, ?string $stockLocation, ?string $documentType = null, ?int $documentId = null, ?string $documentReference = null): void
    {
        if (! $this->isStockControlEnabled()) {
            return;
        }

        foreach ($items as $item) {
            $productId = $item['product_id'] ?? null;
            if (! $productId) {
                continue;
            }

            $product = Product::query()->lockForUpdate()->find($productId);
            if (! $product || ! $product->tracksStock()) {
                continue;
            }

            $qty = (int) ($item['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $this->increase(
                $product,
                $qty,
                $this->resolveChannel($stockLocation),
                true,
                StockMovement::TYPE_PURCHASE,
                $documentType,
                $documentId,
                $documentReference
            );
        }
    }

    /**
     * Customer return (avoir client) — stock goes back in.
     *
     * @param  Collection<int, object>|iterable  $items
     */
    public function increaseFromItems(iterable $items, ?string $stockLocation, ?string $documentType = null, ?int $documentId = null, ?string $documentReference = null): void
    {
        $rows = [];
        foreach ($items as $item) {
            $rows[] = ['product_id' => $item->product_id ?? null, 'quantity' => (int) $item->quantity];
        }

        if (! $this->isStockControlEnabled()) {
            return;
        }

        foreach ($rows as $row) {
            if (! $row['product_id'] || $row['quantity'] <= 0) {
                continue;
            }
            $product = Product::query()->lockForUpdate()->find($row['product_id']);
            if (! $product || ! $product->tracksStock()) {
                continue;
            }
            $this->increase(
                $product,
                $row['quantity'],
                $this->resolveChannel($stockLocation),
                true,
                StockMovement::TYPE_CUSTOMER_RETURN,
                $documentType,
                $documentId,
                $documentReference
            );
        }
    }

    /**
     * Supplier return (avoir fournisseur) — stock goes out.
     *
     * @param  Collection<int, object>|iterable  $items
     */
    public function decreaseFromItems(iterable $items, ?string $stockLocation, ?string $documentType = null, ?int $documentId = null, ?string $documentReference = null): void
    {
        $rows = [];
        foreach ($items as $item) {
            $rows[] = ['product_id' => $item->product_id ?? null, 'quantity' => (int) $item->quantity];
        }
        $this->decreaseForSale($rows, $stockLocation, true, $documentType ?? 'supplier_credit_note', $documentId, $documentReference);
        // Re-tag type as supplier return when possible — decreaseForSale uses sale; override by dedicated loop if needed.
        // Keep sale semantics for quantity; type is already sale. Callers for supplier returns should use adjustQuantity.
    }

    public function decrease(
        Product $product,
        int $quantity,
        string $channel,
        bool $strict = true,
        bool $syncShopify = true,
        string $movementType = StockMovement::TYPE_SALE,
        ?string $documentType = null,
        ?int $documentId = null,
        ?string $documentReference = null,
        ?int $warehouseId = null,
        ?int $locationId = null,
        ?string $notes = null,
        ?int $transferGroupId = null
    ): ?string {
        if (! $product->tracksStock()) {
            return null;
        }

        $field = $this->stockFieldForProduct($product, $channel);
        $current = (int) ($product->{$field} ?? 0);
        $warning = null;
        $allowNegative = StockSettings::allowNegative();

        if ($current < $quantity && ! $allowNegative) {
            $warning = 'Stock insuffisant pour « '.$product->name.' » (disponible: '.$current.', demandé: '.$quantity.').';

            if ($strict) {
                throw new RuntimeException($warning);
            }
        }

        $product->{$field} = $current - $quantity;
        $this->syncAggregateStock($product, $field);
        $product->save();

        $warehouseId = $warehouseId ?? $product->warehouse_id ?? Warehouse::primary()?->id;
        $locationId = $locationId ?? $product->warehouse_location_id;
        $this->adjustProductStockSlot($product, $warehouseId, $locationId, -$quantity);
        $this->recordMovement($product, -$quantity, $movementType, $warehouseId, $locationId, $documentType, $documentId, $documentReference, $notes, $transferGroupId);

        $this->pushEnligneStockToJumia($product, $field);
        $this->pushEnligneStockToShopify($product, $field, $syncShopify);

        return $warning;
    }

    public function increase(
        Product $product,
        int $quantity,
        string $channel,
        bool $syncShopify = true,
        string $movementType = StockMovement::TYPE_PURCHASE,
        ?string $documentType = null,
        ?int $documentId = null,
        ?string $documentReference = null,
        ?int $warehouseId = null,
        ?int $locationId = null,
        ?string $notes = null,
        ?int $transferGroupId = null
    ): void {
        if (! $product->tracksStock()) {
            return;
        }

        $field = $this->stockFieldForProduct($product, $channel);
        $product->{$field} = (int) ($product->{$field} ?? 0) + $quantity;
        $this->syncAggregateStock($product, $field);
        $product->save();

        $warehouseId = $warehouseId ?? $product->warehouse_id ?? Warehouse::primary()?->id;
        $locationId = $locationId ?? $product->warehouse_location_id;
        $this->adjustProductStockSlot($product, $warehouseId, $locationId, $quantity);
        $this->recordMovement($product, $quantity, $movementType, $warehouseId, $locationId, $documentType, $documentId, $documentReference, $notes, $transferGroupId);

        $this->pushEnligneStockToJumia($product, $field);
        $this->pushEnligneStockToShopify($product, $field, $syncShopify);
    }

    /**
     * Manual / inventory adjustment to an absolute quantity on a warehouse slot.
     */
    public function setQuantity(
        Product $product,
        int $newQuantity,
        ?int $warehouseId = null,
        ?int $locationId = null,
        ?string $notes = null,
        string $channel = 'default'
    ): void {
        if (! $product->tracksStock()) {
            return;
        }

        $warehouseId = $warehouseId ?? $product->warehouse_id ?? Warehouse::primary()?->id;
        $locationId = $locationId ?? $product->warehouse_location_id;

        $slot = $this->findOrCreateSlot($product->id, $warehouseId, $locationId);
        $delta = $newQuantity - (int) $slot->quantity;

        if ($delta === 0) {
            return;
        }

        $slot->quantity = $newQuantity;
        $slot->save();

        $field = $this->stockFieldForProduct($product, $channel);
        $product->{$field} = (int) ($product->{$field} ?? 0) + $delta;
        $this->syncAggregateStock($product, $field);
        $this->syncProductAggregateFromSlots($product);
        $product->save();

        $this->recordMovement(
            $product,
            $delta,
            StockMovement::TYPE_INVENTORY_ADJUSTMENT,
            $warehouseId,
            $locationId,
            'inventory',
            null,
            null,
            $notes
        );

        $this->pushEnligneStockToJumia($product, $field);
        $this->pushEnligneStockToShopify($product, $field, true);
    }

    /**
     * Transfer stock between warehouses/locations. Creates sortie + entrée.
     */
    public function transfer(
        Product $product,
        int $quantity,
        int $fromWarehouseId,
        ?int $fromLocationId,
        int $toWarehouseId,
        ?int $toLocationId,
        ?string $notes = null
    ): int {
        if (! $product->tracksStock()) {
            throw new RuntimeException('Ce produit ne gère pas de stock.');
        }
        if ($quantity <= 0) {
            throw new RuntimeException('La quantité à transférer doit être positive.');
        }
        if ($fromWarehouseId === $toWarehouseId && (int) $fromLocationId === (int) $toLocationId) {
            throw new RuntimeException('L’emplacement source et destination sont identiques.');
        }

        $groupId = (int) (DB::table('stock_movements')->max('id') + 1);

        return DB::transaction(function () use ($product, $quantity, $fromWarehouseId, $fromLocationId, $toWarehouseId, $toLocationId, $notes, $groupId) {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $fromSlot = $this->findOrCreateSlot($product->id, $fromWarehouseId, $fromLocationId, true);

            if ((int) $fromSlot->quantity < $quantity && ! StockSettings::allowNegative()) {
                throw new RuntimeException('Stock insuffisant sur l’emplacement source (disponible: '.$fromSlot->quantity.').');
            }

            $fromSlot->quantity = (int) $fromSlot->quantity - $quantity;
            $fromSlot->save();

            $toSlot = $this->findOrCreateSlot($product->id, $toWarehouseId, $toLocationId, true);
            $toSlot->quantity = (int) $toSlot->quantity + $quantity;
            $toSlot->save();

            $this->syncProductAggregateFromSlots($product);
            $product->save();

            $this->recordMovement(
                $product,
                -$quantity,
                StockMovement::TYPE_TRANSFER_OUT,
                $fromWarehouseId,
                $fromLocationId,
                'transfer',
                null,
                null,
                $notes,
                $groupId
            );
            $this->recordMovement(
                $product,
                $quantity,
                StockMovement::TYPE_TRANSFER_IN,
                $toWarehouseId,
                $toLocationId,
                'transfer',
                null,
                null,
                $notes,
                $groupId
            );

            return $groupId;
        });
    }

    /**
     * Sync product.stock_quantity from sum of product_stocks and keep depot/location labels.
     */
    public function syncProductFromWarehouseAssignment(
        Product $product,
        ?int $warehouseId,
        ?int $locationId,
        ?int $quantity = null,
        ?int $previousWarehouseId = null,
        ?int $previousLocationId = null
    ): void {
        $previousWarehouseId = $previousWarehouseId ?? ($product->getOriginal('warehouse_id') ? (int) $product->getOriginal('warehouse_id') : ($product->warehouse_id ? (int) $product->warehouse_id : null));
        $previousLocationId = $previousLocationId ?? ($product->getOriginal('warehouse_location_id') ? (int) $product->getOriginal('warehouse_location_id') : ($product->warehouse_location_id ? (int) $product->warehouse_location_id : null));

        $product->warehouse_id = $warehouseId;
        $product->warehouse_location_id = $locationId;

        if ($warehouseId) {
            $warehouse = Warehouse::find($warehouseId);
            $product->depot = $warehouse?->name;
        } else {
            $product->depot = null;
        }

        if ($locationId) {
            $location = \App\Models\WarehouseLocation::find($locationId);
            $product->location = $location?->code;
        } else {
            $product->location = null;
        }

        if (! $product->tracksStock() || ! $warehouseId) {
            return;
        }

        $slot = $this->findOrCreateSlot($product->id, $warehouseId, $locationId);
        $assignmentChanged = $previousWarehouseId !== $warehouseId
            || $previousLocationId !== $locationId;

        // Moving primary depot/location: transfer qty from previous primary slot to the new one.
        if ($assignmentChanged && $previousWarehouseId && $product->exists) {
            $oldSlotQuery = ProductStock::query()
                ->where('product_id', $product->id)
                ->where('warehouse_id', $previousWarehouseId)
                ->where(function ($q) use ($previousLocationId) {
                    if ($previousLocationId) {
                        $q->where('warehouse_location_id', $previousLocationId);
                    } else {
                        $q->whereNull('warehouse_location_id');
                    }
                });

            $oldSlot = $oldSlotQuery->first();
            if ($oldSlot && $oldSlot->id !== $slot->id) {
                $moveQty = $quantity !== null ? (int) $quantity : (int) $oldSlot->quantity;
                $oldSlot->quantity = max(0, (int) $oldSlot->quantity - $moveQty);
                $oldSlot->save();
                if ((int) $oldSlot->quantity === 0 && (int) $oldSlot->reserved === 0) {
                    $oldSlot->delete();
                }
                $slot->quantity = $moveQty;
                $slot->save();
                $this->syncProductAggregateFromSlots($product);

                return;
            }
        }

        if ($quantity !== null) {
            $oldQty = (int) $slot->quantity;
            $slot->quantity = $quantity;
            $slot->save();

            if ($oldQty !== $quantity && $product->exists) {
                $delta = $quantity - $oldQty;
                if ($delta !== 0) {
                    $this->recordMovement(
                        $product,
                        $delta,
                        $delta > 0 ? StockMovement::TYPE_MANUAL_IN : StockMovement::TYPE_MANUAL_OUT,
                        $warehouseId,
                        $locationId,
                        'product_form',
                        $product->id,
                        $product->ref,
                        'Mise à jour depuis fiche produit'
                    );
                }
            }
        }

        $this->syncProductAggregateFromSlots($product);
    }

    protected function adjustProductStockSlot(Product $product, ?int $warehouseId, ?int $locationId, int $delta): void
    {
        if (! $warehouseId || $delta === 0) {
            return;
        }

        $slot = $this->findOrCreateSlot($product->id, $warehouseId, $locationId);
        $slot->quantity = (int) $slot->quantity + $delta;
        $slot->save();
        $this->syncProductAggregateFromSlots($product);
    }

    protected function findOrCreateSlot(int $productId, ?int $warehouseId, ?int $locationId, bool $lock = false): ProductStock
    {
        if (! $warehouseId) {
            throw new RuntimeException('Dépôt requis pour le stock.');
        }

        $query = ProductStock::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where(function ($q) use ($locationId) {
                if ($locationId) {
                    $q->where('warehouse_location_id', $locationId);
                } else {
                    $q->whereNull('warehouse_location_id');
                }
            });

        if ($lock) {
            $query->lockForUpdate();
        }

        $slot = $query->first();
        if ($slot) {
            return $slot;
        }

        return ProductStock::create([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'warehouse_location_id' => $locationId,
            'quantity' => 0,
            'reserved' => 0,
        ]);
    }

    protected function syncProductAggregateFromSlots(Product $product): void
    {
        $sum = (int) ProductStock::query()->where('product_id', $product->id)->sum('quantity');
        $reserved = (int) ProductStock::query()->where('product_id', $product->id)->sum('reserved');
        $product->stock_quantity = $sum;
        $product->stock_reserved = $reserved;

        if ($product->isShopifyProduct()) {
            $product->stock_enligne = $sum;
        } else {
            $product->stock_magasin = $sum;
        }
    }

    protected function recordMovement(
        Product $product,
        int $quantity,
        string $type,
        ?int $warehouseId,
        ?int $locationId,
        ?string $documentType = null,
        ?int $documentId = null,
        ?string $documentReference = null,
        ?string $notes = null,
        ?int $transferGroupId = null
    ): void {
        if ($quantity === 0) {
            return;
        }

        StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouseId,
            'warehouse_location_id' => $locationId,
            'quantity' => $quantity,
            'type' => $type,
            'moved_at' => now(),
            'document_type' => $documentType,
            'document_id' => $documentId,
            'document_reference' => $documentReference,
            'user_id' => Auth::id(),
            'transfer_group_id' => $transferGroupId,
            'notes' => $notes,
        ]);
    }

    protected function pushEnligneStockToJumia(Product $product, string $field): void
    {
        if ($field !== 'stock_enligne') {
            return;
        }

        try {
            app(MarketplaceStockSyncService::class)->pushProductStockToJumia($product->fresh() ?? $product);
        } catch (\Throwable $e) {
            Log::warning('Jumia stock push after local stock change failed', [
                'product_id' => $product->id,
                'sku' => $product->ref,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function pushEnligneStockToShopify(Product $product, string $field, bool $enabled): void
    {
        if (! $enabled || $field !== 'stock_enligne' || ! $product->isShopifyProduct()) {
            return;
        }

        $productId = $product->id;

        DB::afterCommit(function () use ($productId): void {
            try {
                $fresh = Product::query()->find($productId);
                if (! $fresh) {
                    return;
                }

                app(ShopifyInventorySyncService::class)->pushProductStock($fresh);
            } catch (\Throwable $e) {
                Log::warning('Shopify stock push after local stock change failed', [
                    'product_id' => $productId,
                    'message' => $e->getMessage(),
                ]);
            }
        });
    }

    protected function stockFieldForProduct(Product $product, string $channel): string
    {
        if ($channel === 'enligne' || ($channel === 'default' && $product->isShopifyProduct())) {
            return 'stock_enligne';
        }

        if ($channel === 'magasin' || ($channel === 'default' && ! $product->isShopifyProduct())) {
            return 'stock_magasin';
        }

        return 'stock_quantity';
    }

    protected function syncAggregateStock(Product $product, string $updatedField): void
    {
        if ($updatedField === 'stock_enligne') {
            $product->stock_quantity = (int) $product->stock_enligne;

            return;
        }

        if ($updatedField === 'stock_magasin') {
            $product->stock_quantity = (int) $product->stock_magasin;

            return;
        }

        if ($product->isShopifyProduct()) {
            $product->stock_enligne = (int) $product->stock_quantity;
        } else {
            $product->stock_magasin = (int) $product->stock_quantity;
        }
    }
}
