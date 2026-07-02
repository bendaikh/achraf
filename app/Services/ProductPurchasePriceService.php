<?php

namespace App\Services;

use App\Models\Product;

class ProductPurchasePriceService
{
    /**
     * @param  iterable<array<string, mixed>|object>  $items
     */
    public function syncLastPurchasePrices(iterable $items): void
    {
        foreach ($items as $item) {
            $productId = is_array($item)
                ? ($item['product_id'] ?? null)
                : ($item->product_id ?? null);

            if (! $productId) {
                continue;
            }

            $unitPrice = is_array($item)
                ? ($item['unit_price'] ?? null)
                : ($item->unit_price ?? null);

            if ($unitPrice === null) {
                continue;
            }

            Product::query()
                ->whereKey($productId)
                ->update(['last_purchase_price' => round((float) $unitPrice, 2)]);
        }
    }
}
