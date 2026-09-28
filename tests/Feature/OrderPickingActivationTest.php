<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\OrderSource;
use App\Support\StockSettings;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPickingActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_activating_picking_records_activation_timestamp(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('settings.update'), [
            'settings_type' => 'stock',
            'stock_low_threshold' => 3,
            'stock_minimum_default' => 0,
            'stock_picking_enabled' => '1',
        ])->assertRedirect();

        $this->assertSame('1', Setting::get('stock_picking_enabled'));
        $this->assertNotEmpty(Setting::get(StockSettings::PICKING_ACTIVATED_AT_KEY));
        $this->assertTrue(StockSettings::pickingEnabled());
        $this->assertNotNull(StockSettings::pickingActivatedAt());
    }

    public function test_picking_index_hides_pre_activation_orders_and_shows_new_ones_per_channel(): void
    {
        $user = User::factory()->create();
        $product = $this->stockedProduct();

        Carbon::setTestNow('2026-09-20 10:00:00');
        $oldShopify = $this->orderWithProduct($product, 1, OrderSource::SHOPIFY, 'OLD-SHOPIFY');
        $oldJumia = $this->orderWithProduct($product, 1, OrderSource::JUMIA, 'OLD-JUMIA');
        $oldLibromart = $this->orderWithProduct($product, 1, OrderSource::LIBROMART, 'OLD-LIBRO');

        Carbon::setTestNow('2026-09-28 12:00:00');
        StockSettings::recordPickingActivation(now());
        Setting::set('stock_picking_enabled', '1', 'Activer Préparation / Picking');

        Carbon::setTestNow('2026-09-28 14:00:00');
        $newShopify = $this->orderWithProduct($product, 1, OrderSource::SHOPIFY, 'NEW-SHOPIFY');
        $newJumia = $this->orderWithProduct($product, 1, OrderSource::JUMIA, 'NEW-JUMIA');
        $newLibromart = $this->orderWithProduct($product, 1, OrderSource::LIBROMART, 'NEW-LIBRO');

        $response = $this->actingAs($user)->get(route('sales.picking.index'));
        $response->assertOk();
        $response->assertDontSee('OLD-SHOPIFY');
        $response->assertDontSee('OLD-JUMIA');
        $response->assertDontSee('OLD-LIBRO');
        $response->assertSee('NEW-SHOPIFY');
        $response->assertSee('NEW-JUMIA');
        $response->assertSee('NEW-LIBRO');

        // Historical Libromart order is not deleted — only excluded from picking.
        $this->assertDatabaseHas('pos_sales', ['id' => $oldLibromart->id, 'ticket_number' => 'OLD-LIBRO']);
        $this->assertTrue(StockSettings::orderEligibleForPicking($newShopify->fresh()));
        $this->assertFalse(StockSettings::orderEligibleForPicking($oldShopify->fresh()));

        Carbon::setTestNow();
    }

    public function test_unprepared_post_activation_order_stays_visible_the_next_day(): void
    {
        $user = User::factory()->create();
        $product = $this->stockedProduct(['ref' => 'STAY-VISIBLE']);

        Carbon::setTestNow('2026-09-28 09:00:00');
        Setting::set('stock_picking_enabled', '1');
        StockSettings::recordPickingActivation(now());

        Carbon::setTestNow('2026-09-28 11:00:00');
        $this->orderWithProduct($product, 1, OrderSource::SHOPIFY, 'STILL-OPEN');

        Carbon::setTestNow('2026-09-29 16:00:00');
        $this->actingAs($user)
            ->get(route('sales.picking.index'))
            ->assertOk()
            ->assertSee('STILL-OPEN');

        Carbon::setTestNow();
    }

    public function test_re_enabling_picking_starts_a_fresh_activation_window(): void
    {
        Carbon::setTestNow('2026-09-20 10:00:00');
        Setting::set('stock_picking_enabled', '1');
        StockSettings::recordPickingActivation(now());
        $first = StockSettings::pickingActivatedAt()?->toIso8601String();

        Setting::set('stock_picking_enabled', '0');

        Carbon::setTestNow('2026-09-28 12:00:00');
        $user = User::factory()->create();
        $this->actingAs($user)->put(route('settings.update'), [
            'settings_type' => 'stock',
            'stock_low_threshold' => 3,
            'stock_minimum_default' => 0,
            'stock_picking_enabled' => '1',
        ])->assertRedirect();

        $second = StockSettings::pickingActivatedAt()?->toIso8601String();
        $this->assertNotSame($first, $second);
        $this->assertTrue(
            Carbon::parse($second)->greaterThan(Carbon::parse($first))
        );

        Carbon::setTestNow();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function stockedProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Produit Picking',
            'ref' => 'SKU-PICK',
            'item_kind' => Product::KIND_STOCKED,
            'stock_quantity' => 0,
            'stock_enligne' => 0,
            'stock_magasin' => 0,
            'cost_price_ht' => 20,
            'vat_category' => 'TVA (20%)',
        ], $overrides));
    }

    private function orderWithProduct(Product $product, int $qty, string $source, string $ticket): PosSale
    {
        $client = Client::create(['name' => 'Client '.$ticket]);
        $user = User::factory()->create();
        $order = PosSale::create([
            'ticket_number' => $ticket,
            'client_id' => $client->id,
            'user_id' => $user->id,
            'sold_at' => now(),
            'status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'currency' => 'MAD',
            'subtotal' => 10,
            'total' => 10,
            'payment_method' => 'cash',
            'source' => $source,
        ]);
        PosSaleItem::create([
            'pos_sale_id' => $order->id,
            'product_id' => $product->id,
            'ref' => $product->ref,
            'designation' => $product->name,
            'quantity' => $qty,
            'unit_price' => 10,
            'tax_rate' => 20,
            'discount' => 0,
            'line_total' => 10,
        ]);

        return $order;
    }
}
