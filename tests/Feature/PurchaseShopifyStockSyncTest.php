<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShopifyIntegration;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PurchaseShopifyStockSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_invoice_to_online_warehouse_syncs_simple_shopify_product(): void
    {
        $this->fakeShopifyApi();
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Fournisseur Shopify']);
        $online = Warehouse::onlineWarehouse();
        $this->assertNotNull($online);

        $product = $this->shopifyProduct(['name' => 'Tapis Tiguan 2024', 'ref' => 'TIGUAN-2024']);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'shopify_variant_id' => '111',
            'inventory_item_id' => '900001',
            'title' => 'Default Title',
            'sku' => $product->ref,
            'inventory_quantity' => 0,
        ]);

        $this->actingAs($user)->post(route('supplier-invoices.store'), [
            'invoice_number' => 'FSI-SHOPIFY-SIMPLE',
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-09-18',
            'currency' => 'dh - MAD',
            'warehouse_id' => $online->id,
            'items' => [[
                'product_id' => $product->id,
                'ref' => $product->ref,
                'designation' => $product->name,
                'quantity' => 36,
                'unit_price' => 100,
                'tax_rate' => 20,
                'discount' => 0,
                'discount_type' => 'fixed',
                'warehouse_id' => $online->id,
            ]],
        ])->assertRedirect();

        $invoice = SupplierInvoice::query()->firstOrFail();
        $this->assertNotNull($invoice->stock_applied_at);
        $this->assertSame(36, app(StockMovementService::class)->quantityAtWarehouse($product->fresh(), (int) $online->id));
        $this->assertSame(36, (int) $product->fresh()->stock_enligne);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'inventory_levels/set.json')
                && (int) data_get($request->data(), 'inventory_item_id') === 900001
                && (int) data_get($request->data(), 'available') === 36;
        });

        $this->assertSame(36, (int) $variant->fresh()->inventory_quantity);
    }

    public function test_invoice_to_online_warehouse_updates_only_selected_variant(): void
    {
        $this->fakeShopifyApi();
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Fournisseur Variantes']);
        $online = Warehouse::onlineWarehouse();

        $product = $this->shopifyProduct(['name' => 'Tapis Tiguan 2024', 'ref' => 'TIGUAN-VAR']);
        $black = ProductVariant::create([
            'product_id' => $product->id,
            'shopify_variant_id' => '123456',
            'inventory_item_id' => '900011',
            'title' => 'Noir',
            'option1' => 'Noir',
            'sku' => 'TIGUAN-NOIR',
            'inventory_quantity' => 10,
            'position' => 1,
        ]);
        $grey = ProductVariant::create([
            'product_id' => $product->id,
            'shopify_variant_id' => '123457',
            'inventory_item_id' => '900012',
            'title' => 'Gris',
            'option1' => 'Gris',
            'sku' => 'TIGUAN-GRIS',
            'inventory_quantity' => 5,
            'position' => 2,
        ]);

        // Seed existing online stock: noir=10 already on Shopify side (local slot).
        app(StockMovementService::class)->increase(
            $product,
            10,
            'enligne',
            false,
            \App\Models\StockMovement::TYPE_INVENTORY_ADJUSTMENT,
            'seed',
            null,
            null,
            $online->id,
            null,
            null,
            null,
            (int) $black->id
        );

        $this->actingAs($user)->post(route('supplier-invoices.store'), [
            'invoice_number' => 'FSI-SHOPIFY-VARIANT',
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-09-18',
            'currency' => 'dh - MAD',
            'warehouse_id' => $online->id,
            'items' => [[
                'product_id' => $product->id,
                'product_variant_id' => $black->id,
                'ref' => $black->sku,
                'designation' => 'Tapis Tiguan 2024 – Noir',
                'quantity' => 36,
                'unit_price' => 100,
                'tax_rate' => 20,
                'discount' => 0,
                'discount_type' => 'fixed',
                'warehouse_id' => $online->id,
            ]],
        ])->assertRedirect();

        $service = app(StockMovementService::class);
        $this->assertSame(46, $black->fresh()->onlineStock());
        $this->assertSame(0, $grey->fresh()->onlineStock());

        $invoice = SupplierInvoice::query()->where('invoice_number', 'FSI-SHOPIFY-VARIANT')->firstOrFail();
        $this->assertSame((int) $black->id, (int) $invoice->items()->first()->product_variant_id);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'inventory_levels/set.json')
                && (int) data_get($request->data(), 'inventory_item_id') === 900011
                && (int) data_get($request->data(), 'available') === 46;
        });

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), 'inventory_levels/set.json')
                && (int) data_get($request->data(), 'inventory_item_id') === 900012;
        });

        $this->assertSame(46, (int) $black->fresh()->inventory_quantity);
        $this->assertSame(5, (int) $grey->fresh()->inventory_quantity);
    }

    public function test_br_then_invoice_conversion_does_not_double_stock_or_shopify_push(): void
    {
        $this->fakeShopifyApi();
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Fournisseur BR']);
        $online = Warehouse::onlineWarehouse();
        $product = $this->shopifyProduct(['name' => 'Produit BR', 'ref' => 'BR-SKU']);
        ProductVariant::create([
            'product_id' => $product->id,
            'shopify_variant_id' => '222',
            'inventory_item_id' => '900021',
            'title' => 'Default Title',
            'sku' => $product->ref,
            'inventory_quantity' => 0,
        ]);

        $this->actingAs($user)->post(route('receptions.store'), [
            'reception_number' => 'BR-SHOPIFY-1',
            'supplier_id' => $supplier->id,
            'reception_date' => '2026-09-18',
            'currency' => 'dh - MAD',
            'status' => 'accepté',
            'warehouse_id' => $online->id,
            'items' => [[
                'product_id' => $product->id,
                'ref' => $product->ref,
                'designation' => $product->name,
                'quantity' => 12,
                'unit_price' => 10,
                'tax_rate' => 20,
                'discount' => 0,
                'discount_type' => 'fixed',
                'warehouse_id' => $online->id,
            ]],
        ])->assertRedirect();

        $this->assertSame(12, app(StockMovementService::class)->quantityAtWarehouse($product->fresh(), (int) $online->id));

        $setCallsBefore = collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), 'inventory_levels/set.json'))
            ->count();

        $reception = \App\Models\Reception::query()->firstOrFail();
        $this->actingAs($user)->postJson(route('receptions.bulk-convert'), [
            'ids' => [$reception->id],
            'mode' => 'separate',
        ])->assertOk();

        $this->assertSame(12, app(StockMovementService::class)->quantityAtWarehouse($product->fresh(), (int) $online->id));
        $invoice = SupplierInvoice::query()->firstOrFail();
        $this->assertNotNull($invoice->stock_applied_at);

        $setCallsAfter = collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), 'inventory_levels/set.json'))
            ->count();

        $this->assertSame($setCallsBefore, $setCallsAfter);
    }

    protected function fakeShopifyApi(): void
    {
        ShopifyIntegration::query()->create([
            'shop_name' => 'libromart-test',
            'api_access_token' => 'shpat_test',
            'api_version' => '2024-01',
            'enabled' => true,
            'primary_location_id' => '555',
        ]);

        Http::fake([
            '*/inventory_levels/set.json' => Http::response(['inventory_level' => []], 200),
            '*/inventory_levels/connect.json' => Http::response(['inventory_level' => []], 200),
            '*/locations.json' => Http::response([
                'locations' => [
                    ['id' => 555, 'primary' => true, 'active' => true],
                ],
            ], 200),
            '*' => Http::response([], 200),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function shopifyProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Produit Shopify',
            'ref' => 'SHOP-SKU',
            'source' => 'shopify',
            'external_id' => '8161000000001',
            'item_kind' => Product::KIND_STOCKED,
            'stock_quantity' => 0,
            'stock_enligne' => 0,
            'stock_magasin' => 0,
            'cost_price_ht' => 20,
            'vat_category' => 'TVA (20%)',
        ], $overrides));
    }
}
