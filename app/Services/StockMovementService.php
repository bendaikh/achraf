<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
     * @return list<string>
     */
    public function decreaseForSale(iterable $items, ?string $stockLocation, bool $strict = true): array
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

            $warning = $this->decrease($product, $qty, $this->resolveChannel($stockLocation), $strict);
            if ($warning !== null) {
                $warnings[] = $warning;
            }
        }

        return $warnings;
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
            if (! $product || ! $product->tracksStock()) {
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

    public function decrease(Product $product, int $quantity, string $channel, bool $strict = true, bool $syncShopify = true): ?string
    {
        if (! $product->tracksStock()) {
            return null;
        }

        $field = $this->stockFieldForProduct($product, $channel);
        $current = (int) ($product->{$field} ?? 0);
        $warning = null;

        if ($current < $quantity) {
            $warning = 'Stock insuffisant pour « '.$product->name.' » (disponible: '.$current.', demandé: '.$quantity.').';

            if ($strict) {
                throw new RuntimeException($warning);
            }
        }

        $product->{$field} = $current - $quantity;
        $this->syncAggregateStock($product, $field);
        $product->save();
        $this->pushEnligneStockToJumia($product, $field);
        $this->pushEnligneStockToShopify($product, $field, $syncShopify);

        return $warning;
    }

    public function increase(Product $product, int $quantity, string $channel, bool $syncShopify = true): void
    {
        if (! $product->tracksStock()) {
            return;
        }

        $field = $this->stockFieldForProduct($product, $channel);
        $product->{$field} = (int) ($product->{$field} ?? 0) + $quantity;
        $this->syncAggregateStock($product, $field);
        $product->save();
        $this->pushEnligneStockToJumia($product, $field);
        $this->pushEnligneStockToShopify($product, $field, $syncShopify);
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

    protected function pushEnligneStockToShopify(Product $product, string $field, bool $enabled): void
    {
        if (! $enabled || $field !== 'stock_enligne' || ! $product->isShopifyProduct()) {
            return;
        }

        $productId = $product->id;

        DB::afterCommit(function () use ($productId): void {
            try {
                $fresh = Product::query()->find($productId);
                if (! $fresh) {
                    return;
                }

                app(ShopifyInventorySyncService::class)->pushProductStock($fresh);
            } catch (\Throwable $e) {
                Log::warning('Shopify stock push after local stock change failed', [
                    'product_id' => $productId,
                    'message' => $e->getMessage(),
                ]);
            }
        });
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
