<?php

namespace App\Services;

use App\Models\PosSale;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockReplenishmentNeed;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderPhysicalStockService
{
    public function __construct(
        protected StockMovementService $stockMovement,
        protected ProductPurchaseHistoryService $purchaseHistory,
    ) {}

    /**
     * Deduct physical stock at the fulfillment location (Belvédère by default).
     * Never uses Shopify/online stock. Never creates negative physical stock.
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
                $available = $variantId
                    ? (int) \App\Models\ProductStock::query()
                        ->where('product_id', $product->id)
                        ->where('product_variant_id', $variantId)
                        ->where('warehouse_id', $warehouse->id)
                        ->sum('quantity')
                    : $this->stockMovement->quantityAtWarehouse($product, (int) $warehouse->id);
                if ($available >= $needed) {
                    $this->stockMovement->decrease(
                        $product,
                        $needed,
                        'magasin',
                        true,
                        false,
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
            $existing->quantity_needed = (int) $existing->quantity_needed + $needed;
            $existing->save();
        } else {
            StockReplenishmentNeed::create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'pos_sale_id' => $order->id,
                'quantity_needed' => $needed,
                'suggested_supplier_id' => $last['id'] ?? $product->primary_supplier_id,
                'supplier_id' => $last['id'] ?? $product->primary_supplier_id,
                'status' => StockReplenishmentNeed::STATUS_OPEN,
                'notes' => 'Commande '.$order->ticket_number,
            ]);
        }

        return [
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->ref,
            'quantity' => $needed,
            'message' => 'STOCK PHYSIQUE NON DISPONIBLE',
        ];
    }
}
