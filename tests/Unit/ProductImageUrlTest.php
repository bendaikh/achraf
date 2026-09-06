<?php

namespace Tests\Unit;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_url_falls_back_to_shopify_cdn_when_local_file_is_missing(): void
    {
        Storage::fake('public');

        $product = Product::create([
            'name' => 'Produit image cassée',
            'ref' => 'IMG-404',
            'image' => 'products/shopify-123-missing.png',
            'shopify_image_url' => 'https://cdn.shopify.com/s/files/1/example.jpg',
            'source' => 'shopify',
            'external_id' => '123',
        ]);

        $this->assertSame(
            'https://cdn.shopify.com/s/files/1/example.jpg',
            $product->image_url
        );
    }

    public function test_image_url_uses_local_file_when_it_exists(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/shopify-123-ok.png', 'fake-image');

        $product = Product::create([
            'name' => 'Produit image ok',
            'ref' => 'IMG-OK',
            'image' => 'products/shopify-123-ok.png',
            'shopify_image_url' => 'https://cdn.shopify.com/s/files/1/example.jpg',
            'source' => 'shopify',
            'external_id' => '123',
        ]);

        $this->assertNotNull($product->image_url);
        $this->assertStringContainsString('shopify-123-ok.png', $product->image_url);
        $this->assertStringNotContainsString('cdn.shopify.com', $product->image_url);
    }
}
