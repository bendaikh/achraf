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

    public function test_manual_last_purchase_price_records_audit_and_derives_ht(): void
    {
        $user = \App\Models\User::factory()->create(['name' => 'Acheteur Test']);
        $product = Product::create([
            'name' => 'Produit PA',
            'ref' => 'PA-MANUAL-1',
            'item_kind' => Product::KIND_STOCKED,
            'vat_category' => 'TVA (20%)',
            'last_purchase_price' => null,
            'cost_price_ht' => null,
        ]);

        $cost = app(ProductPurchasePriceService::class)
            ->setManualLastPurchasePrice($product, 70.0, $user);

        $product->refresh();
        $this->assertSame(70.0, (float) $product->last_purchase_price);
        $this->assertNotNull($product->last_purchase_price_updated_at);
        $this->assertSame((int) $user->id, (int) $product->last_purchase_price_updated_by);
        $this->assertTrue($cost['has_cost']);
        $this->assertSame(70.0, $cost['ttc']);
        $this->assertSame(58.33, $cost['ht']);
    }
}
