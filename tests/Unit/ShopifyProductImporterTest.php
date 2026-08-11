<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\ShopifyProductImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopifyProductImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_sums_inventory_across_all_variants(): void
    {
        $product = app(ShopifyProductImporter::class)->import([
            'id' => 111,
            'title' => 'AlloyGator',
            'status' => 'active',
            'variants' => [
                [
                    'id' => 1,
                    'sku' => 'RED-12',
                    'price' => '10.00',
                    'inventory_quantity' => 5,
                    'inventory_management' => 'shopify',
                    'inventory_item_id' => 101,
                    'title' => 'Red',
                ],
                [
                    'id' => 2,
                    'sku' => 'BLU-12',
                    'price' => '10.00',
                    'inventory_quantity' => 60,
                    'inventory_management' => 'shopify',
                    'inventory_item_id' => 102,
                    'title' => 'Blue',
                ],
            ],
        ]);

        $this->assertSame(65, (int) $product->stock_enligne);
        $this->assertSame(65, (int) $product->stock_quantity);
        $this->assertSame('101', $product->variants()->where('sku', 'RED-12')->value('inventory_item_id'));
    }

    public function test_import_updates_stock_for_jumia_linked_products_from_shopify(): void
    {
        $existing = Product::create([
            'name' => 'Tapis sur mesure 4D Opel Corsa F 2019 >',
            'ref' => 'FAST-TAP4D-072',
            'source' => 'shopify',
            'external_id' => '8161092665502',
            'item_kind' => Product::KIND_STOCKED,
            'stock_enligne' => 0,
            'stock_quantity' => 0,
            'jumia_product_sid' => 'c623c35b-5d27-4ac7-9841-3f3ee6df7720',
        ]);

        $product = app(ShopifyProductImporter::class)->import([
            'id' => 8161092665502,
            'title' => 'Tapis sur mesure 4D Opel Corsa F 2019 >',
            'status' => 'active',
            'variants' => [
                [
                    'id' => 44690536431774,
                    'sku' => 'FAST-TAP4D-072',
                    'price' => '450.00',
                    'inventory_quantity' => 4,
                    'inventory_management' => 'shopify',
                    'inventory_item_id' => 467001,
                    'title' => 'Default Title',
                ],
            ],
        ]);

        $this->assertTrue($product->is($existing));
        $this->assertSame(4, (int) $product->fresh()->stock_enligne);
        $this->assertSame(4, (int) $product->fresh()->stock_quantity);
    }
}
