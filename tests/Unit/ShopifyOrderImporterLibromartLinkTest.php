<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\User;
use App\Services\ShopifyOrderImporter;
use App\Support\OrderSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShopifyOrderImporterLibromartLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_links_existing_libromart_order_by_creation_token_instead_of_duplicating(): void
    {
        $user = User::factory()->create();
        $client = Client::create(['name' => 'Client Libromart']);
        $token = (string) Str::uuid();

        $local = PosSale::create([
            'ticket_number' => 'CMD-2026-000001',
            'creation_token' => $token,
            'client_id' => $client->id,
            'user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'assigned_user_id' => $user->id,
            'sold_at' => now(),
            'currency' => 'MAD',
            'subtotal' => 100,
            'discount' => 0,
            'tax_total' => 20,
            'shipping_amount' => 0,
            'total' => 120,
            'payment_method' => 'cash',
            'status' => PosSale::STATUS_PENDING,
            'payment_status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'source' => OrderSource::LIBROMART,
            'sync_status' => PosSale::SYNC_IN_PROGRESS,
        ]);

        PosSaleItem::create([
            'pos_sale_id' => $local->id,
            'designation' => 'Produit local',
            'quantity' => 1,
            'unit_price' => 100,
            'tax_rate' => 20,
            'discount' => 0,
            'line_total' => 120,
        ]);

        $imported = app(ShopifyOrderImporter::class)->import([
            'id' => 99887766,
            'name' => '#FTC9999',
            'order_number' => 9999,
            'currency' => 'MAD',
            'financial_status' => 'pending',
            'fulfillment_status' => null,
            'created_at' => now()->toIso8601String(),
            'email' => 'client@example.com',
            'note_attributes' => [
                ['name' => 'libromart_creation_token', 'value' => $token],
                ['name' => 'libromart_order_id', 'value' => (string) $local->id],
            ],
            'line_items' => [[
                'name' => 'Produit distant',
                'sku' => 'REMOTE-SKU',
                'quantity' => 1,
                'price' => '999.00',
                'tax_lines' => [['rate' => 0.2]],
            ]],
        ]);

        $this->assertSame($local->id, $imported->id);
        $this->assertSame(1, PosSale::count());
        $this->assertSame(OrderSource::LIBROMART, $imported->source);
        $this->assertSame('CMD-2026-000001', $imported->ticket_number);
        $this->assertSame('99887766', $imported->shopify_order_id);
        $this->assertSame('FTC9999', $imported->shopify_order_number);
        $this->assertSame(PosSale::SYNC_SYNCED, $imported->sync_status);
        $this->assertSame($user->id, $imported->created_by_user_id);
        $this->assertSame($user->id, $imported->assigned_user_id);
        $this->assertSame(1, $imported->items()->count());
        $this->assertSame('Produit local', $imported->items()->first()->designation);
        $this->assertEquals(120.0, (float) $imported->total);
    }
}
