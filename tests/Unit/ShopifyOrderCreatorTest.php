<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ShopifyOrderCreator;
use App\Support\OrderSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShopifyOrderCreatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_payload_sends_ht_price_variant_link_and_readable_user_notes(): void
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
        $this->assertSame('541.67', $line['price']);
        $this->assertSame(44690554224798, $line['variant_id']);
        $this->assertTrue($line['taxable']);
        $this->assertSame('108.33', $line['tax_lines'][0]['price']);
        $this->assertFalse($payload['taxes_included']);

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
}
