<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VariantStockMigrationService
{
    /**
     * @return array{
     *     migrated: int,
     *     manual_review: list<array<string, mixed>>,
     *     missing_sku: list<array<string, mixed>>,
     *     missing_shopify_variant_id: list<array<string, mixed>>,
     *     duplicates: list<array<string, mixed>>
     * }
     */
    public function migrate(bool $dryRun = false): array
    {
        $report = [
            'migrated' => 0,
            'manual_review' => [],
            'missing_sku' => [],
            'missing_shopify_variant_id' => [],
            'duplicates' => [],
        ];

        $onlineWarehouse = Warehouse::onlineWarehouse();

        Product::query()
            ->with(['variants'])
            ->whereHas('variants', fn ($query) => $query->havingRaw('COUNT(*) > 1'))
            ->orderBy('id')
            ->chunkById(50, function ($products) use (&$report, $dryRun, $onlineWarehouse) {
                foreach ($products as $product) {
                    $this->migrateProduct($product, $report, $dryRun, $onlineWarehouse);
                }
            });

        $this->detectDuplicateSkus($report);

        return $report;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    protected function migrateProduct(Product $product, array &$report, bool $dryRun, ?Warehouse $onlineWarehouse): void
    {
        $variants = $product->variants->sortBy('position')->values();

        foreach ($variants as $variant) {
            if (! filled($variant->sku)) {
                $report['missing_sku'][] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'variant_id' => $variant->id,
                    'variant_title' => $variant->full_title,
                ];
            }

            if ($product->isShopifyProduct() && ! filled($variant->shopify_variant_id)) {
                $report['missing_shopify_variant_id'][] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'variant_id' => $variant->id,
                    'variant_title' => $variant->full_title,
                ];
            }
        }

        if ($dryRun) {
            $report['migrated']++;

            return;
        }

        DB::transaction(function () use ($product, $variants, &$report, $onlineWarehouse) {
            if ($onlineWarehouse) {
                $this->migrateOnlineStock($product, $variants, (int) $onlineWarehouse->id);
            }

            $physicalQty = (int) ProductStock::query()
                ->where('product_id', $product->id)
                ->whereNull('product_variant_id')
                ->when($onlineWarehouse, fn ($query) => $query->where('warehouse_id', '!=', $onlineWarehouse->id))
                ->sum('quantity');

            if ($physicalQty > 0) {
                $report['manual_review'][] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'reason' => 'Stock physique agrégé ('.$physicalQty.' unités) à répartir manuellement entre les variantes.',
                ];
            }

            $report['migrated']++;
        });
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     */
    protected function migrateOnlineStock(Product $product, Collection $variants, int $onlineWarehouseId): void
    {
        $aggregateSlots = ProductStock::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $onlineWarehouseId)
            ->whereNull('product_variant_id')
            ->get();

        foreach ($variants as $variant) {
            $qty = max(0, (int) $variant->inventory_quantity);
            $slot = ProductStock::query()->firstOrCreate(
                [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'warehouse_id' => $onlineWarehouseId,
                    'warehouse_location_id' => null,
                ],
                ['quantity' => 0, 'reserved' => 0]
            );

            if ((int) $slot->quantity !== $qty) {
                $slot->quantity = $qty;
                $slot->save();
            }
        }

        foreach ($aggregateSlots as $slot) {
            if ((int) $slot->quantity === 0 && (int) $slot->reserved === 0) {
                $slot->delete();
            } elseif ((int) $slot->quantity > 0) {
                Log::info('Variant migration: removed aggregate online slot after split', [
                    'product_id' => $product->id,
                    'slot_id' => $slot->id,
                    'quantity' => $slot->quantity,
                ]);
                $slot->delete();
            }
        }

        $product = $product->fresh();
        $onlineIds = Warehouse::query()->online()->pluck('id');
        $product->stock_enligne = (int) ProductStock::query()
            ->where('product_id', $product->id)
            ->when($onlineIds->isNotEmpty(), fn ($q) => $q->whereIn('warehouse_id', $onlineIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->sum('quantity');
        $product->stock_quantity = $product->stock_enligne;
        $product->save();
    }

    /**
     * @param  array<string, mixed>  $report
     */
    protected function detectDuplicateSkus(array &$report): void
    {
        $duplicates = ProductVariant::query()
            ->select('sku')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->groupBy('sku')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('sku');

        foreach ($duplicates as $sku) {
            $variants = ProductVariant::query()->where('sku', $sku)->with('product')->get();
            $report['duplicates'][] = [
                'sku' => $sku,
                'variants' => $variants->map(fn (ProductVariant $variant) => [
                    'variant_id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'product_name' => $variant->product?->name,
                    'variant_title' => $variant->full_title,
                ])->all(),
            ];
        }
    }
}
