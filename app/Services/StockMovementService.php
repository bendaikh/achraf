<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Collection;
use RuntimeException;

class StockMovementService
{
    public function isStockControlEnabled(): bool
    {
        return Setting::get('stock_control_enabled', '1') !== '0';
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
     */
    public function decreaseForSale(iterable $items, ?string $stockLocation): void
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
            if (! $product) {
                continue;
            }

            $qty = (int) ($item['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $this->decrease($product, $qty, $this->resolveChannel($stockLocation));
        }
    }

    /**
     * @param  iterable<int, array{product_id?: int|null, quantity: int}>  $items
     */
    public function increaseForPurchase(iterable $items, ?string $stockLocation): void
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
            if (! $product) {
                continue;
            }

            $qty = (int) ($item['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $this->increase($product, $qty, $this->resolveChannel($stockLocation));
        }
    }

    /**
     * Customer return (avoir client) — stock goes back in.
     *
     * @param  Collection<int, object>|iterable  $items
     */
    public function increaseFromItems(iterable $items, ?string $stockLocation): void
    {
        $rows = [];
        foreach ($items as $item) {
            $rows[] = ['product_id' => $item->product_id ?? null, 'quantity' => (int) $item->quantity];
        }
        $this->increaseForPurchase($rows, $stockLocation);
    }

    /**
     * Supplier return (avoir fournisseur) — stock goes out.
     *
     * @param  Collection<int, object>|iterable  $items
     */
    public function decreaseFromItems(iterable $items, ?string $stockLocation): void
    {
        $rows = [];
        foreach ($items as $item) {
            $rows[] = ['product_id' => $item->product_id ?? null, 'quantity' => (int) $item->quantity];
        }
        $this->decreaseForSale($rows, $stockLocation);
    }

    public function decrease(Product $product, int $quantity, string $channel): void
    {
        $field = $this->stockFieldForProduct($product, $channel);
        $current = (int) ($product->{$field} ?? 0);

        if ($current < $quantity) {
            throw new RuntimeException(
                'Stock insuffisant pour « '.$product->name.' » (disponible: '.$current.', demandé: '.$quantity.').'
            );
        }

        $product->{$field} = $current - $quantity;
        $this->syncAggregateStock($product, $field);
        $product->save();
    }

    public function increase(Product $product, int $quantity, string $channel): void
    {
        $field = $this->stockFieldForProduct($product, $channel);
        $product->{$field} = (int) ($product->{$field} ?? 0) + $quantity;
        $this->syncAggregateStock($product, $field);
        $product->save();
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
