<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Support\StockSettings;
use Carbon\Carbon;
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
     * @param  iterable<int, array{product_id?: int|null, product_variant_id?: int|null, quantity: int}>  $items
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
                $documentReference,
                null,
                null,
                null,
                null,
                isset($item['product_variant_id']) ? (int) $item['product_variant_id'] : null
            );
            if ($warning !== null) {
                $warnings[] = $warning;
            }
        }

        return $warnings;
    }

    /**
     * @param  iterable<int, array{product_id?: int|null, product_variant_id?: int|null, quantity: int}>  $items
     */
    public function increaseForPurchase(
        iterable $items,
        ?string $stockLocation,
        ?string $documentType = null,
        ?int $documentId = null,
        ?string $documentReference = null,
        ?int $warehouseId = null
    ): void {
        if (! $this->isStockControlEnabled()) {
            return;
        }

        foreach ($items as $item) {
            $row = is_array($item) ? $item : [
                'product_id' => $item->product_id ?? null,
                'product_variant_id' => $item->product_variant_id ?? null,
                'quantity' => $item->quantity ?? 0,
                'warehouse_id' => $item->warehouse_id ?? null,
            ];
            $productId = $row['product_id'] ?? null;
            if (! $productId) {
                continue;
            }

            $product = Product::query()->lockForUpdate()->find($productId);
            if (! $product || ! $product->tracksStock()) {
                continue;
            }

            $qty = (int) ($row['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $itemWarehouseId = isset($row['warehouse_id']) && $row['warehouse_id']
                ? (int) $row['warehouse_id']
                : $warehouseId;
            $itemLocationId = isset($row['warehouse_location_id']) && $row['warehouse_location_id']
                ? (int) $row['warehouse_location_id']
                : (isset($row['location_id']) && $row['location_id'] ? (int) $row['location_id'] : null);

            $this->increase(
                $product,
                $qty,
                $this->resolveChannel($stockLocation),
                true,
                StockMovement::TYPE_PURCHASE,
                $documentType,
                $documentId,
                $documentReference,
                $itemWarehouseId,
                $itemLocationId,
                $row['notes'] ?? null,
                null,
                isset($row['product_variant_id']) ? (int) $row['product_variant_id'] : null
            );
        }
    }

    /**
     * Align the online (Shopify) warehouse slot with an external absolute quantity.
     * Does not push back to Shopify (avoids webhook loops).
     */
    public function syncOnlineWarehouseFromExternal(Product $product, int $available, ?string $notes = null, ?int $variantId = null): void
    {
        if (! $product->tracksStock()) {
            return;
        }

        $warehouse = Warehouse::onlineWarehouse();
        if (! $warehouse) {
            if ($variantId) {
                return;
            }

            $product->stock_enligne = max(0, $available);
            $product->stock_quantity = max(0, $available);
            $product->save();

            return;
        }

        $available = max(0, $available);
        $resolvedVariantId = $this->resolveVariantIdForProduct($product, $variantId, false);
        $slot = $this->findOrCreateSlot($product->id, (int) $warehouse->id, null, true, $resolvedVariantId);
        $before = (int) $slot->quantity;
        $delta = $available - $before;

        if ($delta === 0) {
            $this->syncProductAggregateFromSlots($product);
            if (! $product->warehouse_id) {
                $product->warehouse_id = $warehouse->id;
            }
            $product->save();

            return;
        }

        $slot->quantity = $available;
        $slot->save();

        if (! $product->warehouse_id) {
            $product->warehouse_id = $warehouse->id;
        }

        $this->syncProductAggregateFromSlots($product);
        $product->save();

        $this->recordMovement(
            $product,
            $delta,
            StockMovement::TYPE_INVENTORY_ADJUSTMENT,
            (int) $warehouse->id,
            null,
            'shopify',
            null,
            null,
            $notes ?: 'Sync inventaire Shopify',
            null,
            $before,
            $available,
            $notes ?: 'Sync inventaire Shopify',
            $resolvedVariantId
        );
    }

    public function syncVariantOnlineWarehouseFromExternal(ProductVariant $variant, int $available, ?string $notes = null): void
    {
        $product = $variant->product;
        if (! $product) {
            return;
        }

        $this->syncOnlineWarehouseFromExternal($product, $available, $notes, $variant->id);
        $variant->inventory_quantity = max(0, $available);
        $variant->save();
    }

    /**
     * Ensure a Shopify product is attached to the existing SHOPIFY online warehouse
     * and that product_stocks matches stock_enligne (without creating a new depot).
     */
    public function ensureShopifyProductOnOnlineWarehouse(Product $product): void
    {
        if (! $product->isShopifyProduct() || ! $product->tracksStock()) {
            return;
        }

        $warehouse = Warehouse::onlineWarehouse();
        if (! $warehouse) {
            return;
        }

        if ((int) $product->warehouse_id !== (int) $warehouse->id) {
            $product->warehouse_id = $warehouse->id;
        }

        $qty = max(0, (int) $product->stock_enligne);
        if ($product->hasVariants()) {
            foreach ($product->variants as $variant) {
                $variantQty = max(0, (int) $variant->inventory_quantity);
                $slot = $this->findOrCreateSlot($product->id, (int) $warehouse->id, null, true, $variant->id);
                if ((int) $slot->quantity !== $variantQty) {
                    $slot->quantity = $variantQty;
                    $slot->save();
                }
            }
        } else {
            $slot = $this->findOrCreateSlot($product->id, (int) $warehouse->id, null, true);
            if ((int) $slot->quantity !== $qty) {
                $slot->quantity = $qty;
                $slot->save();
            }
        }

        $this->syncProductAggregateFromSlots($product);
        $product->save();
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
            $rows[] = [
                'product_id' => $item->product_id ?? null,
                'product_variant_id' => $item->product_variant_id ?? null,
                'quantity' => (int) $item->quantity,
            ];
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
                $documentReference,
                null,
                null,
                null,
                null,
                $row['product_variant_id'] ?? null
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
            $rows[] = [
                'product_id' => $item->product_id ?? null,
                'product_variant_id' => $item->product_variant_id ?? null,
                'quantity' => (int) $item->quantity,
            ];
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
        ?int $transferGroupId = null,
        ?int $variantId = null
    ): ?string {
        if (! $product->tracksStock()) {
            return null;
        }

        $resolvedVariantId = $this->resolveVariantIdForProduct($product, $variantId);

        $warehouse = $this->resolveTargetWarehouse($product, $channel, $warehouseId);
        $warehouseId = $warehouse?->id;
        $locationId = $locationId ?? $product->warehouse_location_id;
        $field = $this->stockFieldForWarehouse($warehouse, $product, $channel);

        $slotQty = $warehouseId
            ? (int) $this->findOrCreateSlot($product->id, $warehouseId, $locationId, false, $resolvedVariantId)->quantity
            : (int) ($product->{$field} ?? 0);
        $warning = null;
        $allowNegative = StockSettings::allowNegative();

        if ($slotQty < $quantity && ! $allowNegative) {
            $label = $this->stockLabel($product, $resolvedVariantId);
            $warning = 'Stock insuffisant pour « '.$label.' » (disponible: '.$slotQty.', demandé: '.$quantity.').';

            if ($strict) {
                throw new RuntimeException($warning);
            }
        }

        $this->applyDelta(
            $product,
            -$quantity,
            $field,
            $warehouseId,
            $locationId,
            $movementType,
            $documentType,
            $documentId,
            $documentReference,
            $notes,
            $transferGroupId,
            $resolvedVariantId
        );

        $this->pushEnligneStockToJumia($product, $field);
        $this->pushEnligneStockToShopify($product, $field, $syncShopify, $resolvedVariantId);

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
        ?int $transferGroupId = null,
        ?int $variantId = null
    ): void {
        if (! $product->tracksStock()) {
            return;
        }

        $resolvedVariantId = $this->resolveVariantIdForProduct($product, $variantId);

        $warehouse = $this->resolveTargetWarehouse($product, $channel, $warehouseId);
        $warehouseId = $warehouse?->id;
        $locationId = $locationId ?? $product->warehouse_location_id;
        $field = $this->stockFieldForWarehouse($warehouse, $product, $channel);

        $this->applyDelta(
            $product,
            $quantity,
            $field,
            $warehouseId,
            $locationId,
            $movementType,
            $documentType,
            $documentId,
            $documentReference,
            $notes,
            $transferGroupId,
            $resolvedVariantId
        );

        $this->pushEnligneStockToJumia($product, $field);
        $this->pushEnligneStockToShopify($product, $field, $syncShopify, $resolvedVariantId);
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
        string $channel = 'default',
        ?int $variantId = null
    ): void {
        if (! $product->tracksStock()) {
            return;
        }

        $resolvedVariantId = $this->resolveVariantIdForProduct($product, $variantId);

        $warehouseId = $warehouseId ?? $product->warehouse_id ?? Warehouse::primary()?->id;
        $locationId = $locationId ?? $product->warehouse_location_id;

        $slot = $this->findOrCreateSlot($product->id, $warehouseId, $locationId, false, $resolvedVariantId);
        $before = (int) $slot->quantity;
        $delta = $newQuantity - $before;

        if ($delta === 0) {
            return;
        }

        $slot->quantity = $newQuantity;
        $slot->save();

        $field = $this->stockFieldForWarehouse(Warehouse::find($warehouseId), $product, $channel);

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
            $notes,
            null,
            $before,
            $newQuantity,
            $notes ?: 'Ajustement inventaire',
            $resolvedVariantId
        );

        $this->pushEnligneStockToJumia($product, $field);
        $this->pushEnligneStockToShopify($product, $field, $field === 'stock_enligne', $resolvedVariantId);
    }

    /**
     * Set absolute physical stock on a warehouse/location slot (does not affect Shopify/online stock).
     */
    public function adjustPhysicalStock(
        Product $product,
        int $newQuantity,
        int $warehouseId,
        ?int $locationId,
        string $reason,
        ?string $notes = null,
        ?int $variantId = null
    ): ?StockMovement {
        if (! $product->tracksStock()) {
            throw new RuntimeException('Ce produit ne gère pas de stock.');
        }
        if ($newQuantity < 0) {
            throw new RuntimeException('La quantité ne peut pas être négative.');
        }

        $warehouse = Warehouse::query()->findOrFail($warehouseId);
        if ($warehouse->isOnline()) {
            throw new RuntimeException('Le stock Shopify / en ligne ne peut pas être ajusté depuis cette fonction.');
        }

        if ($locationId) {
            $location = WarehouseLocation::query()->findOrFail($locationId);
            if ((int) $location->warehouse_id !== $warehouseId) {
                throw new RuntimeException('L’emplacement sélectionné n’appartient pas au dépôt choisi.');
            }
        }

        $resolvedVariantId = $this->resolveVariantIdForProduct($product, $variantId);
        $slot = $this->findOrCreateSlot($product->id, $warehouseId, $locationId, false, $resolvedVariantId);
        $before = (int) $slot->quantity;
        $delta = $newQuantity - $before;

        if ($delta === 0) {
            return null;
        }

        $slot->quantity = $newQuantity;
        $slot->save();

        $this->syncProductAggregateFromSlots($product);
        $product->save();

        $reasonLabel = StockMovement::stockAdjustmentReasonLabel($reason);

        $this->recordMovement(
            $product,
            $delta,
            StockMovement::TYPE_STOCK_ADJUSTMENT,
            $warehouseId,
            $locationId,
            'stock_adjustment',
            null,
            null,
            $notes,
            null,
            $before,
            $newQuantity,
            $reasonLabel,
            $resolvedVariantId
        );

        return StockMovement::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouseId)
            ->where('type', StockMovement::TYPE_STOCK_ADJUSTMENT)
            ->latest('id')
            ->first();
    }

    /**
     * Declare physical stock entry into a warehouse (does not affect Shopify/online stock).
     */
    public function declarePhysicalStock(
        Product $product,
        int $quantity,
        int $warehouseId,
        ?int $locationId,
        string $reason,
        ?string $notes = null,
        ?Carbon $movedAt = null,
        ?int $variantId = null
    ): StockMovement {
        if (! $product->tracksStock()) {
            throw new RuntimeException('Ce produit ne gère pas de stock.');
        }
        if ($quantity <= 0) {
            throw new RuntimeException('La quantité doit être positive.');
        }

        $warehouse = Warehouse::query()->findOrFail($warehouseId);
        if ($warehouse->isOnline()) {
            throw new RuntimeException('Le stock physique ne peut pas être déclaré sur un dépôt en ligne (Shopify).');
        }

        if ($locationId) {
            $location = WarehouseLocation::query()->findOrFail($locationId);
            if ((int) $location->warehouse_id !== $warehouseId) {
                throw new RuntimeException('L’emplacement sélectionné n’appartient pas au dépôt choisi.');
            }
        }

        $reasonLabel = StockMovement::physicalStockReasonLabel($reason);

        $this->increase(
            $product,
            $quantity,
            'magasin',
            false,
            StockMovement::TYPE_PHYSICAL_IN,
            'physical_declaration',
            null,
            null,
            $warehouseId,
            $locationId,
            $notes,
            null,
            $variantId
        );

        $movement = StockMovement::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouseId)
            ->where('type', StockMovement::TYPE_PHYSICAL_IN)
            ->latest('id')
            ->firstOrFail();

        $movement->update([
            'reason' => $reasonLabel,
            'notes' => $notes,
            'moved_at' => $movedAt ?? $movement->moved_at,
        ]);

        return $movement->fresh();
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
        ?string $notes = null,
        ?int $variantId = null
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

        return DB::transaction(function () use ($product, $quantity, $fromWarehouseId, $fromLocationId, $toWarehouseId, $toLocationId, $notes, $groupId, $variantId) {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $resolvedVariantId = $this->resolveVariantIdForProduct($product, $variantId);
            $fromSlot = $this->findOrCreateSlot($product->id, $fromWarehouseId, $fromLocationId, true, $resolvedVariantId);

            if ((int) $fromSlot->quantity < $quantity && ! StockSettings::allowNegative()) {
                throw new RuntimeException('Stock insuffisant sur l’emplacement source (disponible: '.$fromSlot->quantity.').');
            }

            $fromSlot->quantity = (int) $fromSlot->quantity - $quantity;
            $fromSlot->save();

            $toSlot = $this->findOrCreateSlot($product->id, $toWarehouseId, $toLocationId, true, $resolvedVariantId);
            $toSlot->quantity = (int) $toSlot->quantity + $quantity;
            $toSlot->save();

            $fromBefore = (int) $fromSlot->quantity + $quantity;
            $toBefore = (int) $toSlot->quantity - $quantity;

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
                $groupId,
                $fromBefore,
                (int) $fromSlot->quantity,
                'Transfert de stock',
                $resolvedVariantId
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
                $groupId,
                $toBefore,
                (int) $toSlot->quantity,
                'Transfert de stock',
                $resolvedVariantId
            );

            // Shopify ne change que si SHOPIFY STOCK EN LIGNE est source ou destination.
            $onlineId = Warehouse::onlineWarehouse()?->id;
            $touchesOnline = $onlineId
                && ((int) $fromWarehouseId === (int) $onlineId || (int) $toWarehouseId === (int) $onlineId);

            if ($touchesOnline) {
                $this->pushEnligneStockToJumia($product, 'stock_enligne');
                $this->pushEnligneStockToShopify($product, 'stock_enligne', true, $resolvedVariantId);
            }

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

    public function quantityAtWarehouse(Product $product, int $warehouseId, ?int $locationId = null): int
    {
        return $this->slotQuantity($product->id, $warehouseId, $locationId);
    }

    public function quantityAtSlot(Product $product, int $warehouseId, ?int $locationId = null, ?int $variantId = null): int
    {
        $query = ProductStock::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouseId);

        if ($locationId) {
            $query->where('warehouse_location_id', $locationId);
        } else {
            $query->whereNull('warehouse_location_id');
        }

        if ($variantId) {
            $query->where('product_variant_id', $variantId);
        } else {
            $query->whereNull('product_variant_id');
        }

        return (int) ($query->value('quantity') ?? 0);
    }

    /**
     * @return list<array{warehouse_id:int, name:string, kind:string, quantity:int, is_online:bool}>
     */
    public function locationBreakdown(Product $product): array
    {
        $warehouses = Warehouse::query()->active()->orderByDesc('is_fulfillment_default')->orderBy('name')->get();
        $slots = ProductStock::query()->where('product_id', $product->id)->get()->groupBy('warehouse_id');

        $rows = [];
        foreach ($warehouses as $warehouse) {
            $qty = (int) ($slots->get($warehouse->id)?->sum('quantity') ?? 0);
            $rows[] = [
                'warehouse_id' => (int) $warehouse->id,
                'name' => $warehouse->name,
                'code' => $warehouse->code,
                'kind' => $warehouse->kind ?? Warehouse::KIND_PHYSICAL,
                'quantity' => $qty,
                'is_online' => $warehouse->isOnline(),
            ];
        }

        return $rows;
    }

    public function physicalTotal(Product $product): int
    {
        $onlineIds = Warehouse::query()->online()->pluck('id');

        return (int) ProductStock::query()
            ->where('product_id', $product->id)
            ->when($onlineIds->isNotEmpty(), fn ($q) => $q->whereNotIn('warehouse_id', $onlineIds))
            ->sum('quantity');
    }

    /**
     * @return list<array{
     *     variant_id: int,
     *     variant_title: string,
     *     sku: ?string,
     *     barcode: ?string,
     *     total_stock: int,
     *     locations: list<array{warehouse_id:int, name:string, quantity:int, is_online:bool}>
     * }>
     */
    public function variantLocationBreakdown(Product $product): array
    {
        if (! $product->hasVariants()) {
            return [];
        }

        $warehouses = Warehouse::query()->active()->orderByDesc('is_fulfillment_default')->orderBy('name')->get();
        $slots = ProductStock::query()
            ->where('product_id', $product->id)
            ->whereNotNull('product_variant_id')
            ->get()
            ->groupBy('product_variant_id');

        return $product->variants->map(function (ProductVariant $variant) use ($warehouses, $slots) {
            $variantSlots = $slots->get($variant->id, collect());
            $locations = [];

            foreach ($warehouses as $warehouse) {
                $qty = (int) $variantSlots->where('warehouse_id', $warehouse->id)->sum('quantity');
                $locations[] = [
                    'warehouse_id' => (int) $warehouse->id,
                    'name' => $warehouse->name,
                    'quantity' => $qty,
                    'is_online' => $warehouse->isOnline(),
                ];
            }

            return [
                'variant_id' => $variant->id,
                'variant_title' => $variant->full_title,
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'price' => $variant->price,
                'total_stock' => (int) $variantSlots->sum('quantity'),
                'locations' => $locations,
            ];
        })->values()->all();
    }

    protected function applyDelta(
        Product $product,
        int $delta,
        string $field,
        ?int $warehouseId,
        ?int $locationId,
        string $movementType,
        ?string $documentType,
        ?int $documentId,
        ?string $documentReference,
        ?string $notes,
        ?int $transferGroupId,
        ?int $variantId = null
    ): void {
        if ($delta === 0) {
            return;
        }

        $resolvedVariantId = $this->resolveVariantIdForProduct($product, $variantId);

        $before = 0;
        if ($warehouseId) {
            $slot = $this->findOrCreateSlot($product->id, $warehouseId, $locationId, false, $resolvedVariantId);
            $before = (int) $slot->quantity;
            $slot->quantity = $before + $delta;
            $slot->save();
            $after = (int) $slot->quantity;
        } else {
            $before = (int) ($product->{$field} ?? 0);
            $after = $before + $delta;
        }

        $this->syncProductAggregateFromSlots($product);
        $product->save();

        $this->recordMovement(
            $product,
            $delta,
            $movementType,
            $warehouseId,
            $locationId,
            $documentType,
            $documentId,
            $documentReference,
            $notes,
            $transferGroupId,
            $before,
            $after,
            $notes,
            $resolvedVariantId
        );
    }

    protected function slotQuantity(int $productId, int $warehouseId, ?int $locationId = null): int
    {
        $query = ProductStock::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId);

        if ($locationId) {
            $query->where('warehouse_location_id', $locationId);
        }

        return (int) $query->sum('quantity');
    }

    protected function resolveTargetWarehouse(Product $product, string $channel, ?int $warehouseId): ?Warehouse
    {
        if ($warehouseId) {
            return Warehouse::query()->find($warehouseId);
        }

        $field = $this->stockFieldForProduct($product, $channel);
        if ($field === 'stock_enligne') {
            return Warehouse::onlineWarehouse() ?? Warehouse::primary();
        }

        return Warehouse::fulfillmentWarehouse()
            ?? ($product->warehouse_id ? Warehouse::find($product->warehouse_id) : null)
            ?? Warehouse::primary();
    }

    protected function stockFieldForWarehouse(?Warehouse $warehouse, Product $product, string $channel): string
    {
        if ($warehouse) {
            return $warehouse->isOnline() ? 'stock_enligne' : 'stock_magasin';
        }

        return $this->stockFieldForProduct($product, $channel);
    }

    protected function findOrCreateSlot(int $productId, ?int $warehouseId, ?int $locationId, bool $lock = false, ?int $variantId = null): ProductStock
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
            })
            ->where(function ($q) use ($variantId) {
                if ($variantId) {
                    $q->where('product_variant_id', $variantId);
                } else {
                    $q->whereNull('product_variant_id');
                }
            });

        if ($lock) {
            $query->lockForUpdate();
        }

        $slot = $query->first();
        if ($slot) {
            return $slot;
        }

        $slot = ProductStock::create([
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'warehouse_id' => $warehouseId,
            'warehouse_location_id' => $locationId,
            'quantity' => 0,
            'reserved' => 0,
        ]);

        $product = Product::query()->find($productId);
        $warehouse = Warehouse::query()->find($warehouseId);
        $hasOtherSlots = ProductStock::query()
            ->where('product_id', $productId)
            ->where('id', '!=', $slot->id)
            ->exists();
        if ($product && $warehouse && ! $hasOtherSlots && ! $product->hasVariants()) {
            $legacy = $warehouse->isOnline()
                ? (int) ($product->stock_enligne ?? 0)
                : (int) ($product->isShopifyProduct() ? 0 : ($product->stock_magasin ?? $product->stock_quantity ?? 0));
            if ($legacy !== 0) {
                $slot->quantity = $legacy;
                $slot->save();
            }
        }

        return $slot;
    }

    protected function syncProductAggregateFromSlots(Product $product): void
    {
        $onlineIds = Warehouse::query()->online()->pluck('id');
        $enligne = (int) ProductStock::query()
            ->where('product_id', $product->id)
            ->when($onlineIds->isNotEmpty(), fn ($q) => $q->whereIn('warehouse_id', $onlineIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->sum('quantity');
        $magasin = (int) ProductStock::query()
            ->where('product_id', $product->id)
            ->when($onlineIds->isNotEmpty(), fn ($q) => $q->whereNotIn('warehouse_id', $onlineIds))
            ->sum('quantity');
        $reserved = (int) ProductStock::query()->where('product_id', $product->id)->sum('reserved');

        $product->stock_enligne = $enligne;
        $product->stock_magasin = $magasin;
        $product->stock_reserved = $reserved;
        $product->stock_quantity = $product->isShopifyProduct() ? $enligne : $magasin;
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
        ?int $transferGroupId = null,
        ?int $quantityBefore = null,
        ?int $quantityAfter = null,
        ?string $reason = null,
        ?int $variantId = null
    ): void {
        if ($quantity === 0) {
            return;
        }

        StockMovement::create([
            'product_id' => $product->id,
            'product_variant_id' => $variantId,
            'warehouse_id' => $warehouseId,
            'warehouse_location_id' => $locationId,
            'quantity' => $quantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'type' => $type,
            'moved_at' => now(),
            'document_type' => $documentType,
            'document_id' => $documentId,
            'document_reference' => $documentReference,
            'user_id' => Auth::id(),
            'transfer_group_id' => $transferGroupId,
            'notes' => $notes,
            'reason' => $reason ?? $notes,
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

    protected function pushEnligneStockToShopify(Product $product, string $field, bool $enabled, ?int $variantId = null): void
    {
        if (! $enabled || $field !== 'stock_enligne' || ! $product->isShopifyProduct()) {
            return;
        }

        $productId = $product->id;
        $resolvedVariantId = $variantId;

        DB::afterCommit(function () use ($productId, $resolvedVariantId): void {
            try {
                $fresh = Product::query()->find($productId);
                if (! $fresh) {
                    return;
                }

                app(ShopifyInventorySyncService::class)->pushProductStock($fresh, $resolvedVariantId);
            } catch (\Throwable $e) {
                Log::warning('Shopify stock push after local stock change failed', [
                    'product_id' => $productId,
                    'variant_id' => $resolvedVariantId,
                    'message' => $e->getMessage(),
                ]);
            }
        });
    }

    protected function resolveVariantIdForProduct(Product $product, ?int $variantId, bool $required = true): ?int
    {
        if (! $product->hasVariants()) {
            return null;
        }

        if (! $variantId) {
            if ($required) {
                throw new RuntimeException('Une variante doit être sélectionnée pour « '.$product->name.' ».');
            }

            return null;
        }

        $exists = ProductVariant::query()
            ->where('id', $variantId)
            ->where('product_id', $product->id)
            ->exists();

        if (! $exists) {
            throw new RuntimeException('Variante invalide pour « '.$product->name.' ».');
        }

        return $variantId;
    }

    protected function stockLabel(Product $product, ?int $variantId): string
    {
        if (! $variantId) {
            return (string) $product->name;
        }

        $variant = ProductVariant::query()->find($variantId);

        return $variant
            ? $product->name.' – '.$variant->full_title
            : (string) $product->name;
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
