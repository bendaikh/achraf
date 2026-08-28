<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Support\VatCategoryHelper;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LocationStockReportService
{
    /**
     * Rapport stock par dépôt (et optionnellement un emplacement).
     * Les stocks Shopify et physiques restent séparés : un rapport = un dépôt.
     *
     * @return array{
     *     warehouse: Warehouse,
     *     location: ?WarehouseLocation,
     *     as_of: Carbon,
     *     rows: Collection<int, object>,
     *     references: int,
     *     quantity: int,
     *     value_ht: float,
     *     value_ttc: float,
     *     value_vat: float
     * }
     */
    public function report(Warehouse $warehouse, ?Carbon $asOf = null, ?int $locationId = null): array
    {
        $asOf = ($asOf ?? now())->endOfDay();
        $location = $locationId ? WarehouseLocation::find($locationId) : null;
        $useSnapshot = $asOf->isSameDay(now()) || $asOf->greaterThanOrEqualTo(now());

        $slots = $useSnapshot
            ? $this->currentSlots($warehouse, $locationId)
            : $this->slotsAsOf($warehouse, $asOf, $locationId);

        $productIds = $slots->pluck('product_id')->unique()->all();
        $products = $productIds === []
            ? collect()
            : Product::query()
                ->with(['primarySupplier', 'variants'])
                ->whereIn('id', $productIds)
                ->orderBy('name')
                ->get()
                ->keyBy('id');

        $rows = collect();
        foreach ($slots as $slot) {
            $qty = (int) $slot->quantity;
            if ($qty <= 0) {
                continue;
            }
            $product = $products->get($slot->product_id);
            if (! $product) {
                continue;
            }

            $reserved = (int) ($slot->reserved ?? 0);
            $available = max(0, $qty - $reserved);
            $ht = $this->purchasePriceHt($product);
            $rate = VatCategoryHelper::rateFromLabel($product->vat_category);
            $ttc = $this->purchasePriceTtc($product, $ht);
            $saleHt = round((float) ($product->sale_price_ht ?? 0), 2);
            $saleTtc = round((float) ($product->sale_price ?? 0), 2);
            $variantLabel = $product->variants->count() > 1
                ? $product->variants->pluck('title')->filter()->implode(' / ')
                : ($product->variants->first()?->title ?: '');

            $rows->push((object) [
                'product' => $product,
                'name' => $product->name,
                'sku' => $product->ref,
                'variant' => $variantLabel,
                'supplier' => $product->primarySupplier?->name
                    ?: ($product->last_purchase_supplier_name ?: ''),
                'depot' => $warehouse->name,
                'location' => $slot->location_code ?: '—',
                'category' => $product->product_type_category ?: ($product->product_category ?: ''),
                'quantity' => $qty,
                'reserved' => $reserved,
                'available' => $available,
                'price_ht' => $ht,
                'vat_rate' => $rate,
                'price_ttc' => $ttc,
                'value_ht' => round($ht * $qty, 2),
                'value_ttc' => round($ttc * $qty, 2),
                'sale_price_ht' => $saleHt,
                'sale_price_ttc' => $saleTtc,
            ]);
        }

        $valueHt = round((float) $rows->sum('value_ht'), 2);
        $valueTtc = round((float) $rows->sum('value_ttc'), 2);

        return [
            'warehouse' => $warehouse,
            'location' => $location,
            'as_of' => $asOf,
            'rows' => $rows,
            'references' => $rows->count(),
            'quantity' => (int) $rows->sum('quantity'),
            'value_ht' => $valueHt,
            'value_ttc' => $valueTtc,
            'value_vat' => round($valueTtc - $valueHt, 2),
        ];
    }

    /**
     * @return Collection<int, object{product_id:int, quantity:int, reserved:int, location_code:?string}>
     */
    protected function currentSlots(Warehouse $warehouse, ?int $locationId = null): Collection
    {
        return ProductStock::query()
            ->leftJoin('warehouse_locations', 'warehouse_locations.id', '=', 'product_stocks.warehouse_location_id')
            ->where('product_stocks.warehouse_id', $warehouse->id)
            ->when($locationId, fn ($q) => $q->where('product_stocks.warehouse_location_id', $locationId))
            ->selectRaw('product_stocks.product_id, product_stocks.quantity, product_stocks.reserved, warehouse_locations.code as location_code')
            ->get();
    }

    /**
     * @return Collection<int, object{product_id:int, quantity:int, reserved:int, location_code:?string}>
     */
    protected function slotsAsOf(Warehouse $warehouse, Carbon $asOf, ?int $locationId = null): Collection
    {
        // Approximation : quantités actuelles regroupées par produit, moins mouvements postérieurs.
        $current = ProductStock::query()
            ->where('warehouse_id', $warehouse->id)
            ->when($locationId, fn ($q) => $q->where('warehouse_location_id', $locationId))
            ->selectRaw('product_id, SUM(quantity) as quantity, SUM(reserved) as reserved')
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $later = StockMovement::query()
            ->where('warehouse_id', $warehouse->id)
            ->when($locationId, fn ($q) => $q->where('warehouse_location_id', $locationId))
            ->where('moved_at', '>', $asOf)
            ->selectRaw('product_id, SUM(quantity) as qty')
            ->groupBy('product_id')
            ->pluck('qty', 'product_id')
            ->map(fn ($qty) => (int) $qty);

        $ids = $current->keys()->merge($later->keys())->unique();
        $result = collect();
        foreach ($ids as $productId) {
            $slot = $current->get($productId);
            $qty = (int) ($slot->quantity ?? 0) - (int) $later->get($productId, 0);
            $result->push((object) [
                'product_id' => (int) $productId,
                'quantity' => $qty,
                'reserved' => (int) ($slot->reserved ?? 0),
                'location_code' => null,
            ]);
        }

        return $result;
    }

    public function purchasePriceHt(Product $product): float
    {
        $ht = $product->cost_price_ht ?? $product->last_purchase_price;

        return round((float) ($ht ?? 0), 2);
    }

    public function purchasePriceTtc(Product $product, ?float $ht = null): float
    {
        $ht ??= $this->purchasePriceHt($product);
        $rate = VatCategoryHelper::rateFromLabel($product->vat_category);

        return round($ht * (1 + ($rate / 100)), 2);
    }
}
