<?php

namespace App\Services;

use App\Models\JumiaIntegration;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PosSale;
use App\Services\Jumia\JumiaApiClient;
use App\Support\OrderSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarketplaceStockSyncService
{
    public function __construct(
        protected StockMovementService $stockMovement
    ) {}

    public function isEnabled(): bool
    {
        return $this->stockMovement->isStockControlEnabled();
    }

    public function findProductBySku(string $sku): ?Product
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        $product = Product::query()
            ->whereRaw('LOWER(ref) = ?', [strtolower($sku)])
            ->first();

        if ($product) {
            return $product;
        }

        $variant = ProductVariant::query()
            ->whereRaw('LOWER(sku) = ?', [strtolower($sku)])
            ->with('product')
            ->first();

        return $variant?->product;
    }

    /**
     * Apply stock adjustments for a marketplace order using delta logic to avoid double-counting on re-sync.
     *
     * @param  array<string, int>  $previousApplied
     * @param  array<string, int>  $currentQuantities
     * @return array{applied: array<string, int>, errors: array<string, string>}
     */
    public function syncOrderStock(
        PosSale $sale,
        array $previousApplied,
        array $currentQuantities,
        string $source
    ): array {
        if (! $this->isEnabled()) {
            return ['applied' => $currentQuantities, 'errors' => []];
        }

        $previousApplied = $this->normalizeSkuQuantities($previousApplied);
        $currentQuantities = $this->normalizeSkuQuantities($currentQuantities);

        $allSkus = array_unique(array_merge(array_keys($previousApplied), array_keys($currentQuantities)));
        $applied = [];
        $errors = [];
        $productsToPush = [];

        foreach ($allSkus as $sku) {
            $previousQty = $previousApplied[$sku] ?? 0;
            $currentQty = $currentQuantities[$sku] ?? 0;
            $delta = $currentQty - $previousQty;

            if ($delta === 0) {
                if ($currentQty > 0) {
                    $applied[$sku] = $currentQty;
                }

                continue;
            }

            $product = $this->findProductBySku($sku);
            if (! $product) {
                $errors[$sku] = 'Produit introuvable pour le SKU « '.$sku.' ».';
                Log::warning('Marketplace stock sync: SKU not found', [
                    'sale_id' => $sale->id,
                    'source' => $source,
                    'sku' => $sku,
                    'delta' => $delta,
                ]);

                if ($currentQty > 0) {
                    $applied[$sku] = $currentQty;
                }

                continue;
            }

            try {
                $product = Product::query()->lockForUpdate()->find($product->id);
                if (! $product) {
                    continue;
                }

                if ($delta > 0) {
                    $this->decreaseForMarketplace($product, $delta);
                } else {
                    $this->stockMovement->increase($product, abs($delta), 'enligne');
                }

                $product->refresh();
                $productsToPush[$product->id] = $product;

                if ($currentQty > 0) {
                    $applied[$sku] = $currentQty;
                }
            } catch (\Throwable $e) {
                $errors[$sku] = $e->getMessage();
                Log::error('Marketplace stock sync failed for SKU', [
                    'sale_id' => $sale->id,
                    'source' => $source,
                    'sku' => $sku,
                    'delta' => $delta,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->persistStockMetadata($sale, $applied, $errors);

        if ($source === OrderSource::SHOPIFY && $productsToPush !== []) {
            DB::afterCommit(function () use ($productsToPush): void {
                $this->pushStockToJumia(array_values($productsToPush));
            });
        }

        return ['applied' => $applied, 'errors' => $errors];
    }

    /**
     * @param  array<int, Product>  $products
     */
    public function pushStockToJumia(array $products): void
    {
        $integration = JumiaIntegration::query()
            ->where('enabled', true)
            ->first();

        if (! $integration || ! $integration->isConfigured()) {
            Log::info('Jumia stock push skipped: integration not configured or disabled.');

            return;
        }

        $client = new JumiaApiClient($integration);

        foreach ($products as $product) {
            $sku = trim((string) $product->ref);
            if ($sku === '') {
                continue;
            }

            $stock = max(0, (int) $product->stock_enligne);

            try {
                $client->updateProductStock($sku, $stock, $product->jumia_product_sid);
                $product->forceFill(['jumia_stock_synced_at' => now()])->save();

                Log::info('Jumia stock updated', [
                    'product_id' => $product->id,
                    'sku' => $sku,
                    'stock' => $stock,
                ]);
            } catch (\Throwable $e) {
                Log::error('Jumia stock push failed', [
                    'product_id' => $product->id,
                    'sku' => $sku,
                    'stock' => $stock,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  iterable<int, array{ref?: string|null, quantity: int}>  $lineRows
     * @return array<string, int>
     */
    public function quantitiesFromLineRows(iterable $lineRows): array
    {
        $quantities = [];

        foreach ($lineRows as $row) {
            $sku = trim((string) ($row['ref'] ?? ''));
            if ($sku === '') {
                continue;
            }

            $quantities[$sku] = ($quantities[$sku] ?? 0) + max(0, (int) ($row['quantity'] ?? 0));
        }

        return $quantities;
    }

    /**
     * @return array<string, int>
     */
    public function previousAppliedFromSale(?PosSale $sale): array
    {
        if (! $sale) {
            return [];
        }

        $metadata = $sale->external_metadata ?? [];
        $applied = $metadata['stock_applied'] ?? [];

        return is_array($applied) ? $this->normalizeSkuQuantities($applied) : [];
    }

    protected function decreaseForMarketplace(Product $product, int $quantity): void
    {
        $field = 'stock_enligne';
        $current = (int) ($product->{$field} ?? 0);

        if ($current < $quantity) {
            Log::warning('Marketplace stock insufficient — clamping to zero', [
                'product_id' => $product->id,
                'sku' => $product->ref,
                'available' => $current,
                'requested' => $quantity,
            ]);
        }

        $product->{$field} = max(0, $current - $quantity);
        $product->stock_quantity = (int) $product->stock_enligne;
        $product->save();
    }

    /**
     * @param  array<string, int>  $applied
     * @param  array<string, string>  $errors
     */
    protected function persistStockMetadata(PosSale $sale, array $applied, array $errors): void
    {
        $metadata = $sale->external_metadata ?? [];
        $metadata['stock_applied'] = $applied;
        $metadata['stock_synced_at'] = now()->toIso8601String();

        if ($errors !== []) {
            $metadata['stock_sync_errors'] = $errors;
        } else {
            unset($metadata['stock_sync_errors']);
        }

        $sale->forceFill(['external_metadata' => $metadata])->save();
    }

    /**
     * @param  array<string, int>  $quantities
     * @return array<string, int>
     */
    protected function normalizeSkuQuantities(array $quantities): array
    {
        $normalized = [];

        foreach ($quantities as $sku => $qty) {
            $sku = trim((string) $sku);
            if ($sku === '') {
                continue;
            }

            $normalized[$sku] = max(0, (int) $qty);
        }

        return $normalized;
    }
}
