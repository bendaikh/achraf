<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\ProductCompatibilityService;
use App\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCompatibilityAndBulkAssignTest extends TestCase
{
    use RefreshDatabase;

    public function test_compatibility_links_are_bidirectional_and_do_not_merge_stock(): void
    {
        $f40 = Product::create([
            'name' => 'Tapis BMW Série 1 F40',
            'ref' => 'TAP-BMW-F40',
            'item_kind' => Product::KIND_STOCKED,
            'status' => 'Activer',
            'stock_quantity' => 0,
            'stock_reserved' => 0,
        ]);
        $f70 = Product::create([
            'name' => 'Tapis BMW Série 1 F70',
            'ref' => 'TAP-BMW-F70',
            'item_kind' => Product::KIND_STOCKED,
            'status' => 'Activer',
            'stock_quantity' => 3,
            'stock_reserved' => 0,
        ]);

        app(ProductCompatibilityService::class)->sync($f40, [$f70->id]);

        $this->assertTrue($f40->fresh()->compatibleProducts()->where('products.id', $f70->id)->exists());
        $this->assertTrue($f70->fresh()->compatibleProducts()->where('products.id', $f40->id)->exists());
        $this->assertSame(0, $f40->fresh()->availableStock());
        $this->assertSame(3, $f70->fresh()->availableStock());

        app(ProductCompatibilityService::class)->sync($f40, []);

        $this->assertFalse($f40->fresh()->compatibleProducts()->where('products.id', $f70->id)->exists());
        $this->assertFalse($f70->fresh()->compatibleProducts()->where('products.id', $f40->id)->exists());
        $this->assertDatabaseHas('products', ['id' => $f40->id, 'stock_quantity' => 0]);
        $this->assertDatabaseHas('products', ['id' => $f70->id, 'stock_quantity' => 3]);
    }

    public function test_product_search_includes_compatible_hits(): void
    {
        $user = User::factory()->create();
        $f40 = Product::create([
            'name' => 'Tapis BMW Série 1 F40',
            'ref' => 'TAP-BMW-F40',
            'item_kind' => Product::KIND_STOCKED,
            'status' => 'Activer',
        ]);
        $f70 = Product::create([
            'name' => 'Tapis BMW Série 1 F70',
            'ref' => 'TAP-BMW-F70',
            'item_kind' => Product::KIND_STOCKED,
            'status' => 'Activer',
            'stock_quantity' => 3,
        ]);
        app(ProductCompatibilityService::class)->sync($f40, [$f70->id]);

        $response = $this->actingAs($user)->get(route('products.index', [
            'search' => 'F40',
            'result_type' => 'all',
        ]));

        $response->assertOk();
        $response->assertSee('Tapis BMW Série 1 F40', false);
        $response->assertSee('Tapis BMW Série 1 F70', false);
        $response->assertSee('Compatible / équivalent', false);
    }

    public function test_bulk_assign_updates_default_warehouse_without_moving_stock(): void
    {
        $user = User::factory()->create();

        $source = Warehouse::query()->create([
            'name' => 'Dépôt A',
            'code' => 'DEP-A',
            'kind' => Warehouse::KIND_PHYSICAL,
            'status' => Warehouse::STATUS_ACTIVE,
            'is_primary' => true,
        ]);
        $target = Warehouse::query()->create([
            'name' => 'MAGASIN BELVÉDÈRE',
            'code' => 'BEL',
            'kind' => Warehouse::KIND_PHYSICAL,
            'status' => Warehouse::STATUS_ACTIVE,
            'is_primary' => false,
        ]);
        $sourceLoc = WarehouseLocation::query()->create([
            'warehouse_id' => $source->id,
            'code' => 'A-STOCK',
            'name' => 'A Stock',
            'status' => WarehouseLocation::STATUS_ACTIVE,
        ]);
        $targetLoc = WarehouseLocation::query()->create([
            'warehouse_id' => $target->id,
            'code' => 'BEL-STOCK',
            'name' => 'Bel Stock',
            'status' => WarehouseLocation::STATUS_ACTIVE,
        ]);

        $product = Product::create([
            'name' => 'Produit test',
            'ref' => 'PROD-1',
            'item_kind' => Product::KIND_STOCKED,
            'status' => 'Activer',
            'warehouse_id' => $source->id,
            'warehouse_location_id' => $sourceLoc->id,
            'depot' => $source->name,
            'location' => $sourceLoc->code,
            'stock_quantity' => 5,
        ]);

        app(StockMovementService::class)->assignDefaultWarehouseWithoutMovingStock(
            $product,
            $source->id,
            $sourceLoc->id
        );
        $product->save();

        $product->stocks()->updateOrCreate(
            [
                'warehouse_id' => $source->id,
                'warehouse_location_id' => $sourceLoc->id,
                'product_variant_id' => null,
            ],
            ['quantity' => 5, 'reserved' => 0]
        );

        $response = $this->actingAs($user)->postJson(route('products.bulk-assign-warehouse'), [
            'ids' => [$product->id],
            'warehouse_id' => $target->id,
            'warehouse_location_id' => $targetLoc->id,
        ]);

        $response->assertOk();
        $product->refresh();

        $this->assertSame($target->id, (int) $product->warehouse_id);
        $this->assertSame($targetLoc->id, (int) $product->warehouse_location_id);
        $this->assertSame('MAGASIN BELVÉDÈRE', $product->depot);
        $this->assertSame('BEL-STOCK', $product->location);

        $sourceQty = (int) $product->stocks()
            ->where('warehouse_id', $source->id)
            ->where('warehouse_location_id', $sourceLoc->id)
            ->value('quantity');
        $this->assertSame(5, $sourceQty, 'Physical stock must stay on the source depot');
        $this->assertTrue($response->json('stock_elsewhere_count') >= 1);
    }
}
