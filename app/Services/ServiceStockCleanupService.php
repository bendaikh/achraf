<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Neutralise les faux stocks historiques des articles non stockables (services / non stockés).
 * N'efface jamais le produit ni les documents commerciaux associés.
 */
class ServiceStockCleanupService
{
    /**
     * @return array{slots_cleared: int, quantity_removed: int, product_ids: list<int>}
     */
    public function neutralizeNonStockableSlots(?int $actorUserId = null): array
    {
        $actorUserId ??= Auth::id();
        $slotsCleared = 0;
        $quantityRemoved = 0;
        $productIds = [];

        DB::transaction(function () use (&$slotsCleared, &$quantityRemoved, &$productIds, $actorUserId) {
            $slots = ProductStock::query()
                ->whereHas('product', fn ($q) => $q->where(function ($inner) {
                    $inner->where('item_kind', Product::KIND_SERVICE)
                        ->orWhere('item_kind', Product::KIND_NON_STOCKED)
                        ->orWhere('element_type', 'Service');
                }))
                ->where(function ($q) {
                    $q->where('quantity', '!=', 0)->orWhere('reserved', '!=', 0);
                })
                ->lockForUpdate()
                ->get();

            foreach ($slots as $slot) {
                $before = (int) $slot->quantity;
                $reservedBefore = (int) $slot->reserved;
                if ($before === 0 && $reservedBefore === 0) {
                    continue;
                }

                $productIds[] = (int) $slot->product_id;
                $quantityRemoved += abs($before);
                $slotsCleared++;

                $slot->quantity = 0;
                $slot->reserved = 0;
                $slot->save();

                if ($before !== 0) {
                    StockMovement::create([
                        'product_id' => $slot->product_id,
                        'product_variant_id' => $slot->product_variant_id,
                        'warehouse_id' => $slot->warehouse_id,
                        'warehouse_location_id' => $slot->warehouse_location_id,
                        'quantity' => -$before,
                        'quantity_before' => $before,
                        'quantity_after' => 0,
                        'type' => StockMovement::TYPE_INVENTORY_ADJUSTMENT,
                        'moved_at' => now(),
                        'document_type' => 'service_stock_cleanup',
                        'document_id' => null,
                        'document_reference' => 'CLEANUP-SERVICE-STOCK',
                        'user_id' => $actorUserId,
                        'notes' => 'Neutralisation stock service/non stockable (article non physique)',
                        'reason' => 'Neutralisation stock service',
                    ]);
                }

                Log::info('service_stock_cleanup.slot_zeroed', [
                    'product_id' => $slot->product_id,
                    'warehouse_id' => $slot->warehouse_id,
                    'warehouse_location_id' => $slot->warehouse_location_id,
                    'quantity_before' => $before,
                    'reserved_before' => $reservedBefore,
                ]);
            }

            $productIds = array_values(array_unique($productIds));
            foreach ($productIds as $productId) {
                $product = Product::query()->lockForUpdate()->find($productId);
                if (! $product) {
                    continue;
                }
                $product->stock_quantity = 0;
                $product->stock_magasin = 0;
                $product->stock_reserved = 0;
                $product->stock_sellable = 0;
                $product->warehouse_id = null;
                $product->warehouse_location_id = null;
                $product->depot = null;
                $product->location = null;
                $product->save();
            }
        });

        return [
            'slots_cleared' => $slotsCleared,
            'quantity_removed' => $quantityRemoved,
            'product_ids' => $productIds,
        ];
    }

    /**
     * Vide tous les slots d'un produit devenu non stockable (changement de type).
     */
    public function clearSlotsForProduct(Product $product, ?string $reason = null): int
    {
        if ($product->tracksStock()) {
            return 0;
        }

        $cleared = 0;
        $slots = ProductStock::query()
            ->where('product_id', $product->id)
            ->lockForUpdate()
            ->get();

        foreach ($slots as $slot) {
            $before = (int) $slot->quantity;
            $reserved = (int) $slot->reserved;
            if ($before === 0 && $reserved === 0) {
                continue;
            }

            $slot->quantity = 0;
            $slot->reserved = 0;
            $slot->save();
            $cleared++;

            if ($before !== 0) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'product_variant_id' => $slot->product_variant_id,
                    'warehouse_id' => $slot->warehouse_id,
                    'warehouse_location_id' => $slot->warehouse_location_id,
                    'quantity' => -$before,
                    'quantity_before' => $before,
                    'quantity_after' => 0,
                    'type' => StockMovement::TYPE_INVENTORY_ADJUSTMENT,
                    'moved_at' => now(),
                    'document_type' => 'item_kind_change',
                    'document_reference' => 'KIND-CHANGE',
                    'user_id' => Auth::id(),
                    'notes' => $reason ?: 'Article devenu non stockable — stock physique annulé',
                    'reason' => 'Changement type article',
                ]);
            }
        }

        $product->stock_quantity = 0;
        $product->stock_magasin = 0;
        $product->stock_reserved = 0;
        $product->stock_sellable = 0;

        return $cleared;
    }
}
