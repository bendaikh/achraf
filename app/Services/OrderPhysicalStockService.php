<?php

namespace App\Services;

use App\Models\PosSale;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\StockReplenishmentNeed;
use App\Models\StockReservation;
use App\Models\Warehouse;
use App\Support\StockSettings;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderPhysicalStockService
{
    public function __construct(
        protected StockMovementService $stockMovement,
        protected ProductPurchaseHistoryService $purchaseHistory,
    ) {}

    /**
     * Entry point used by the order UI.
     * Picking ON  → allocate/reserve (no physical exit).
     * Picking OFF → legacy immediate physical exit.
     *
     * @return array{deducted: list<array>, unavailable: list<array>, warehouse: Warehouse, mode: string, reserved?: list<array>}
     */
    public function prepare(PosSale $order, ?int $warehouseId = null): array
    {
        if (StockSettings::pickingEnabled()) {
            return $this->allocate($order, $warehouseId);
        }

        $result = $this->process($order, $warehouseId);
        $result['mode'] = 'legacy_exit';

        return $result;
    }

    /**
     * Allocate available physical stock to the order (reservation only).
     * Searches physical warehouses (fulfillment first), never the online mirror.
     *
     * @return array{deducted: list<array>, reserved: list<array>, unavailable: list<array>, warehouse: Warehouse, mode: string}
     */
    public function allocate(PosSale $order, ?int $warehouseId = null): array
    {
        $preferred = $warehouseId
            ? Warehouse::query()->findOrFail($warehouseId)
            : Warehouse::fulfillmentWarehouse();

        if (! $preferred || $preferred->isOnline()) {
            throw new RuntimeException('Aucun emplacement physique de préparation n’est configuré (Magasin Belvédère).');
        }

        if ($order->physical_stock_processed_at) {
            throw new RuntimeException('Le stock physique de cette commande a déjà été traité (sortie validée).');
        }

        $existingActive = StockReservation::query()
            ->active()
            ->where('source_type', 'pos_sale')
            ->where('source_id', $order->id)
            ->exists();

        if ($existingActive) {
            throw new RuntimeException('Cette commande a déjà des réservations actives. Utilisez Préparation / Picking.');
        }

        $order->loadMissing('items.product', 'items.variant');

        return DB::transaction(function () use ($order, $preferred) {
            $reserved = [];
            $unavailable = [];

            $warehouses = Warehouse::query()
                ->active()
                ->physical()
                ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$preferred->id])
                ->orderByDesc('is_fulfillment_default')
                ->orderBy('name')
                ->get();

            foreach ($order->items as $item) {
                $product = $item->product;
                if (! $product || ! $product->tracksStock()) {
                    continue;
                }

                $needed = (int) $item->quantity;
                if ($needed <= 0) {
                    continue;
                }

                $variantId = $item->product_variant_id ? (int) $item->product_variant_id : null;
                $remaining = $needed;

                foreach ($warehouses as $warehouse) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $slots = ProductStock::query()
                        ->where('product_id', $product->id)
                        ->where('warehouse_id', $warehouse->id)
                        ->when(
                            $variantId,
                            fn ($q) => $q->where('product_variant_id', $variantId),
                            fn ($q) => $q->whereNull('product_variant_id')
                        )
                        ->orderByRaw('CASE WHEN warehouse_location_id IS NULL THEN 1 ELSE 0 END')
                        ->orderBy('warehouse_location_id')
                        ->lockForUpdate()
                        ->get();

                    foreach ($slots as $slot) {
                        if ($remaining <= 0) {
                            break;
                        }
                        $available = $slot->available();
                        if ($available <= 0) {
                            continue;
                        }
                        $take = min($remaining, $available);
                        $this->stockMovement->reserveStock(
                            $product,
                            $take,
                            (int) $warehouse->id,
                            $slot->warehouse_location_id ? (int) $slot->warehouse_location_id : null,
                            $variantId,
                            [
                                'source_type' => 'pos_sale',
                                'source_id' => $order->id,
                                'source_line_type' => 'pos_sale_item',
                                'source_line_id' => $item->id,
                                'document_reference' => $order->ticket_number,
                                'notes' => 'Allocation commande '.$order->ticket_number,
                            ]
                        );
                        $reserved[] = [
                            'product_id' => $product->id,
                            'product_variant_id' => $variantId,
                            'name' => $product->name,
                            'sku' => $item->variant?->sku ?: $product->ref,
                            'quantity' => $take,
                            'warehouse_id' => (int) $warehouse->id,
                            'warehouse_location_id' => $slot->warehouse_location_id,
                        ];
                        $remaining -= $take;
                    }
                }

                if ($remaining > 0) {
                    $unavailable[] = $this->registerNeed($order, $product, $preferred, $remaining);
                }
            }

            return [
                'deducted' => [],
                'reserved' => $reserved,
                'unavailable' => $unavailable,
                'warehouse' => $preferred,
                'mode' => 'reservation',
            ];
        });
    }

    /**
     * Validate physical exit for reserved quantities (picking workflow).
     * Idempotent: already consumed reservations are skipped.
     *
     * @return array{consumed: int, skipped: int}
     */
    public function validateExit(PosSale $order): array
    {
        if ($order->physical_stock_processed_at) {
            return ['consumed' => 0, 'skipped' => 0];
        }

        return DB::transaction(function () use ($order) {
            $reservations = StockReservation::query()
                ->active()
                ->where('source_type', 'pos_sale')
                ->where('source_id', $order->id)
                ->lockForUpdate()
                ->get();

            $consumed = 0;
            foreach ($reservations as $reservation) {
                $this->stockMovement->consumeReservation(
                    $reservation,
                    'Validation sortie commande '.$order->ticket_number
                );
                $consumed++;
            }

            $order->physical_stock_processed_at = now();
            $order->save();

            return ['consumed' => $consumed, 'skipped' => 0];
        });
    }

    /**
     * Release active reservations when order is cancelled before exit.
     */
    public function releaseAllocations(PosSale $order, ?string $notes = null): int
    {
        $reservations = StockReservation::query()
            ->active()
            ->where('source_type', 'pos_sale')
            ->where('source_id', $order->id)
            ->get();

        $released = 0;
        foreach ($reservations as $reservation) {
            $this->stockMovement->releaseReservation(
                $reservation,
                $notes ?? 'Annulation commande '.$order->ticket_number
            );
            $released++;
        }

        // Recalculate replenishment needs for this order.
        StockReplenishmentNeed::query()
            ->open()
            ->where('pos_sale_id', $order->id)
            ->update(['status' => 'cancelled']);

        return $released;
    }

    /**
     * Deduct physical stock at the fulfillment location (Belvédère by default).
     * Never uses Shopify/online stock. Never creates negative physical stock.
     * Legacy path when picking is disabled.
     *
     * @return array{deducted: list<array>, unavailable: list<array>, warehouse: Warehouse}
     */
    public function process(PosSale $order, ?int $warehouseId = null): array
    {
        $warehouse = $warehouseId
            ? Warehouse::query()->findOrFail($warehouseId)
            : Warehouse::fulfillmentWarehouse();

        if (! $warehouse || $warehouse->isOnline()) {
            throw new RuntimeException('Aucun emplacement physique de préparation n’est configuré (Magasin Belvédère).');
        }

        if ($order->physical_stock_processed_at) {
            throw new RuntimeException('Le stock physique de cette commande a déjà été traité.');
        }

        $order->loadMissing('items.product', 'items.variant');

        return DB::transaction(function () use ($order, $warehouse) {
            $deducted = [];
            $unavailable = [];

            foreach ($order->items as $item) {
                $product = $item->product;
                if (! $product || ! $product->tracksStock()) {
                    continue;
                }

                $needed = (int) $item->quantity;
                if ($needed <= 0) {
                    continue;
                }

                $variantId = $item->product_variant_id ? (int) $item->product_variant_id : null;
                $available = $this->availableAtWarehouse($product, (int) $warehouse->id, $variantId);
                if ($available >= $needed) {
                    $this->stockMovement->decrease(
                        $product,
                        $needed,
                        'magasin',
                        true,
                        true,
                        StockMovement::TYPE_ORDER_OUT,
                        'pos_sale',
                        $order->id,
                        $order->ticket_number,
                        (int) $warehouse->id,
                        null,
                        'Sortie commande '.$order->ticket_number,
                        null,
                        $variantId
                    );
                    $deducted[] = [
                        'product_id' => $product->id,
                        'product_variant_id' => $variantId,
                        'name' => $product->name,
                        'sku' => $item->variant?->sku ?: $product->ref,
                        'quantity' => $needed,
                    ];

                    continue;
                }

                $unavailable[] = $this->registerNeed($order, $product, $warehouse, $needed);
            }

            $order->physical_stock_processed_at = now();
            $order->save();

            return [
                'deducted' => $deducted,
                'unavailable' => $unavailable,
                'warehouse' => $warehouse,
            ];
        });
    }

    protected function availableAtWarehouse(Product $product, int $warehouseId, ?int $variantId): int
    {
        return (int) ProductStock::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouseId)
            ->when(
                $variantId,
                fn ($q) => $q->where('product_variant_id', $variantId),
                fn ($q) => $q->whereNull('product_variant_id')
            )
            ->get()
            ->sum(fn (ProductStock $slot) => $slot->available());
    }

    /**
     * @return array{product_id:int, name:string, sku:?string, quantity:int, message:string}
     */
    protected function registerNeed(PosSale $order, Product $product, Warehouse $warehouse, int $needed): array
    {
        $last = $this->purchaseHistory->lastSuppliersForProducts([$product->id])[$product->id] ?? null;

        $existing = StockReplenishmentNeed::query()
            ->open()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('pos_sale_id', $order->id)
            ->first();

        if ($existing) {
            $existing->update([
                'quantity_needed' => $needed,
                'suggested_supplier_id' => $last['supplier_id'] ?? $existing->suggested_supplier_id,
            ]);
        } else {
            StockReplenishmentNeed::create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'pos_sale_id' => $order->id,
                'quantity_needed' => $needed,
                'suggested_supplier_id' => $last['supplier_id'] ?? null,
                'status' => 'open',
                'notes' => 'Manque sur commande '.$order->ticket_number,
            ]);
        }

        return [
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->ref,
            'quantity' => $needed,
            'message' => 'Stock physique insuffisant — besoin d’approvisionnement créé.',
        ];
    }
}
