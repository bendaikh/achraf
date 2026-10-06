<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\StockReplenishmentNeed;
use App\Models\StockReservation;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OrderPhysicalStockService;
use App\Services\StockMovementService;
use App\Support\OrderSource;
use App\Support\StockSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PickingPartialExitAndPurchaseNeedsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('stock_picking_enabled', '1');
        StockSettings::recordPickingActivation(now()->subMinute());
    }

    public function test_belvedere_stock_11_reserves_order_instead_of_shortage_even_after_repeated_reimports(): void
    {
        $product = $this->stockedProduct(['ref' => 'FTC-TW-52859', 'name' => 'Turtle Wax Clearvue']);
        $this->declarePhysicalStock($product, 11);
        $service = app(OrderPhysicalStockService::class);

        $order = $this->orderWithLines([[$product, 1]], 'FAST12949');
        $service->ensureAllocated($order->fresh());

        // Shopify sync every 5 min deletes and recreates order lines (new ids).
        for ($i = 0; $i < 12; $i++) {
            $this->reimportLines($order);
            $service->ensureAllocated($order->fresh());
        }

        $this->assertSame(1, (int) StockReservation::query()->active()->where('source_id', $order->id)->sum('quantity'));
        $this->assertSame(1, $this->belvedereReserved($product));
        $this->assertSame(11, $this->belvedereQuantity($product));
        $this->assertSame(0, StockReplenishmentNeed::query()->open()->count());

        // Second order for the same product still finds stock (11 − 1 reserved).
        $other = $this->orderWithLines([[$product, 1]], 'FAST12947');
        $service->ensureAllocated($other->fresh());
        $this->assertSame(1, (int) StockReservation::query()->active()->where('source_id', $other->id)->sum('quantity'));
        $this->assertSame(0, StockReplenishmentNeed::query()->open()->count());

        $line = collect($service->pickingLinesForOrder($order->fresh()))->first();
        $this->assertSame(11, $line['belvedere_stock']);
        $this->assertSame(1, $line['reserved']);
        $this->assertSame(0, $line['missing']);
    }

    public function test_picking_page_shows_reserved_line_with_checkbox_for_stock_9_product(): void
    {
        $user = User::factory()->create();
        $product = $this->stockedProduct(['ref' => 'FAST-DEFCHR-115', 'name' => 'Déflecteur chrome']);
        $this->declarePhysicalStock($product, 9);
        $order = $this->orderWithLines([[$product, 1]], 'FAST12941');
        $this->reimportLines($order);

        $response = $this->actingAs($user)->get(route('sales.picking.index'));
        $response->assertOk();
        $response->assertSee('FAST12941');
        $response->assertSee('Belvédère 9');
        $response->assertSee('Réservé 1');
        $response->assertSee('Manquant 0');
        $response->assertSee('name="line_ids[]"', false);
        $this->assertSame(0, StockReplenishmentNeed::query()->open()->count());
    }

    public function test_purchase_need_is_ordered_minus_available_minus_reserved(): void
    {
        $product = $this->stockedProduct(['ref' => 'NEED-MATH']);
        $this->declarePhysicalStock($product, 3);
        $service = app(OrderPhysicalStockService::class);

        $order = $this->orderWithLines([[$product, 5]], 'NEED-5');
        $service->ensureAllocated($order->fresh());

        $this->assertSame(3, (int) StockReservation::query()->active()->where('source_id', $order->id)->sum('quantity'));
        $this->assertSame(2, (int) StockReplenishmentNeed::query()->open()->where('pos_sale_id', $order->id)->value('quantity_needed'));

        // Stock arrives → re-check: need disappears, no double reservation.
        $this->declarePhysicalStock($product, 10);
        $service->ensureAllocated($order->fresh());
        $this->assertSame(5, (int) StockReservation::query()->active()->where('source_id', $order->id)->sum('quantity'));
        $this->assertSame(0, StockReplenishmentNeed::query()->open()->where('pos_sale_id', $order->id)->count());
    }

    public function test_validate_exit_ships_only_ticked_reserved_lines_and_keeps_order_pending(): void
    {
        $user = User::factory()->create();
        $available = $this->stockedProduct(['ref' => 'AVAIL-1', 'name' => 'Produit dispo']);
        $alsoAvailable = $this->stockedProduct(['ref' => 'AVAIL-2', 'name' => 'Produit dispo 2']);
        $missing = $this->stockedProduct(['ref' => 'MISS-1', 'name' => 'Produit manquant']);
        $this->declarePhysicalStock($available, 5);
        $this->declarePhysicalStock($alsoAvailable, 4);

        $order = $this->orderWithLines([[$available, 2], [$alsoAvailable, 1], [$missing, 1]], 'MULTI-1');
        $this->actingAs($user)->get(route('sales.picking.index'))->assertOk()->assertSee('MULTI-1');

        $lines = PosSaleItem::query()->where('pos_sale_id', $order->id)->get()->keyBy('product_id');
        $this->assertSame(1, (int) StockReplenishmentNeed::query()->open()->where('pos_sale_id', $order->id)->where('product_id', $missing->id)->value('quantity_needed'));

        // Tick only the first available line, plus the unavailable one (must be ignored).
        $this->actingAs($user)->post(route('sales.picking.validate-exit'), [
            'line_ids' => [$lines[$available->id]->id, $lines[$missing->id]->id],
        ])->assertRedirect();

        $this->assertSame(3, $this->belvedereQuantity($available));
        $this->assertSame(0, $this->belvedereReserved($available));
        $this->assertSame(4, $this->belvedereQuantity($alsoAvailable));
        $this->assertSame(1, $this->belvedereReserved($alsoAvailable));
        $this->assertNull($order->fresh()->physical_stock_processed_at);
        $this->assertSame(1, StockReplenishmentNeed::query()->open()->where('pos_sale_id', $order->id)->count());

        // Re-sync after partial exit must not re-reserve the shipped line.
        app(OrderPhysicalStockService::class)->ensureAllocated($order->fresh());
        $this->assertSame(0, $this->belvedereReserved($available));
        $this->assertSame(3, $this->belvedereQuantity($available));

        // Ship the second available line.
        $this->actingAs($user)->post(route('sales.picking.validate-exit'), [
            'line_ids' => [$lines[$alsoAvailable->id]->id],
        ])->assertRedirect();
        $this->assertSame(3, $this->belvedereQuantity($alsoAvailable));
        $this->assertNull($order->fresh()->physical_stock_processed_at);

        // Missing product arrives → reserved → shipped → order complete.
        $this->declarePhysicalStock($missing, 2);
        app(OrderPhysicalStockService::class)->ensureAllocated($order->fresh());
        $this->assertSame(0, StockReplenishmentNeed::query()->open()->where('pos_sale_id', $order->id)->count());
        $this->actingAs($user)->post(route('sales.picking.validate-exit'), [
            'line_ids' => [$lines[$missing->id]->id],
        ])->assertRedirect();

        $this->assertSame(1, $this->belvedereQuantity($missing));
        $this->assertNotNull($order->fresh()->physical_stock_processed_at);
    }

    public function test_validate_exit_survives_line_reimport_between_allocation_and_exit(): void
    {
        $user = User::factory()->create();
        $product = $this->stockedProduct(['ref' => 'REIMPORT-EXIT']);
        $this->declarePhysicalStock($product, 4);
        $order = $this->orderWithLines([[$product, 2]], 'REIMP-1');
        app(OrderPhysicalStockService::class)->ensureAllocated($order->fresh());

        $this->reimportLines($order);
        $newLine = PosSaleItem::query()->where('pos_sale_id', $order->id)->firstOrFail();

        $this->actingAs($user)->post(route('sales.picking.validate-exit'), [
            'line_ids' => [$newLine->id],
        ])->assertRedirect();

        $this->assertSame(2, $this->belvedereQuantity($product));
        $this->assertSame(0, $this->belvedereReserved($product));
        $this->assertNotNull($order->fresh()->physical_stock_processed_at);
    }

    public function test_recalculate_purchase_needs_frees_stale_reservations_without_touching_stock_or_orders(): void
    {
        $user = User::factory()->create();
        $product = $this->stockedProduct(['ref' => 'RECALC-SKU']);
        $this->declarePhysicalStock($product, 9);
        $belvedere = Warehouse::fulfillmentWarehouse();
        $location = $belvedere->locations()->first();

        $orderA = $this->orderWithLines([[$product, 1]], 'RECALC-A');
        $orderB = $this->orderWithLines([[$product, 1]], 'RECALC-B');

        // Legacy corrupted state: 9 orphan reservations on order A (deleted lines) drain stock.
        app(StockMovementService::class)->reserveStock($product, 9, (int) $belvedere->id, (int) $location->id, null, [
            'source_type' => 'pos_sale',
            'source_id' => $orderA->id,
            'source_line_type' => 'pos_sale_item',
            'source_line_id' => 999999,
        ]);
        StockReplenishmentNeed::create([
            'product_id' => $product->id, 'warehouse_id' => $belvedere->id, 'pos_sale_id' => $orderB->id,
            'quantity_needed' => 1, 'status' => 'open',
        ]);
        $ordered = StockReplenishmentNeed::create([
            'product_id' => $product->id, 'warehouse_id' => $belvedere->id, 'pos_sale_id' => $orderB->id,
            'quantity_needed' => 3, 'quantity_ordered' => 3, 'status' => StockReplenishmentNeed::STATUS_ORDERED,
        ]);

        $this->actingAs($user)
            ->from(route('purchases.needs.index'))
            ->post(route('purchases.needs.recalculate'))
            ->assertRedirect(route('purchases.needs.index'))
            ->assertSessionHas('success');

        $this->assertSame(9, $this->belvedereQuantity($product));
        $this->assertSame(2, $this->belvedereReserved($product));
        $this->assertSame(1, (int) StockReservation::query()->active()->where('source_id', $orderA->id)->sum('quantity'));
        $this->assertSame(1, (int) StockReservation::query()->active()->where('source_id', $orderB->id)->sum('quantity'));
        $this->assertSame(0, StockReplenishmentNeed::query()->open()->count());
        $this->assertSame(StockReplenishmentNeed::STATUS_ORDERED, $ordered->fresh()->status);
        $this->assertNull($orderA->fresh()->physical_stock_processed_at);
        $this->assertSame(1, PosSaleItem::query()->where('pos_sale_id', $orderA->id)->count());
    }

    public function test_reset_truly_deletes_work_data_and_recreates_only_real_shortages(): void
    {
        $user = User::factory()->create();
        $available = $this->stockedProduct(['ref' => 'RESET-OK']);
        $missing = $this->stockedProduct(['ref' => 'RESET-MISS']);
        $this->declarePhysicalStock($available, 5);
        $belvedere = Warehouse::fulfillmentWarehouse();

        $order = $this->orderWithLines([[$available, 2], [$missing, 3]], 'RESET-FULL');
        app(OrderPhysicalStockService::class)->ensureAllocated($order->fresh());

        $lineAvailable = PosSaleItem::query()->where('pos_sale_id', $order->id)->where('product_id', $available->id)->firstOrFail();
        $this->actingAs($user)->post(route('sales.picking.validate-exit'), [
            'line_ids' => [$lineAvailable->id],
        ])->assertRedirect();

        $consumedBefore = StockReservation::query()
            ->where('source_id', $order->id)
            ->where('status', StockReservation::STATUS_CONSUMED)
            ->sum('quantity');
        $this->assertSame(2, (int) $consumedBefore);

        // Ghost / stale workflow rows that must disappear (not stay at qty 0).
        $ghostNeed = StockReplenishmentNeed::create([
            'product_id' => $available->id,
            'warehouse_id' => $belvedere->id,
            'pos_sale_id' => $order->id,
            'quantity_needed' => 0,
            'status' => 'open',
            'notes' => 'fantôme qty 0',
        ]);
        $staleNeed = StockReplenishmentNeed::create([
            'product_id' => $missing->id,
            'warehouse_id' => $belvedere->id,
            'pos_sale_id' => $order->id,
            'quantity_needed' => 99,
            'status' => 'open',
            'notes' => 'ancien besoin faux',
        ]);
        $orderedNeed = StockReplenishmentNeed::create([
            'product_id' => $missing->id,
            'warehouse_id' => $belvedere->id,
            'pos_sale_id' => $order->id,
            'quantity_needed' => 1,
            'quantity_ordered' => 1,
            'status' => StockReplenishmentNeed::STATUS_ORDERED,
        ]);

        $physicalBefore = $this->belvedereQuantity($available);

        $this->actingAs($user)
            ->from(route('purchases.needs.index'))
            ->post(route('purchases.needs.recalculate'))
            ->assertRedirect(route('purchases.needs.index'))
            ->assertSessionHas('success');

        // Ghost / stale open needs deleted — not left displayed at 0.
        $this->assertDatabaseMissing('stock_replenishment_needs', ['id' => $ghostNeed->id]);
        $this->assertDatabaseMissing('stock_replenishment_needs', ['id' => $staleNeed->id]);
        $this->assertSame(0, StockReplenishmentNeed::query()->open()->where('quantity_needed', '<=', 0)->count());

        // Only the real shortage (3 missing) is recreated; BC-ordered need untouched.
        $this->assertSame(3, (int) StockReplenishmentNeed::query()->open()->where('pos_sale_id', $order->id)->where('product_id', $missing->id)->value('quantity_needed'));
        $this->assertSame(1, StockReplenishmentNeed::query()->open()->count());
        $this->assertSame(StockReplenishmentNeed::STATUS_ORDERED, $orderedNeed->fresh()->status);

        // Validated exit kept; physical stock unchanged; order not deleted.
        $this->assertSame(2, (int) StockReservation::query()->where('source_id', $order->id)->where('status', StockReservation::STATUS_CONSUMED)->sum('quantity'));
        $this->assertSame($physicalBefore, $this->belvedereQuantity($available));
        $this->assertSame(3, $physicalBefore);
        $this->assertDatabaseHas('pos_sales', ['id' => $order->id, 'ticket_number' => 'RESET-FULL']);
        $this->assertNull($order->fresh()->physical_stock_processed_at);

        // No released/ghost reservation rows left for the order (only consumed + new active if any).
        $this->assertSame(0, StockReservation::query()->where('source_id', $order->id)->where('status', StockReservation::STATUS_RELEASED)->count());
    }

    public function test_recalculate_button_is_on_purchase_needs_and_replenishment_pages(): void
    {
        $user = User::factory()->create();
        $confirm = 'Cette action va supprimer les réservations et besoins automatiques non validés puis recalculer le workflow depuis le stock réel. Continuer ?';
        $this->actingAs($user)->get(route('purchases.needs.index'))
            ->assertOk()
            ->assertSee('Réinitialiser / Recalculer les besoins d’achat')
            ->assertSee($confirm, false);
        $this->actingAs($user)->get(route('stock.replenishment.index'))
            ->assertOk()
            ->assertSee('Réinitialiser / Recalculer les besoins d’achat')
            ->assertSee($confirm, false);
    }

    public function test_sav_quarantine_stock_is_never_reserved(): void
    {
        $product = $this->stockedProduct(['ref' => 'SAV-ONLY']);
        $sav = Warehouse::query()->where('is_sellable', false)->physical()->active()->first();
        if (! $sav) {
            $this->markTestSkipped('Pas de dépôt SAV non vendable dans le schéma de test.');
        }
        app(StockMovementService::class)->adjustPhysicalStock($product, 5, (int) $sav->id, null, StockMovement::REASON_INVENTORY_CORRECTION);

        $order = $this->orderWithLines([[$product, 1]], 'SAV-1');
        app(OrderPhysicalStockService::class)->ensureAllocated($order->fresh());

        $this->assertSame(0, (int) StockReservation::query()->active()->where('source_id', $order->id)->sum('quantity'));
        $this->assertSame(1, (int) StockReplenishmentNeed::query()->open()->where('pos_sale_id', $order->id)->value('quantity_needed'));
    }

    // ---------------------------------------------------------------- helpers

    private function belvedereQuantity(Product $product): int
    {
        return (int) ProductStock::query()->where('product_id', $product->id)
            ->where('warehouse_id', Warehouse::fulfillmentWarehouse()->id)->sum('quantity');
    }

    private function belvedereReserved(Product $product): int
    {
        return (int) ProductStock::query()->where('product_id', $product->id)
            ->where('warehouse_id', Warehouse::fulfillmentWarehouse()->id)->sum('reserved');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function stockedProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Produit '.($overrides['ref'] ?? 'X'),
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

    /**
     * @param  list<array{0: Product, 1: int}>  $lines
     */
    private function orderWithLines(array $lines, string $ticket): PosSale
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
            'source' => OrderSource::SHOPIFY,
        ]);
        foreach ($lines as [$product, $qty]) {
            $this->createLine($order, $product, $qty);
        }

        return $order;
    }

    private function createLine(PosSale $order, Product $product, int $qty): PosSaleItem
    {
        return PosSaleItem::create([
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
    }

    /** Mimic ShopifyOrderImporter: delete all lines and recreate them with new ids. */
    private function reimportLines(PosSale $order): void
    {
        $existing = PosSaleItem::query()->where('pos_sale_id', $order->id)->get();
        PosSaleItem::query()->where('pos_sale_id', $order->id)->delete();
        foreach ($existing as $line) {
            $this->createLine($order, Product::findOrFail($line->product_id), (int) $line->quantity);
        }
    }
}
