<?php

namespace Tests\Unit;

use App\Models\Product;
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
