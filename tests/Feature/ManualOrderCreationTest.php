<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ManualOrderCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_commercial_is_automatically_recorded_as_creator_and_assignee(): void
    {
        $commercial = User::factory()->create();
        $otherUser = User::factory()->create();
        $client = Client::create(['name' => 'Client test']);
        $product = Product::create([
            'name' => 'Produit test',
            'ref' => 'TEST-001',
            'sale_price' => 120,
            'vat_category' => '20%',
        ]);
        $token = (string) Str::uuid();

        $response = $this->actingAs($commercial)->post(route('orders.store'), [
            'creation_token' => $token,
            'client_id' => $client->id,
            'assigned_user_id' => $otherUser->id,
            'sold_at' => '2026-08-15 12:00:00',
            'status' => 'pending',
            'sales_channel' => 'shopify',
            'currency' => 'MAD',
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'discount' => 0,
            ]],
            'discount_type' => 'amount',
            'discount_value' => 20,
            'shipping_amount' => 30,
            'tags' => 'VIP, WhatsApp',
            'submit_action' => 'save',
        ]);

        $order = PosSale::query()->where('creation_token', $token)->firstOrFail();
        $response->assertRedirect(route('orders.show', $order));
        $this->assertSame($commercial->id, $order->created_by_user_id);
        $this->assertSame($commercial->id, $order->assigned_user_id);
        $this->assertSame($commercial->id, $order->user_id);
        $this->assertEquals(250.0, (float) $order->total);
        $this->assertSame(['VIP', 'WhatsApp'], $order->tags);
        $this->assertDatabaseCount('order_activities', 2);
    }

    public function test_product_search_lists_catalog_when_browsing_without_term(): void
    {
        $user = User::factory()->create();
        Product::create([
            'name' => 'Cahier Libromart',
            'ref' => 'CAH-001',
            'sale_price' => 15,
            'vat_category' => '20%',
        ]);

        $this->actingAs($user)
            ->getJson(route('orders.products.search', ['browse' => 1]))
            ->assertOk()
            ->assertJsonPath('products.0.name', 'Cahier Libromart');

        $this->actingAs($user)
            ->getJson(route('orders.products.search', ['q' => 'a']))
            ->assertOk()
            ->assertJsonPath('products', []);
    }

    public function test_super_admin_can_assign_order_to_another_user(): void
    {
        $admin = User::factory()->create();
        $commercial = User::factory()->create();
        $role = Role::create(['name' => 'Super Admin', 'slug' => 'superadmin']);
        $admin->roles()->attach($role);
        $client = Client::create(['name' => 'Client admin']);
        $product = Product::create([
            'name' => 'Produit admin',
            'ref' => 'TEST-002',
            'sale_price' => 100,
        ]);

        $this->actingAs($admin)->post(route('orders.store'), [
            'creation_token' => (string) Str::uuid(),
            'client_id' => $client->id,
            'assigned_user_id' => $commercial->id,
            'sold_at' => '2026-08-15 12:00:00',
            'status' => 'pending',
            'sales_channel' => 'manual',
            'currency' => 'MAD',
            'payment_status' => 'pending',
            'payment_method' => 'transfer',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'submit_action' => 'save',
        ])->assertRedirect();

        $order = PosSale::query()->latest('id')->firstOrFail();
        $this->assertSame($admin->id, $order->created_by_user_id);
        $this->assertSame($commercial->id, $order->assigned_user_id);
    }

    public function test_creation_token_prevents_duplicate_local_orders(): void
    {
        $user = User::factory()->create();
        $client = Client::create(['name' => 'Client double clic']);
        $product = Product::create([
            'name' => 'Produit double clic',
            'ref' => 'TEST-003',
            'sale_price' => 50,
        ]);
        $token = (string) Str::uuid();
        $payload = [
            'creation_token' => $token,
            'client_id' => $client->id,
            'assigned_user_id' => $user->id,
            'sold_at' => '2026-08-15 12:00:00',
            'status' => 'pending',
            'sales_channel' => 'manual',
            'currency' => 'MAD',
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'submit_action' => 'save',
        ];

        $this->actingAs($user)->post(route('orders.store'), $payload)->assertRedirect();
        $this->actingAs($user)->post(route('orders.store'), $payload)->assertRedirect();

        $this->assertSame(1, PosSale::query()->where('creation_token', $token)->count());
    }
}
