<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\StockReplenishmentNeed;
use App\Models\StockReservation;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockMovementService;
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

    public function test_picking_index_hides_pre_activation_orders_and_shows_new_ones_with_physical_stock(): void
    {
        $user = User::factory()->create();
        $product = $this->stockedProduct();
        $this->declarePhysicalStock($product, 10);

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

    public function test_unprepared_post_activation_order_with_physical_stock_stays_visible_the_next_day(): void
    {
        $user = User::factory()->create();
        $product = $this->stockedProduct(['ref' => 'STAY-VISIBLE']);
        $this->declarePhysicalStock($product, 2);

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

    public function test_zero_physical_stock_hides_order_from_picking_and_creates_purchase_need(): void
    {
        $user = User::factory()->create();
        $product = $this->stockedProduct(['ref' => 'TAPIS-ZERO', 'name' => 'Tapis zéro stock']);
        $online = Warehouse::onlineWarehouse();
        $this->assertNotNull($online);

        // Shopify online stock must NOT qualify for picking.
        app(StockMovementService::class)->increase(
            $product,
            5,
            'enligne',
            false,
            StockMovement::TYPE_PURCHASE,
            null,
            null,
            null,
            $online->id
        );

        Setting::set('stock_picking_enabled', '1');
        StockSettings::recordPickingActivation(now()->subMinute());

        $order = $this->orderWithProduct($product, 1, OrderSource::SHOPIFY, 'ZERO-PHYS');

        $this->actingAs($user)
            ->get(route('sales.picking.index'))
            ->assertOk()
            ->assertDontSee('ZERO-PHYS');

        $this->assertSame(0, StockReservation::query()->active()->where('source_id', $order->id)->count());
        $this->assertSame(1, StockReplenishmentNeed::query()->open()->where('pos_sale_id', $order->id)->value('quantity_needed'));
    }

    public function test_partial_physical_stock_reserves_available_and_needs_remainder(): void
    {
        $user = User::factory()->create();
        $product = $this->stockedProduct(['ref' => 'TAPIS-PART', 'name' => 'Tapis partiel']);
        $this->declarePhysicalStock($product, 1);

        Setting::set('stock_picking_enabled', '1');
        StockSettings::recordPickingActivation(now()->subMinute());

        $order = $this->orderWithProduct($product, 2, OrderSource::SHOPIFY, 'PARTIAL-PHYS');

        $this->actingAs($user)
            ->get(route('sales.picking.index'))
            ->assertOk()
            ->assertSee('PARTIAL-PHYS');

        $this->assertSame(1, (int) StockReservation::query()->active()->where('source_id', $order->id)->sum('quantity'));
        $this->assertSame(1, (int) StockReplenishmentNeed::query()->open()->where('pos_sale_id', $order->id)->value('quantity_needed'));
    }

    public function test_sufficient_physical_stock_reserves_fully_without_purchase_need(): void
    {
        $user = User::factory()->create();
        $product = $this->stockedProduct(['ref' => 'TAPIS-OK', 'name' => 'Tapis ok']);
        $this->declarePhysicalStock($product, 2);

        Setting::set('stock_picking_enabled', '1');
        StockSettings::recordPickingActivation(now()->subMinute());

        $order = $this->orderWithProduct($product, 1, OrderSource::SHOPIFY, 'FULL-PHYS');

        $this->actingAs($user)
            ->get(route('sales.picking.index'))
            ->assertOk()
            ->assertSee('FULL-PHYS');

        $this->assertSame(1, (int) StockReservation::query()->active()->where('source_id', $order->id)->sum('quantity'));
        $this->assertSame(0, StockReplenishmentNeed::query()->open()->where('pos_sale_id', $order->id)->count());
    }

    public function test_picking_index_shows_expandable_product_lines_with_reserved_and_missing(): void
    {
        $user = User::factory()->create();
        $product = $this->stockedProduct(['ref' => 'FAST-TAP4D-211', 'name' => 'Tapis 4D BMW F70']);
        $this->declarePhysicalStock($product, 1);

        Setting::set('stock_picking_enabled', '1');
        StockSettings::recordPickingActivation(now()->subMinute());

        $this->orderWithProduct($product, 3, OrderSource::SHOPIFY, 'LINES-PHYS');

        $response = $this->actingAs($user)->get(route('sales.picking.index'));
        $response->assertOk();
        $response->assertSee('LINES-PHYS');
        $response->assertSee('Tapis 4D BMW F70');
        $response->assertSee('FAST-TAP4D-211');
        $response->assertSee('Cmd 3');
        $response->assertSee('Belvédère 1');
        $response->assertSee('Réservé 1');
        $response->assertSee('Manquant 2');
        $response->assertSee('BEL-STOCK');

        $this->assertSame(1, (int) StockReservation::query()->active()->sum('quantity'));
        $this->assertSame(2, (int) StockReplenishmentNeed::query()->open()->value('quantity_needed'));
    }

    public function test_reset_picking_releases_and_reallocates_without_changing_physical_stock(): void
    {
        $user = User::factory()->create();
        $product = $this->stockedProduct(['ref' => 'RESET-SKU', 'name' => 'Produit reset']);
        $this->declarePhysicalStock($product, 2);

        Setting::set('stock_picking_enabled', '1');
        StockSettings::recordPickingActivation(now()->subMinute());

        $order = $this->orderWithProduct($product, 3, OrderSource::SHOPIFY, 'RESET-ORDER');

        $this->actingAs($user)->get(route('sales.picking.index'))->assertOk();
        $this->assertSame(2, (int) StockReservation::query()->active()->where('source_id', $order->id)->sum('quantity'));
        $this->assertSame(1, (int) StockReplenishmentNeed::query()->open()->where('pos_sale_id', $order->id)->value('quantity_needed'));

        $belvedere = Warehouse::fulfillmentWarehouse();
        $physicalBefore = (int) \App\Models\ProductStock::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $belvedere->id)
            ->sum('quantity');

        $this->actingAs($user)
            ->post(route('settings.stock.reset-picking'))
            ->assertRedirect(route('settings.stock', ['tab' => 'regles']));

        $this->assertDatabaseHas('pos_sales', ['id' => $order->id, 'ticket_number' => 'RESET-ORDER']);
        $this->assertSame(2, (int) StockReservation::query()->active()->where('source_id', $order->id)->sum('quantity'));
        $this->assertSame(1, (int) StockReplenishmentNeed::query()->open()->where('pos_sale_id', $order->id)->value('quantity_needed'));

        $physicalAfter = (int) \App\Models\ProductStock::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $belvedere->id)
            ->sum('quantity');
        $this->assertSame($physicalBefore, $physicalAfter);
        $this->assertSame(2, $physicalAfter);
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

    private function declarePhysicalStock(Product $product, int $qty): void
    {
        $belvedere = Warehouse::fulfillmentWarehouse();
        $this->assertNotNull($belvedere);
        $location = $belvedere->locations()->first() ?? \App\Models\WarehouseLocation::create([
            'warehouse_id' => $belvedere->id,
            'code' => 'BEL-STOCK',
            'name' => 'Bel Stock',
            'status' => 'active',
        ]);

        app(StockMovementService::class)->adjustPhysicalStock(
            $product,
            $qty,
            (int) $belvedere->id,
            (int) $location->id,
            StockMovement::REASON_INVENTORY_CORRECTION
        );
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
            'line_total' => 10 * $qty,
        ]);

        return $order;
    }
}
