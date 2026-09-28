<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class StockMovementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_strict_decrease_blocks_when_stock_is_insufficient(): void
    {
        $product = $this->shopifyProduct(stock: 0);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stock insuffisant');

        app(StockMovementService::class)->decreaseForSale(
            [['product_id' => $product->id, 'quantity' => 1]],
            'DEPOT'
        );
    }

    public function test_non_strict_decrease_warns_and_allows_negative_stock(): void
    {
        $product = $this->shopifyProduct(stock: 0);

        $warnings = app(StockMovementService::class)->decreaseForSale(
            [['product_id' => $product->id, 'quantity' => 1]],
            'DEPOT',
            strict: false
        );

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('Tapis Corsa', $warnings[0]);
        $this->assertStringContainsString('disponible: 0', $warnings[0]);
        $this->assertStringContainsString('demandé: 1', $warnings[0]);

        $product->refresh();
        $this->assertSame(-1, (int) $product->stock_enligne);
        $this->assertSame(-1, (int) $product->stock_quantity);
    }

    public function test_non_strict_decrease_still_deducts_available_units(): void
    {
        $product = $this->shopifyProduct(stock: 2);

        $warnings = app(StockMovementService::class)->decreaseForSale(
            [['product_id' => $product->id, 'quantity' => 5]],
            'DEPOT',
            strict: false
        );

        $this->assertNotEmpty($warnings);
        $product->refresh();
        $this->assertSame(-3, (int) $product->stock_enligne);
    }

    public function test_depot_location_uses_enligne_stock_for_shopify_products(): void
    {
        $product = $this->shopifyProduct(stock: 4);

        app(StockMovementService::class)->decreaseForSale(
            [['product_id' => $product->id, 'quantity' => 1]],
            'DEPOT'
        );

        $product->refresh();
        $this->assertSame(3, (int) $product->stock_enligne);
        $this->assertSame(3, (int) $product->stock_quantity);
    }

    public function test_purchase_adopts_legacy_null_variant_slot_instead_of_duplicate_insert(): void
    {
        $warehouse = Warehouse::fulfillmentWarehouse() ?? Warehouse::query()->first();
        $this->assertNotNull($warehouse);

        $location = WarehouseLocation::query()->firstOrCreate(
            ['warehouse_id' => $warehouse->id, 'code' => 'LEGACY-01'],
            ['name' => 'Emplacement legacy', 'zone' => 'A', 'status' => 'active']
        );

        $product = Product::create([
            'name' => 'Tapis sur mesure',
            'ref' => 'FAST-TAP4D-023',
            'source' => 'shopify',
            'external_id' => '999000111',
            'item_kind' => Product::KIND_STOCKED,
            'stock_enligne' => 0,
            'stock_quantity' => 0,
            'stock_magasin' => 0,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'title' => 'Default Title',
            'sku' => $product->ref,
            'inventory_quantity' => 0,
        ]);

        // Legacy row: same unique slot key, but product_variant_id is null.
        $legacy = ProductStock::create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'warehouse_id' => $warehouse->id,
            'warehouse_location_id' => $location->id,
            'quantity' => 0,
            'reserved' => 0,
        ]);

        app(StockMovementService::class)->increaseForPurchase(
            [[
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'quantity' => 5,
                'warehouse_id' => $warehouse->id,
                'warehouse_location_id' => $location->id,
            ]],
            $warehouse->name,
            'supplier_delivery_note',
            1,
            'BL-TEST-1',
            $warehouse->id
        );

        $this->assertSame(1, ProductStock::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('warehouse_location_id', $location->id)
            ->count());

        $legacy->refresh();
        $this->assertSame($variant->id, (int) $legacy->product_variant_id);
        $this->assertSame(5, (int) $legacy->quantity);
    }

    private function shopifyProduct(int $stock): Product
    {
        return Product::create([
            'name' => 'Tapis Corsa',
            'ref' => 'FAST-TAP4D-072',
            'source' => 'shopify',
            'external_id' => '8161092665502',
            'item_kind' => Product::KIND_STOCKED,
            'stock_enligne' => $stock,
            'stock_quantity' => $stock,
            'stock_magasin' => 0,
        ]);
    }
}
