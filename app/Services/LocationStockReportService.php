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
    public const PRICE_SOURCE_LAST_PURCHASE = 'Dernier achat fournisseur';

    public const PRICE_SOURCE_PRODUCT_COST = 'Coût fiche produit';

    public const PRICE_SOURCE_NONE = 'Non renseigné';

    /**
     * Rapport stock par dépôt (et optionnellement un emplacement).
     * Uniquement produits physiques stockables avec quantité > 0.
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
                ->tracksStock()
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
            if (! $product || ! $product->tracksStock()) {
                continue;
            }

            $reserved = (int) ($slot->reserved ?? 0);
            $available = max(0, $qty - $reserved);
            $cost = $this->resolvePurchaseCost($product);
            $ht = $cost['ht'];
            $ttc = $cost['ttc'];
            $hasCost = $cost['has_cost'];
            $rate = VatCategoryHelper::rateFromLabel($product->vat_category);
            $saleHt = round((float) ($product->sale_price_ht ?? 0), 2);
            $saleTtc = round((float) ($product->sale_price ?? 0), 2);
            $variantLabel = '';
            if (! empty($slot->variant_title)) {
                $variantLabel = (string) $slot->variant_title;
            } elseif ($product->variants->count() > 1) {
                $variantLabel = $product->variants->pluck('title')->filter()->implode(' / ');
            } else {
                $variantLabel = $product->variants->first()?->title ?: '';
            }

            $valueHt = $hasCost ? round((float) $ht * $qty, 2) : 0.0;
            $valueTtc = $hasCost ? round((float) $ttc * $qty, 2) : 0.0;

            $rows->push((object) [
                'product' => $product,
                'product_id' => (int) $product->id,
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
                'price_ht' => $hasCost ? $ht : null,
                'price_ht_display' => $hasCost ? number_format((float) $ht, 2, '.', '') : self::PRICE_SOURCE_NONE,
                'price_source' => $cost['source'],
                'has_purchase_cost' => $hasCost,
                'vat_rate' => $rate,
                'price_ttc' => $hasCost ? $ttc : null,
                'price_ttc_display' => $hasCost
                    ? number_format((float) $ttc, 2, '.', '')
                    : self::PRICE_SOURCE_NONE,
                'value_ht' => $valueHt,
                'value_ttc' => $valueTtc,
                'value_ht_display' => $hasCost ? number_format($valueHt, 2, '.', '') : self::PRICE_SOURCE_NONE,
                'value_ttc_display' => $hasCost ? number_format($valueTtc, 2, '.', '') : self::PRICE_SOURCE_NONE,
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
     * @return Collection<int, object{product_id:int, quantity:int, reserved:int, location_code:?string, variant_title:?string}>
     */
    protected function currentSlots(Warehouse $warehouse, ?int $locationId = null): Collection
    {
        return ProductStock::query()
            ->leftJoin('warehouse_locations', 'warehouse_locations.id', '=', 'product_stocks.warehouse_location_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'product_stocks.product_variant_id')
            ->join('products', 'products.id', '=', 'product_stocks.product_id')
            ->where('product_stocks.warehouse_id', $warehouse->id)
            ->where('product_stocks.quantity', '>', 0)
            ->where('products.item_kind', Product::KIND_STOCKED)
            ->when($locationId, fn ($q) => $q->where('product_stocks.warehouse_location_id', $locationId))
            ->selectRaw('product_stocks.product_id, product_stocks.quantity, product_stocks.reserved, warehouse_locations.code as location_code, product_variants.title as variant_title')
            ->get();
    }

    /**
     * @return Collection<int, object{product_id:int, quantity:int, reserved:int, location_code:?string, variant_title:?string}>
     */
    protected function slotsAsOf(Warehouse $warehouse, Carbon $asOf, ?int $locationId = null): Collection
    {
        $current = ProductStock::query()
            ->join('products', 'products.id', '=', 'product_stocks.product_id')
            ->where('product_stocks.warehouse_id', $warehouse->id)
            ->where('products.item_kind', Product::KIND_STOCKED)
            ->when($locationId, fn ($q) => $q->where('product_stocks.warehouse_location_id', $locationId))
            ->selectRaw('product_stocks.product_id, SUM(product_stocks.quantity) as quantity, SUM(product_stocks.reserved) as reserved')
            ->groupBy('product_stocks.product_id')
            ->get()
            ->keyBy('product_id');

        $later = StockMovement::query()
            ->where('warehouse_id', $warehouse->id)
            ->when($locationId, fn ($q) => $q->where('warehouse_location_id', $locationId))
            ->where('moved_at', '>', $asOf)
            ->whereHas('product', fn ($q) => $q->tracksStock())
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
                'variant_title' => null,
            ]);
        }

        return $result;
    }

    /**
     * Résout un prix d'achat réel uniquement — jamais inventé depuis un prix de vente.
     *
     * Convention catalogue :
     * - last_purchase_price = PA TTC
     * - cost_price_ttc = PA TTC (fiche)
     * - cost_price_ht = PA HT (coût de revient)
     *
     * PA HT = PA TTC / (1 + taux TVA) — on ne rajoute jamais la TVA sur un montant déjà TTC.
     *
     * @return array{ht: ?float, ttc: ?float, source: string, has_cost: bool}
     */
    public function resolvePurchaseCost(Product $product): array
    {
        $rate = VatCategoryHelper::rateFromLabel($product->vat_category);
        $factor = 1 + ($rate / 100);

        if ($product->last_purchase_price !== null && $product->last_purchase_price !== '') {
            $ttc = round((float) $product->last_purchase_price, 2);

            return [
                'ttc' => $ttc,
                'ht' => round($ttc / $factor, 2),
                'source' => self::PRICE_SOURCE_LAST_PURCHASE,
                'has_cost' => true,
            ];
        }

        $costTtc = $product->getAttribute('cost_price_ttc');
        if ($costTtc !== null && $costTtc !== '') {
            $ttc = round((float) $costTtc, 2);

            return [
                'ttc' => $ttc,
                'ht' => round($ttc / $factor, 2),
                'source' => self::PRICE_SOURCE_PRODUCT_COST,
                'has_cost' => true,
            ];
        }

        if ($product->cost_price_ht !== null && $product->cost_price_ht !== '') {
            $ht = round((float) $product->cost_price_ht, 2);

            return [
                'ht' => $ht,
                'ttc' => round($ht * $factor, 2),
                'source' => self::PRICE_SOURCE_PRODUCT_COST,
                'has_cost' => true,
            ];
        }

        return [
            'ht' => null,
            'ttc' => null,
            'source' => self::PRICE_SOURCE_NONE,
            'has_cost' => false,
        ];
    }

    /**
     * @deprecated Prefer resolvePurchaseCost() — returns 0 only as « coût non renseigné ».
     */
    public function purchasePriceHt(Product $product): float
    {
        $cost = $this->resolvePurchaseCost($product);

        return $cost['has_cost'] ? (float) $cost['ht'] : 0.0;
    }

    /**
     * PA TTC catalogue (jamais recalculé en ajoutant la TVA sur un montant déjà TTC).
     *
     * @deprecated Prefer resolvePurchaseCost() — returns 0 only as « coût non renseigné ».
     */
    public function purchasePriceTtc(Product $product, ?float $ignored = null): float
    {
        $cost = $this->resolvePurchaseCost($product);

        return $cost['has_cost'] ? (float) $cost['ttc'] : 0.0;
    }
}
