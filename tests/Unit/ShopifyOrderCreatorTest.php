<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShopifyIntegration;
use App\Models\User;
use App\Services\ShopifyOrderCreator;
use App\Support\OrderSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShopifyOrderCreatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_payload_sends_ttc_price_variant_link_and_readable_user_notes(): void
    {
        $creator = User::factory()->create(['name' => 'Yassine']);
        $commercial = User::factory()->create(['name' => 'Yassine Commercial']);
        $client = Client::create(['name' => 'Client Test', 'email' => 'client@example.com']);

        $product = Product::create([
            'name' => 'Accoudoir sur mesure Peugeot partner Tepee 2008-2018',
            'ref' => 'FAST-ACC-017',
            'sale_price' => 650,
            'source' => 'shopify',
            'external_id' => '8161105477790',
            'vat_category' => '20%',
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'title' => 'Default Title',
            'sku' => 'FAST-ACC-017',
            'price' => 650,
            'shopify_variant_id' => '44690554224798',
            'position' => 1,
        ]);

        $token = (string) Str::uuid();
        $order = PosSale::create([
            'ticket_number' => 'CMD-2026-000099',
            'creation_token' => $token,
            'client_id' => $client->id,
            'user_id' => $commercial->id,
            'created_by_user_id' => $creator->id,
            'assigned_user_id' => $commercial->id,
            'sold_at' => now(),
            'currency' => 'MAD',
            'subtotal' => 541.67,
            'discount' => 0,
            'tax_total' => 108.33,
            'shipping_amount' => 0,
            'total' => 650,
            'payment_method' => 'cash',
            'status' => PosSale::STATUS_PENDING,
            'payment_status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'source' => OrderSource::LIBROMART,
            'sync_status' => PosSale::SYNC_NOT_SYNCED,
        ]);

        // Simulate the bug: order item without shopify_variant_id / product_variant_id.
        PosSaleItem::create([
            'pos_sale_id' => $order->id,
            'product_id' => $product->id,
            'ref' => 'FAST-ACC-017',
            'designation' => $product->name,
            'shopify_variant_id' => null,
            'product_variant_id' => null,
            'quantity' => 1,
            'unit_price' => 541.67,
            'tax_rate' => 20,
            'discount' => 0,
            'line_total' => 650,
        ]);

        $order->load(['client', 'items.variant', 'items.product.variants', 'creator', 'assignedUser']);
        $payload = app(ShopifyOrderCreator::class)->payload($order);

        $line = $payload['line_items'][0];
        // Shopify expects tax-inclusive (TTC) line prices so total_price matches Libromart.
        $this->assertSame('650.00', $line['price']);
        $this->assertSame(44690554224798, $line['variant_id']);
        $this->assertTrue($line['taxable']);
        $this->assertSame('108.33', $line['tax_lines'][0]['price']);
        $this->assertTrue($payload['taxes_included']);

        $notes = collect($payload['note_attributes'])->keyBy('name');
        $this->assertSame('Yassine', $notes['Créée depuis Libromart par']['value']);
        $this->assertSame('Yassine Commercial', $notes['Commercial']['value']);
        $this->assertSame('CMD-2026-000099', $notes['N° commande Libromart']['value']);
        $this->assertSame((string) $order->id, $notes['libromart_order_id']['value']);
        $this->assertSame((string) $creator->id, $notes['libromart_created_by_user_id']['value']);
    }

    public function test_store_persists_shopify_variant_id_for_single_variant_products(): void
    {
        $user = User::factory()->create(['name' => 'Commercial']);
        $client = Client::create(['name' => 'Client variante']);
        $product = Product::create([
            'name' => 'Produit Shopify',
            'ref' => 'SKU-1',
            'sale_price' => 120,
            'source' => 'shopify',
            'external_id' => '111',
            'vat_category' => '20%',
            'status' => 'Activer',
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'title' => 'Default Title',
            'sku' => 'SKU-1',
            'price' => 120,
            'shopify_variant_id' => '999888777',
            'position' => 1,
        ]);

        $this->actingAs($user)->post(route('orders.store'), [
            'creation_token' => (string) Str::uuid(),
            'client_id' => $client->id,
            'assigned_user_id' => $user->id,
            'sold_at' => '2026-09-06 12:00:00',
            'status' => 'pending',
            'sales_channel' => 'manual',
            'currency' => 'MAD',
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'submit_action' => 'save',
        ])->assertRedirect();

        $item = PosSaleItem::query()->latest('id')->firstOrFail();
        $this->assertSame($variant->id, (int) $item->product_variant_id);
        $this->assertSame('999888777', (string) $item->shopify_variant_id);
    }

    public function test_first_sync_creates_shopify_order_without_scanning_history(): void
    {
        Http::fake([
            '*/admin/api/*/orders.json' => Http::sequence()
                ->push([
                    'order' => [
                        'id' => 555001,
                        'name' => '#FTC9001',
                        'order_number' => 9001,
                        'note_attributes' => [],
                    ],
                ], 201),
        ]);

        $order = $this->makeLibromartOrder();
        ShopifyIntegration::create([
            'shop_name' => 'test-shop',
            'api_version' => '2024-01',
            'api_access_token' => 'shpat_test',
            'enabled' => true,
        ]);

        $synced = app(ShopifyOrderCreator::class)->sync($order, $order->created_by_user_id);

        $this->assertSame(PosSale::SYNC_SYNCED, $synced->sync_status);
        $this->assertSame('555001', (string) $synced->shopify_order_id);
        $this->assertSame('FTC9001', $synced->shopify_order_number);
        $this->assertNull($synced->sync_error);

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), 'orders.json')
                && ($request['order']['note_attributes'][2]['name'] ?? null) === 'libromart_creation_token';
        });
    }

    public function test_resync_is_idempotent_when_shopify_order_already_linked(): void
    {
        $order = $this->makeLibromartOrder([
            'shopify_order_id' => '777',
            'shopify_order_number' => 'FTC777',
            'external_id' => '777',
            'sync_status' => PosSale::SYNC_SYNCED,
        ]);

        ShopifyIntegration::create([
            'shop_name' => 'test-shop',
            'api_version' => '2024-01',
            'api_access_token' => 'shpat_test',
            'enabled' => true,
        ]);

        Http::fake();

        $synced = app(ShopifyOrderCreator::class)->sync($order);

        $this->assertSame('777', (string) $synced->shopify_order_id);
        Http::assertNothingSent();
    }

    public function test_recovery_resync_links_existing_remote_order_instead_of_duplicating(): void
    {
        $token = (string) Str::uuid();
        $order = $this->makeLibromartOrder([
            'creation_token' => $token,
            'ticket_number' => 'CMD-2026-000004',
            'sync_status' => PosSale::SYNC_ERROR,
            'sync_error' => 'timeout',
            'sync_attempted_at' => now()->subMinutes(10),
        ]);

        ShopifyIntegration::create([
            'shop_name' => 'test-shop',
            'api_version' => '2024-01',
            'api_access_token' => 'shpat_test',
            'enabled' => true,
        ]);

        Http::fake([
            '*/admin/api/*/orders.json*' => function ($request) use ($token) {
                if ($request->method() === 'GET') {
                    return Http::response([
                        'orders' => [[
                            'id' => 888001,
                            'name' => '#FTC888',
                            'note_attributes' => [
                                ['name' => 'libromart_creation_token', 'value' => $token],
                            ],
                        ]],
                    ]);
                }

                return Http::response(['error' => 'should not create'], 500);
            },
        ]);

        $synced = app(ShopifyOrderCreator::class)->sync($order);

        $this->assertSame(PosSale::SYNC_SYNCED, $synced->sync_status);
        $this->assertSame('888001', (string) $synced->shopify_order_id);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_concurrent_in_progress_sync_throws_instead_of_silent_success(): void
    {
        $order = $this->makeLibromartOrder([
            'sync_status' => PosSale::SYNC_IN_PROGRESS,
            'sync_attempted_at' => now()->subMinute(),
        ]);

        ShopifyIntegration::create([
            'shop_name' => 'test-shop',
            'api_version' => '2024-01',
            'api_access_token' => 'shpat_test',
            'enabled' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('déjà en cours');

        app(ShopifyOrderCreator::class)->sync($order);
    }

    public function test_api_failure_persists_error_status(): void
    {
        Http::fake([
            '*/admin/api/*/orders.json' => Http::response(['errors' => 'Unauthorized'], 401),
        ]);

        $order = $this->makeLibromartOrder();
        ShopifyIntegration::create([
            'shop_name' => 'test-shop',
            'api_version' => '2024-01',
            'api_access_token' => 'shpat_test',
            'enabled' => true,
        ]);

        try {
            app(ShopifyOrderCreator::class)->sync($order);
            $this->fail('Expected exception');
        } catch (\Throwable $e) {
            $order->refresh();
            $this->assertSame(PosSale::SYNC_ERROR, $order->sync_status);
            $this->assertNotEmpty($order->sync_error);
            $this->assertNull($order->shopify_order_id);
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeLibromartOrder(array $overrides = []): PosSale
    {
        $user = User::factory()->create(['name' => 'Commercial Sync']);
        $client = Client::create([
            'name' => 'Client Sync',
            'email' => 'sync@example.com',
            'phone' => '0600000000',
        ]);
        $product = Product::create([
            'name' => 'Produit Sync',
            'ref' => 'SYNC-SKU',
            'sale_price' => 100,
            'vat_category' => '20%',
        ]);

        $order = PosSale::create(array_merge([
            'ticket_number' => 'CMD-2026-000200',
            'creation_token' => (string) Str::uuid(),
            'client_id' => $client->id,
            'user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'assigned_user_id' => $user->id,
            'sold_at' => now(),
            'currency' => 'MAD',
            'subtotal' => 83.33,
            'discount' => 0,
            'tax_total' => 16.67,
            'shipping_amount' => 0,
            'total' => 100,
            'payment_method' => 'cash',
            'status' => PosSale::STATUS_PENDING,
            'payment_status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'source' => OrderSource::LIBROMART,
            'sync_status' => PosSale::SYNC_NOT_SYNCED,
        ], $overrides));

        PosSaleItem::create([
            'pos_sale_id' => $order->id,
            'product_id' => $product->id,
            'ref' => 'SYNC-SKU',
            'designation' => $product->name,
            'quantity' => 1,
            'unit_price' => 83.33,
            'tax_rate' => 20,
            'discount' => 0,
            'line_total' => 100,
        ]);

        return $order->fresh();
    }
}
