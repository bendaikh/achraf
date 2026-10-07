<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\ShopifyIntegration;
use App\Models\StockMovement;
use App\Models\StockReplenishmentNeed;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OrderPhysicalStockService;
use App\Services\ShopifyInventorySyncService;
use App\Services\StockMovementService;
use App\Support\OrderSource;
use App\Support\StockSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopifyZeroStockPushGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('stock_picking_enabled', '1');
        StockSettings::recordPickingActivation(now()->subMinute());
    }

    public function test_physical_stock_five_pushes_five_to_shopify(): void
    {
        $this->fakeShopifyApi(shopifyAvailable: 0);
        $belvedere = $this->shopifyFeedWarehouse();
        [$product, $variant] = $this->shopifyProductWithVariant();

        app(StockMovementService::class)->increase(
            $product,
            5,
            'magasin',
            true,
            StockMovement::TYPE_PHYSICAL_IN,
            null,
            null,
            null,
            (int) $belvedere->id,
            null,
            null,
            null,
            (int) $variant->id
        );

        // afterCommit peut ne pas s’exécuter dans la transaction de test : push explicite.
        app(ShopifyInventorySyncService::class)->pushProductStock($product->fresh(), (int) $variant->id);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'inventory_levels/set.json')
                && (int) data_get($request->data(), 'inventory_item_id') === 900501
                && (int) data_get($request->data(), 'available') === 5;
        });

        $this->assertSame(5, app(StockMovementService::class)->shopifyAvailableQuantity($product->fresh(), (int) $variant->id));
        $this->assertSame(5, (int) $variant->fresh()->inventory_quantity);
    }

    public function test_libromart_zero_does_not_overwrite_positive_shopify_quantity(): void
    {
        $this->fakeShopifyApi(shopifyAvailable: 3);
        [$product, $variant] = $this->shopifyProductWithVariant(['inventory_quantity' => 3]);

        // Stock physique Libromart = 0 ; Shopify a volontairement 3.
        $this->assertSame(0, app(StockMovementService::class)->shopifyAvailableQuantity($product->fresh(), (int) $variant->id));

        app(ShopifyInventorySyncService::class)->pushProductStock($product->fresh(), (int) $variant->id);

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), 'inventory_levels/set.json')
                && (int) data_get($request->data(), 'available') === 0;
        });

        // Miroir local inchangé (pas écrasé à 0).
        $this->assertSame(3, (int) $variant->fresh()->inventory_quantity);
        // Toujours aucun stock physique Libromart.
        $this->assertSame(0, $product->fresh()->physicalStock());
    }

    public function test_order_on_zero_physical_with_shopify_qty_creates_need_without_stock_movement(): void
    {
        $this->fakeShopifyApi(shopifyAvailable: 3);
        [$product, $variant] = $this->shopifyProductWithVariant(['inventory_quantity' => 3]);

        $movementsBefore = StockMovement::query()->where('product_id', $product->id)->count();
        $physicalBefore = $product->fresh()->physicalStock();
        $this->assertSame(0, $physicalBefore);

        $order = $this->shopifyOrderForProduct($product, $variant, 1);
        $result = app(OrderPhysicalStockService::class)->ensureAllocated($order->fresh(['items.product.variants', 'items.variant']));

        $this->assertNotNull($result);
        $this->assertSame(0, (int) collect($result['reserved'] ?? [])->sum('quantity'));
        $this->assertGreaterThan(0, (int) collect($result['unavailable'] ?? [])->sum('quantity'));

        $need = StockReplenishmentNeed::query()->open()->where('pos_sale_id', $order->id)->first();
        $this->assertNotNull($need);
        $this->assertSame(1, (int) $need->quantity_needed);

        // Aucune sortie physique : la qty Shopify n’est jamais transformée en stock Libromart.
        $physicalMovements = StockMovement::query()
            ->where('product_id', $product->id)
            ->whereIn('type', [
                StockMovement::TYPE_ORDER_OUT,
                StockMovement::TYPE_SALE,
            ])
            ->count();
        $this->assertSame(0, $physicalMovements);
        $this->assertSame(0, $product->fresh()->physicalStock());
        $this->assertSame($movementsBefore, StockMovement::query()->where('product_id', $product->id)->count());

        // Push 0 interdit : Shopify conserve sa quantité commerciale.
        app(ShopifyInventorySyncService::class)->pushProductStock($product->fresh(), (int) $variant->id);
        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), 'inventory_levels/set.json')
                && (int) data_get($request->data(), 'available') === 0;
        });
        $this->assertSame(3, (int) $variant->fresh()->inventory_quantity);
    }

    protected function shopifyFeedWarehouse(): Warehouse
    {
        $belvedere = Warehouse::fulfillmentWarehouse();
        $this->assertNotNull($belvedere);
        $belvedere->update(['available_for_shopify' => true]);

        return $belvedere->fresh();
    }

    /**
     * @param  array<string, mixed>  $variantOverrides
     * @return array{0: Product, 1: ProductVariant}
     */
    protected function shopifyProductWithVariant(array $variantOverrides = []): array
    {
        $product = Product::create([
            'name' => 'Produit Guard Shopify',
            'ref' => 'GUARD-SKU',
            'source' => 'shopify',
            'external_id' => '8161999000001',
            'item_kind' => Product::KIND_STOCKED,
            'stock_quantity' => 0,
            'stock_enligne' => 0,
            'stock_magasin' => 0,
            'cost_price_ht' => 20,
            'vat_category' => 'TVA (20%)',
        ]);

        $variant = ProductVariant::create(array_merge([
            'product_id' => $product->id,
            'shopify_variant_id' => '555001',
            'inventory_item_id' => '900501',
            'title' => 'Default Title',
            'sku' => $product->ref,
            'inventory_quantity' => 0,
        ], $variantOverrides));

        return [$product, $variant];
    }

    protected function shopifyOrderForProduct(Product $product, ProductVariant $variant, int $qty): PosSale
    {
        $client = Client::create(['name' => 'Client Shopify Guard']);
        $user = User::factory()->create();
        $order = PosSale::create([
            'ticket_number' => 'FAST-GUARD-1',
            'client_id' => $client->id,
            'user_id' => $user->id,
            'sold_at' => now(),
            'status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'currency' => 'MAD',
            'subtotal' => 10 * $qty,
            'total' => 12 * $qty,
            'payment_method' => PosSale::PAYMENT_CARD,
            'source' => OrderSource::SHOPIFY,
        ]);

        PosSaleItem::create([
            'pos_sale_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'ref' => $product->ref,
            'designation' => $product->name,
            'quantity' => $qty,
            'unit_price' => 10,
            'tax_rate' => 20,
            'discount' => 0,
            'line_total' => 12 * $qty,
        ]);

        return $order;
    }

    protected function fakeShopifyApi(int $shopifyAvailable = 0): void
    {
        ShopifyIntegration::query()->create([
            'shop_name' => 'libromart-guard',
            'api_access_token' => 'shpat_test',
            'api_version' => '2024-01',
            'enabled' => true,
            'primary_location_id' => '555',
        ]);

        Http::fake([
            '*/inventory_levels.json*' => Http::response([
                'inventory_levels' => [[
                    'inventory_item_id' => 900501,
                    'location_id' => 555,
                    'available' => $shopifyAvailable,
                ]],
            ], 200),
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
}
