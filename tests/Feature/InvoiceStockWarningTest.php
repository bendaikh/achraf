<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceStockWarningTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_can_be_created_when_shopify_stock_is_zero(): void
    {
        $user = User::factory()->create();
        $client = Client::create(['name' => 'Client Test']);
        $product = Product::create([
            'name' => 'Tapis sur mesure 4D Opel Corsa F 2019',
            'ref' => 'FAST-TAP4D-072',
            'source' => 'shopify',
            'external_id' => '8161092665502',
            'item_kind' => Product::KIND_STOCKED,
            'stock_enligne' => 0,
            'stock_quantity' => 0,
            'stock_magasin' => 0,
        ]);

        $response = $this->actingAs($user)->post(route('invoices.store'), [
            'client_id' => $client->id,
            'invoice_date' => '2026-08-08',
            'currency' => 'dh - MAD',
            'stock_location' => 'DEPOT',
            'items' => [[
                'product_id' => $product->id,
                'ref' => $product->ref,
                'designation' => $product->name,
                'quantity' => 1,
                'unit_price' => 100,
                'tax_rate' => 20,
                'discount' => 0,
                'discount_type' => 'fixed',
            ]],
        ]);

        $response->assertRedirect(route('invoices.index'));
        $response->assertSessionHas('success');
        $response->assertSessionHas('warning');
        $this->assertStringContainsString('Stock insuffisant', session('warning'));
        $this->assertSame(1, Invoice::count());

        $product->refresh();
        $this->assertSame(-1, (int) $product->stock_enligne);
    }
}
