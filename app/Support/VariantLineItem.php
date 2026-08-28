<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Validation\Rule;

class VariantLineItem
{
    /**
     * @return array<string, mixed>
     */
    public static function validationRules(string $prefix = 'items.*'): array
    {
        return [
            $prefix.'.product_id' => 'nullable|exists:products,id',
            $prefix.'.product_variant_id' => [
                'nullable',
                'integer',
                Rule::exists('product_variants', 'id'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function normalize(array $item): array
    {
        $productId = isset($item['product_id']) ? (int) $item['product_id'] : null;
        $variantId = isset($item['product_variant_id']) && $item['product_variant_id'] !== ''
            ? (int) $item['product_variant_id']
            : null;

        if ($variantId && ! $productId) {
            $variant = ProductVariant::query()->find($variantId);
            $productId = $variant?->product_id;
        }

        if ($productId && $variantId) {
            $variant = ProductVariant::query()
                ->where('id', $variantId)
                ->where('product_id', $productId)
                ->first();

            if (! $variant) {
                $variantId = null;
            } else {
                $product = $variant->product;
                $item['ref'] = $item['ref'] ?? ($variant->sku ?: $product?->ref);
                $item['designation'] = $item['designation'] ?? VariantCatalogSearch::variantLabel(
                    $product ?? Product::find($productId),
                    $variant
                );
            }
        }

        $item['product_id'] = $productId;
        $item['product_variant_id'] = $variantId;

        return $item;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{product_id: ?int, product_variant_id: ?int, quantity: int}
     */
    public static function stockPayload(array $item): array
    {
        $normalized = self::normalize($item);

        return [
            'product_id' => $normalized['product_id'] ?? null,
            'product_variant_id' => $normalized['product_variant_id'] ?? null,
            'quantity' => (int) ($normalized['quantity'] ?? 0),
            'warehouse_id' => $normalized['warehouse_id'] ?? null,
            'warehouse_location_id' => $normalized['warehouse_location_id'] ?? ($normalized['location_id'] ?? null),
            'notes' => $normalized['notes'] ?? null,
        ];
    }
}
