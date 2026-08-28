<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class VariantCatalogSearch
{
    /**
     * Expand products into selectable catalog rows (one row per variant when applicable).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function expandProducts(Collection $products, string $priceMode = 'sale'): Collection
    {
        return $products->flatMap(function (Product $product) use ($priceMode) {
            if ($product->variants->count() > 1) {
                return $product->variants->map(fn (ProductVariant $variant) => self::mapVariantRow($product, $variant, $priceMode));
            }

            $variant = $product->variants->first();

            return [self::mapProductRow($product, $variant, $priceMode)];
        })->values();
    }

    /**
     * @return array<string, mixed>
     */
    public static function mapVariantRow(Product $product, ProductVariant $variant, string $priceMode = 'sale'): array
    {
        $sku = $variant->sku ?: $product->ref;
        $label = self::variantLabel($product, $variant, $sku);
        $prices = self::resolvePrices($product, $variant, $priceMode);

        return array_merge([
            'id' => 'v'.$variant->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'text' => $label,
            'name' => $product->name,
            'variant' => $variant->full_title,
            'ref' => $sku,
            'barcode' => $variant->barcode ?: $product->barcode,
            'vat_category' => $product->vat_category,
            'shopify_variant_id' => $variant->shopify_variant_id,
            'has_variants' => true,
            'stock' => $variant->totalStock(),
        ], $prices);
    }

    /**
     * @return array<string, mixed>
     */
    public static function mapProductRow(Product $product, ?ProductVariant $variant = null, string $priceMode = 'sale'): array
    {
        $sku = $variant?->sku ?: $product->ref;
        $label = trim($product->name.($sku ? ' ('.$sku.')' : ''));
        $prices = self::resolvePrices($product, $variant, $priceMode);

        return array_merge([
            'id' => (string) $product->id,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'text' => $label,
            'name' => $product->name,
            'variant' => null,
            'ref' => $sku,
            'barcode' => $variant?->barcode ?: $product->barcode,
            'vat_category' => $product->vat_category,
            'shopify_variant_id' => $variant?->shopify_variant_id,
            'has_variants' => false,
            'stock' => (int) $product->stock_quantity,
        ], $prices);
    }

    public static function variantLabel(Product $product, ProductVariant $variant, ?string $sku = null): string
    {
        $sku = $sku ?: ($variant->sku ?: $product->ref);
        $variantName = $variant->full_title;

        return trim($product->name.' – '.$variantName.($sku ? ' – '.$sku : ''));
    }

    /**
     * @return array{sale_price_ht: float, sale_price: float, cost_price_ht: float, cost_price_ttc: float, last_purchase_price: ?float}
     */
    protected static function resolvePrices(Product $product, ?ProductVariant $variant, string $priceMode): array
    {
        $variantPrice = $variant?->price;

        if ($priceMode === 'purchase') {
            return [
                'sale_price_ht' => (float) ($product->sale_price_ht ?? 0),
                'sale_price' => (float) ($product->sale_price ?? 0),
                'cost_price_ht' => (float) ($product->cost_price_ht ?? 0),
                'cost_price_ttc' => (float) ($product->cost_price_ttc ?? 0),
                'last_purchase_price' => $product->last_purchase_price !== null
                    ? (float) $product->last_purchase_price
                    : null,
            ];
        }

        $salePrice = $variantPrice !== null ? (float) $variantPrice : (float) ($product->sale_price ?? 0);
        $salePriceHt = $product->sale_price_ht !== null && $variantPrice === null
            ? (float) $product->sale_price_ht
            : ($salePrice > 0 ? round($salePrice / 1.2, 2) : 0.0);

        return [
            'sale_price_ht' => $salePriceHt,
            'sale_price' => $salePrice,
            'cost_price_ht' => (float) ($product->cost_price_ht ?? 0),
            'cost_price_ttc' => (float) ($product->cost_price_ttc ?? 0),
            'last_purchase_price' => $product->last_purchase_price !== null
                ? (float) $product->last_purchase_price
                : null,
        ];
    }

    public static function activeProductQuery(): Builder
    {
        return Product::query()
            ->where(function (Builder $statusQuery) {
                $statusQuery->where('status', 'Activer')->orWhereNull('status');
            });
    }
}
