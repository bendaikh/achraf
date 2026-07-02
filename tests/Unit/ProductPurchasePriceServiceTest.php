<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\ProductPurchasePriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPurchasePriceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_last_purchase_prices_updates_linked_products(): void
    {
        $product = Product::create([
            'name' => 'Test Product',
            'ref' => 'TEST-001',
            'last_purchase_price' => null,
        ]);

        app(ProductPurchasePriceService::class)->syncLastPurchasePrices([
            ['product_id' => $product->id, 'unit_price' => 125.50],
            ['product_id' => null, 'unit_price' => 999],
        ]);

        $this->assertEquals(125.50, (float) $product->fresh()->last_purchase_price);
    }
}
