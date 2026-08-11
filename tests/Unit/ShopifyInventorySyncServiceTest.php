<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ShopifyInventorySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopifyInventorySyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_level_update_recalculates_product_stock(): void
    {
        $product = Product::create([
            'name' => 'Tapis Corsa',
            'ref' => 'FAST-TAP4D-072',
            'source' => 'shopify',
            'external_id' => '8161092665502',
            'item_kind' => Product::KIND_STOCKED,
            'stock_enligne' => 0,
            'stock_quantity' => 0,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'shopify_variant_id' => '44690536431774',
            'inventory_item_id' => '467001',
            'title' => 'Default Title',
            'sku' => 'FAST-TAP4D-072',
            'inventory_quantity' => 0,
        ]);

        $updated = app(ShopifyInventorySyncService::class)->applyInventoryLevelUpdate('467001', '999', 4);

        $this->assertNotNull($updated);
        $this->assertSame(4, (int) $updated->stock_enligne);
        $this->assertSame(4, (int) $updated->stock_quantity);
        $this->assertSame(4, (int) $product->variants()->value('inventory_quantity'));
    }
}
