<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\Reception;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\StockReplenishmentNeed;
use App\Models\StockReservation;
use App\Models\Supplier;
use App\Models\SupplierPurchaseOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\LocationStockReportService;
use App\Services\OrderPhysicalStockService;
use App\Services\StockMovementService;
use App\Support\StockSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationStockManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_physical_stock_declaration_increases_warehouse_without_touching_shopify(): void
    {
        $user = User::factory()->create();
        $product = $this->stockedProduct();
        $belvedere = Warehouse::fulfillmentWarehouse();
        $online = Warehouse::onlineWarehouse();
        $this->assertNotNull($belvedere);
        $this->assertNotNull($online);

        app(StockMovementService::class)->increase(
            $product,
            1,
            'enligne',
            false,
            StockMovement::TYPE_INVENTORY_ADJUSTMENT,
            'test',
            null,
            null,
            $online->id
        );
        $product->refresh();
        $this->assertSame(1, app(StockMovementService::class)->quantityAtWarehouse($product, (int) $online->id));

        $this->actingAs($user)->postJson(route('products.declare-stock', $product), [
            'quantity' => 4,
            'warehouse_id' => $belvedere->id,
            'reason' => StockMovement::REASON_PURCHASE,
            'moved_at' => '2026-08-30',
            'notes' => 'Achat direct magasin',
        ])->assertOk()->assertJson(['success' => true]);

        $product->refresh();
        $service = app(StockMovementService::class);
        $this->assertSame(4, $service->quantityAtWarehouse($product, (int) $belvedere->id));
        $this->assertSame(1, $service->quantityAtWarehouse($product, (int) $online->id));
        $this->assertSame(4, $service->physicalTotal($product));
        $this->assertSame(1, $service->quantityAtWarehouse($product, (int) $online->id));

        $movement = StockMovement::query()
            ->where('product_id', $product->id)
            ->where('type', StockMovement::TYPE_PHYSICAL_IN)
            ->first();
        $this->assertNotNull($movement);
        $this->assertSame(4, (int) $movement->quantity);
        $this->assertSame('Achat', $movement->reason);
        $this->assertSame((int) $user->id, (int) $movement->user_id);
        $this->assertSame('2026-08-30', $movement->moved_at->format('Y-m-d'));
    }

    public function test_physical_stock_adjustment_sets_absolute_quantity_without_touching_shopify(): void
    {
        $user = User::factory()->create();
        $product = $this->stockedProduct();
        $belvedere = Warehouse::fulfillmentWarehouse();
        $online = Warehouse::onlineWarehouse();
        $location = $belvedere->locations()->first();
        if (! $location) {
            $location = \App\Models\WarehouseLocation::create([
                'warehouse_id' => $belvedere->id,
                'code' => 'BEL-TEST',
                'name' => 'Bel Test',
                'status' => 'active',
            ]);
        }

        app(StockMovementService::class)->adjustPhysicalStock($product, 1, (int) $belvedere->id, (int) $location->id, StockMovement::REASON_INVENTORY_CORRECTION);
        app(StockMovementService::class)->increase($product, 1, 'enligne', false, StockMovement::TYPE_INVENTORY_ADJUSTMENT, 'test', null, null, $online->id);

        $this->actingAs($user)->patch(route('stock.magasin.update', $product), [
            'quantity' => 4,
            'warehouse_id' => $belvedere->id,
            'warehouse_location_id' => $location->id,
            'reason' => StockMovement::REASON_INVENTORY_CORRECTION,
            'notes' => 'Inventaire',
        ])->assertRedirect();

        $service = app(StockMovementService::class);
        $product->refresh();
        $this->assertSame(4, $service->quantityAtSlot($product, (int) $belvedere->id, (int) $location->id));
        $this->assertSame(1, $service->quantityAtWarehouse($product, (int) $online->id));

        $movement = StockMovement::query()
            ->where('product_id', $product->id)
            ->where('type', StockMovement::TYPE_STOCK_ADJUSTMENT)
            ->latest('id')
            ->first();
        $this->assertNotNull($movement);
        $this->assertSame(3, (int) $movement->quantity);
        $this->assertSame(1, (int) $movement->quantity_before);
        $this->assertSame(4, (int) $movement->quantity_after);
        $this->assertSame('Inventaire / Correction de stock', $movement->reason);
        $this->assertSame((int) $user->id, (int) $movement->user_id);
        $this->assertSame((int) $belvedere->id, (int) $movement->warehouse_id);
        $this->assertSame((int) $location->id, (int) $movement->warehouse_location_id);
    }

    public function test_adjust_page_lists_physical_slots_with_sans_emplacement(): void
    {
        $user = User::factory()->create();
        $product = $this->stockedProduct(['ref' => 'ADJ-SLOTS']);
        $belvedere = Warehouse::fulfillmentWarehouse();
        $this->assertNotNull($belvedere);

        $location = \App\Models\WarehouseLocation::query()->firstOrCreate(
            ['warehouse_id' => $belvedere->id, 'code' => 'BEL-STOCK'],
            ['name' => 'Bel Stock', 'status' => 'active']
        );

        $service = app(StockMovementService::class);
        $service->adjustPhysicalStock($product, 1, (int) $belvedere->id, (int) $location->id, StockMovement::REASON_INVENTORY_CORRECTION);
        $service->adjustPhysicalStock($product, 2, (int) $belvedere->id, null, StockMovement::REASON_INVENTORY_CORRECTION);

        $response = $this->actingAs($user)->get(route('stock.magasin.edit', $product));
        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('Stock physique actuel', $html);
        $this->assertStringContainsString('BEL-STOCK', $html);
        $this->assertStringContainsString('Sans emplacement', $html);
        $this->assertStringContainsString('Nouvelle quantité réelle', $html);
        $this->assertStringContainsString('Écart calculé', $html);
        $this->assertStringContainsString('stockAdjustForm', $html);
        $this->assertMatchesRegularExpression('/warehouse_name.{1,40}Magasin Belv/u', $html);
    }

    public function test_adjust_rejects_nonexistent_physical_slot(): void
    {
        $user = User::factory()->create();
        $product = $this->stockedProduct();
        $belvedere = Warehouse::fulfillmentWarehouse();
        $location = $belvedere->locations()->first() ?? \App\Models\WarehouseLocation::create([
            'warehouse_id' => $belvedere->id,
            'code' => 'BEL-MISS',
            'name' => 'Missing',
            'status' => 'active',
        ]);

        $this->actingAs($user)->from(route('stock.magasin.edit', $product))->patch(route('stock.magasin.update', $product), [
            'quantity' => 3,
            'warehouse_id' => $belvedere->id,
            'warehouse_location_id' => $location->id,
            'reason' => StockMovement::REASON_INVENTORY_CORRECTION,
        ])->assertRedirect(route('stock.magasin.edit', $product))
            ->assertSessionHas('error');

        $this->assertSame(0, app(StockMovementService::class)->quantityAtSlot($product, (int) $belvedere->id, (int) $location->id));
    }

    public function test_physical_stock_can_be_adjusted_to_zero_without_touching_shopify(): void
    {
        $user = User::factory()->create();
        $product = $this->stockedProduct();
        $belvedere = Warehouse::fulfillmentWarehouse();
        $online = Warehouse::onlineWarehouse();
        $location = $belvedere->locations()->first() ?? \App\Models\WarehouseLocation::create([
            'warehouse_id' => $belvedere->id,
            'code' => 'BEL-ZERO',
            'name' => 'Bel Zero',
            'status' => 'active',
        ]);

        $service = app(StockMovementService::class);
        $service->adjustPhysicalStock($product, 1, (int) $belvedere->id, (int) $location->id, StockMovement::REASON_INVENTORY_CORRECTION);
        $service->increase($product, 5, 'enligne', false, StockMovement::TYPE_INVENTORY_ADJUSTMENT, 'shopify', null, null, $online->id);

        $this->actingAs($user)->patch(route('stock.magasin.update', $product), [
            'quantity' => 0,
            'warehouse_id' => $belvedere->id,
            'warehouse_location_id' => $location->id,
            'reason' => StockMovement::REASON_SALE,
            'notes' => 'Dernière unité vendue',
        ])->assertRedirect()->assertSessionHas('success');

        $product->refresh();
        $this->assertSame(0, $service->quantityAtSlot($product, (int) $belvedere->id, (int) $location->id));
        $this->assertSame(5, $service->quantityAtWarehouse($product, (int) $online->id));
        $this->assertSame(5, (int) $product->stock_enligne);

        $movement = StockMovement::query()
            ->where('product_id', $product->id)
            ->where('type', StockMovement::TYPE_STOCK_ADJUSTMENT)
            ->latest('id')
            ->first();

        $this->assertNotNull($movement);
        $this->assertSame(1, (int) $movement->quantity_before);
        $this->assertSame(0, (int) $movement->quantity_after);
        $this->assertSame(-1, (int) $movement->quantity);
        $this->assertSame('Vente (sortie)', $movement->reason);
        $this->assertSame((int) $user->id, (int) $movement->user_id);
    }

    public function test_declare_modal_set_mode_allows_zero_absolute_quantity(): void
    {
        $user = User::factory()->create();
        $product = $this->stockedProduct();
        $belvedere = Warehouse::fulfillmentWarehouse();
        $online = Warehouse::onlineWarehouse();
        $location = $belvedere->locations()->first() ?? \App\Models\WarehouseLocation::create([
            'warehouse_id' => $belvedere->id,
            'code' => 'BEL-SET',
            'name' => 'Bel Set',
            'status' => 'active',
        ]);

        $service = app(StockMovementService::class);
        $service->adjustPhysicalStock($product, 2, (int) $belvedere->id, (int) $location->id, StockMovement::REASON_INVENTORY_CORRECTION);
        $service->increase($product, 5, 'enligne', false, StockMovement::TYPE_INVENTORY_ADJUSTMENT, 'shopify', null, null, $online->id);

        $this->actingAs($user)->postJson(route('products.declare-stock', $product), [
            'mode' => 'set',
            'quantity' => 0,
            'warehouse_id' => $belvedere->id,
            'warehouse_location_id' => $location->id,
            'reason' => StockMovement::REASON_BREAKAGE,
            'notes' => 'Casse totale',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertSame(0, $service->quantityAtSlot($product->fresh(), (int) $belvedere->id, (int) $location->id));
        $this->assertSame(5, (int) $product->fresh()->stock_enligne);
    }

    public function test_product_list_filters_by_warehouse_location_stock_slot(): void
    {
        $user = User::factory()->create();
        $product = $this->stockedProduct(['ref' => 'FILTER-SKU']);
        $belvedere = Warehouse::fulfillmentWarehouse();
        $this->assertNotNull($belvedere);

        $location = $belvedere->locations()->first();
        if (! $location) {
            $location = \App\Models\WarehouseLocation::create([
                'warehouse_id' => $belvedere->id,
                'code' => 'BEL-STOCK',
                'name' => 'Bel Stock',
                'status' => 'active',
            ]);
        }

        app(StockMovementService::class)->adjustPhysicalStock(
            $product,
            4,
            (int) $belvedere->id,
            (int) $location->id,
            StockMovement::REASON_INVENTORY_CORRECTION,
            'test'
        );

        $this->actingAs($user)->get(route('products.index', [
            'warehouse_id' => $belvedere->id,
            'warehouse_location_id' => $location->id,
            'location_stock_gt_zero' => 1,
        ]))
            ->assertOk()
            ->assertSee('FILTER-SKU');
    }

    public function test_product_list_depot_column_shows_physical_slots_not_default_shopify(): void
    {
        $user = User::factory()->create();
        $belvedere = Warehouse::fulfillmentWarehouse();
        $online = Warehouse::onlineWarehouse();
        $this->assertNotNull($belvedere);
        $this->assertNotNull($online);

        $location = \App\Models\WarehouseLocation::query()->firstOrCreate(
            ['warehouse_id' => $belvedere->id, 'code' => 'TC-R01-E01'],
            ['name' => 'TC-R01-E01', 'status' => 'active']
        );

        $product = $this->stockedProduct([
            'ref' => 'DEPOT-COL-SKU',
            'name' => 'Produit colonne dépôt',
            'warehouse_id' => $online->id,
            'depot' => $online->name,
            'warehouse_location_id' => null,
            'location' => null,
        ]);

        $service = app(StockMovementService::class);
        $service->adjustPhysicalStock(
            $product,
            1,
            (int) $belvedere->id,
            (int) $location->id,
            StockMovement::REASON_INVENTORY_CORRECTION,
            'test'
        );
        $service->increase(
            $product,
            3,
            'enligne',
            false,
            StockMovement::TYPE_INVENTORY_ADJUSTMENT,
            'shopify',
            null,
            null,
            $online->id
        );

        // Zero-qty slot must stay hidden in the depot column (may still appear in filter dropdowns).
        $emptyLoc = \App\Models\WarehouseLocation::query()->firstOrCreate(
            ['warehouse_id' => $belvedere->id, 'code' => 'TC-EMPTY'],
            ['name' => 'Empty', 'status' => 'active']
        );
        ProductStock::query()->create([
            'product_id' => $product->id,
            'warehouse_id' => $belvedere->id,
            'warehouse_location_id' => $emptyLoc->id,
            'quantity' => 0,
            'reserved' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('products.index', ['search' => 'DEPOT-COL-SKU']));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString($belvedere->name, $html);
        $this->assertStringContainsString('TC-R01-E01', $html);
        $this->assertStringContainsString('Qté 1', $html);
        $this->assertStringContainsString('Canal en ligne · Qté 3', $html);
        $this->assertStringContainsString($online->name, $html);

        // Extract the depot cell for this product row to assert zero-qty slots are omitted.
        $this->assertMatchesRegularExpression(
            '/data-lm-col="depot">\s*<div class="space-y-2">.*?TC-R01-E01.*?Canal en ligne · Qté 3.*?<\/div>\s*<\/td>/s',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/data-lm-col="depot">[^<]*<div class="space-y-2">[^<]*TC-EMPTY/s',
            $html
        );

        // Physical block must appear before the online channel label.
        $physicalPos = strpos($html, 'TC-R01-E01');
        $onlinePos = strpos($html, 'Canal en ligne · Qté 3');
        $this->assertNotFalse($physicalPos);
        $this->assertNotFalse($onlinePos);
        $this->assertLessThan($onlinePos, $physicalPos);
    }

    public function test_product_list_depot_column_lists_all_positive_physical_locations(): void
    {
        $user = User::factory()->create();
        $belvedere = Warehouse::fulfillmentWarehouse();
        $this->assertNotNull($belvedere);

        $locA = \App\Models\WarehouseLocation::query()->firstOrCreate(
            ['warehouse_id' => $belvedere->id, 'code' => 'TC-A'],
            ['name' => 'A', 'status' => 'active']
        );
        $locB = \App\Models\WarehouseLocation::query()->firstOrCreate(
            ['warehouse_id' => $belvedere->id, 'code' => 'TC-B'],
            ['name' => 'B', 'status' => 'active']
        );

        $product = $this->stockedProduct(['ref' => 'MULTI-LOC-SKU']);
        $service = app(StockMovementService::class);
        $service->adjustPhysicalStock($product, 2, (int) $belvedere->id, (int) $locA->id, StockMovement::REASON_INVENTORY_CORRECTION);
        $service->adjustPhysicalStock($product, 5, (int) $belvedere->id, (int) $locB->id, StockMovement::REASON_INVENTORY_CORRECTION);

        $this->actingAs($user)->get(route('products.index', ['search' => 'MULTI-LOC-SKU']))
            ->assertOk()
            ->assertSee('TC-A')
            ->assertSee('Qté 2')
            ->assertSee('TC-B')
            ->assertSee('Qté 5');
    }

    public function test_transfer_moves_quantity_without_creating_stock(): void
    {
        $product = $this->stockedProduct();
        $from = Warehouse::fulfillmentWarehouse();
        $to = Warehouse::query()->where('code', 'PRINCIPAL')->first() ?? Warehouse::primary();
        $this->assertNotNull($from);
        $this->assertNotNull($to);
        $this->assertNotSame($from->id, $to->id);

        app(StockMovementService::class)->increase(
            $product,
            10,
            'magasin',
            false,
            StockMovement::TYPE_PURCHASE,
            'test',
            null,
            null,
            $from->id
        );

        app(StockMovementService::class)->transfer($product, 3, (int) $from->id, null, (int) $to->id, null, 'test');

        $this->assertSame(7, app(StockMovementService::class)->quantityAtWarehouse($product->fresh(), (int) $from->id));
        $this->assertSame(3, app(StockMovementService::class)->quantityAtWarehouse($product->fresh(), (int) $to->id));
        $this->assertSame(10, (int) ProductStock::query()->where('product_id', $product->id)->sum('quantity'));
    }

    public function test_reception_adds_stock_once_and_invoice_conversion_does_not(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'AZMI FRERES']);
        $product = $this->stockedProduct();
        $warehouse = Warehouse::fulfillmentWarehouse();

        $this->actingAs($user)->post(route('receptions.store'), [
            'reception_number' => 'BR-TEST-1',
            'supplier_id' => $supplier->id,
            'reception_date' => '2026-08-19',
            'currency' => 'dh - MAD',
            'status' => 'accepté',
            'warehouse_id' => $warehouse->id,
            'items' => [[
                'product_id' => $product->id,
                'ref' => $product->ref,
                'designation' => $product->name,
                'quantity' => 10,
                'unit_price' => 50,
                'tax_rate' => 20,
                'discount' => 0,
                'discount_type' => 'fixed',
            ]],
        ])->assertRedirect();

        $this->assertSame(10, app(StockMovementService::class)->quantityAtWarehouse($product->fresh(), (int) $warehouse->id));
        $this->assertSame(1, StockMovement::query()->where('type', StockMovement::TYPE_PURCHASE)->count());

        $reception = Reception::query()->firstOrFail();
        $this->actingAs($user)->postJson(route('receptions.bulk-convert'), [
            'ids' => [$reception->id],
            'mode' => 'separate',
        ])->assertOk();

        $this->assertSame(10, app(StockMovementService::class)->quantityAtWarehouse($product->fresh(), (int) $warehouse->id));
        $this->assertSame(1, StockMovement::query()->where('type', StockMovement::TYPE_PURCHASE)->count());
    }

    public function test_reception_converts_to_delivery_note_without_duplicating_stock(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'AZMI FRERES']);
        $product = $this->stockedProduct();
        $warehouse = Warehouse::fulfillmentWarehouse();

        $this->actingAs($user)->post(route('receptions.store'), [
            'reception_number' => 'BR-TEST-BL-1',
            'supplier_id' => $supplier->id,
            'reception_date' => '2026-08-19',
            'currency' => 'dh - MAD',
            'status' => 'accepté',
            'warehouse_id' => $warehouse->id,
            'items' => [[
                'product_id' => $product->id,
                'ref' => $product->ref,
                'designation' => $product->name,
                'quantity' => 10,
                'unit_price' => 50,
                'tax_rate' => 20,
                'discount' => 0,
                'discount_type' => 'fixed',
            ]],
        ])->assertRedirect();

        $reception = Reception::query()->firstOrFail();
        $this->actingAs($user)->postJson(route('receptions.bulk-convert-delivery-notes'), [
            'ids' => [$reception->id],
            'mode' => 'separate',
        ])->assertOk();

        $reception->refresh();
        $this->assertNotNull($reception->converted_supplier_delivery_note_id);
        $this->assertNull($reception->converted_supplier_invoice_id);

        $note = \App\Models\SupplierDeliveryNote::query()->firstOrFail();
        $this->assertSame(10, (int) $note->items()->first()->quantity);
        $this->assertSame($reception->reception_number, $note->items()->first()->source_document_reference);
        $this->assertNotNull($note->stock_applied_at);
        $this->assertSame(10, app(StockMovementService::class)->quantityAtWarehouse($product->fresh(), (int) $warehouse->id));
        $this->assertSame(1, StockMovement::query()->where('type', StockMovement::TYPE_PURCHASE)->count());

        $this->actingAs($user)->postJson(route('receptions.bulk-convert-delivery-notes'), [
            'ids' => [$reception->id],
            'mode' => 'separate',
        ])->assertStatus(422);

        $this->actingAs($user)->postJson(route('receptions.bulk-convert'), [
            'ids' => [$reception->id],
            'mode' => 'separate',
        ])->assertOk();

        $this->assertNotNull($reception->fresh()->converted_supplier_invoice_id);
        $this->assertSame(1, StockMovement::query()->where('type', StockMovement::TYPE_PURCHASE)->count());
    }

    public function test_combined_receptions_convert_to_one_delivery_note_with_origin_on_lines(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Fournisseur combiné']);
        $product = $this->stockedProduct();
        $warehouse = Warehouse::fulfillmentWarehouse();

        foreach (['BR-COMB-1', 'BR-COMB-2'] as $number) {
            $this->actingAs($user)->post(route('receptions.store'), [
                'reception_number' => $number,
                'supplier_id' => $supplier->id,
                'reception_date' => '2026-08-19',
                'currency' => 'dh - MAD',
                'status' => 'accepté',
                'warehouse_id' => $warehouse->id,
                'items' => [[
                    'product_id' => $product->id,
                    'ref' => $product->ref,
                    'designation' => $product->name,
                    'quantity' => 4,
                    'unit_price' => 10,
                    'tax_rate' => 20,
                    'discount' => 0,
                    'discount_type' => 'fixed',
                ]],
            ])->assertRedirect();
        }

        $ids = Reception::query()->orderBy('id')->pluck('id')->all();
        $this->actingAs($user)->postJson(route('receptions.bulk-convert-delivery-notes'), [
            'ids' => $ids,
            'mode' => 'combined',
        ])->assertOk();

        $this->assertSame(1, \App\Models\SupplierDeliveryNote::query()->count());
        $note = \App\Models\SupplierDeliveryNote::query()->firstOrFail();
        $this->assertCount(2, $note->items);
        $this->assertEqualsCanonicalizing(
            ['BR-COMB-1', 'BR-COMB-2'],
            $note->items->pluck('source_document_reference')->all()
        );
        $this->assertSame(8, app(StockMovementService::class)->quantityAtWarehouse($product->fresh(), (int) $warehouse->id));
        $this->assertSame(2, StockMovement::query()->where('type', StockMovement::TYPE_PURCHASE)->count());
    }

    public function test_split_reception_distributes_quantities(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Fournisseur']);
        $product = $this->stockedProduct();
        $online = Warehouse::onlineWarehouse();
        $store = Warehouse::fulfillmentWarehouse();

        $this->actingAs($user)->post(route('receptions.store'), [
            'reception_number' => 'BR-SPLIT',
            'supplier_id' => $supplier->id,
            'reception_date' => '2026-08-19',
            'currency' => 'dh - MAD',
            'status' => 'accepté',
            'warehouse_id' => $store->id,
            'items' => [[
                'product_id' => $product->id,
                'ref' => $product->ref,
                'designation' => $product->name,
                'quantity' => 20,
                'unit_price' => 10,
                'tax_rate' => 20,
                'discount' => 0,
                'discount_type' => 'fixed',
                'allocations' => [
                    ['warehouse_id' => $online->id, 'quantity' => 15],
                    ['warehouse_id' => $store->id, 'quantity' => 5],
                ],
            ]],
        ])->assertRedirect();

        $this->assertSame(15, app(StockMovementService::class)->quantityAtWarehouse($product->fresh(), (int) $online->id));
        $this->assertSame(5, app(StockMovementService::class)->quantityAtWarehouse($product->fresh(), (int) $store->id));
    }

    public function test_delivery_note_adds_stock_once_and_convert_does_not_duplicate(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Fournisseur BL']);
        $product = $this->stockedProduct();
        $warehouse = Warehouse::fulfillmentWarehouse();

        $this->actingAs($user)->post(route('supplier-delivery-notes.store'), [
            'delivery_number' => 'BL-TEST-1',
            'supplier_id' => $supplier->id,
            'delivery_date' => '2026-08-20',
            'currency' => 'dh - MAD',
            'status' => 'accepté',
            'warehouse_id' => $warehouse->id,
            'items' => [[
                'product_id' => $product->id,
                'ref' => $product->ref,
                'designation' => $product->name,
                'quantity' => 10,
                'unit_price' => 50,
                'tax_rate' => 20,
                'discount' => 0,
                'discount_type' => 'fixed',
                'warehouse_id' => $warehouse->id,
            ]],
        ])->assertRedirect(route('supplier-delivery-notes.index'));

        $this->assertSame(10, app(StockMovementService::class)->quantityAtWarehouse($product->fresh(), (int) $warehouse->id));
        $this->assertSame(1, StockMovement::query()->where('type', StockMovement::TYPE_PURCHASE)->count());

        $deliveryNote = \App\Models\SupplierDeliveryNote::query()->firstOrFail();
        $this->assertNotNull($deliveryNote->stock_applied_at);

        $this->actingAs($user)->postJson(route('supplier-delivery-notes.bulk-convert'), [
            'ids' => [$deliveryNote->id],
            'mode' => 'separate',
        ])->assertOk();

        $this->assertSame(10, app(StockMovementService::class)->quantityAtWarehouse($product->fresh(), (int) $warehouse->id));
        $this->assertSame(1, StockMovement::query()->where('type', StockMovement::TYPE_PURCHASE)->count());

        $invoice = \App\Models\SupplierInvoice::query()->firstOrFail();
        $this->assertNotNull($invoice->stock_applied_at);
    }

    public function test_direct_supplier_invoice_adds_stock_on_create_without_duplicate_on_receive(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Fournisseur Facture']);
        $productA = $this->stockedProduct(['name' => 'Produit A', 'ref' => 'A-1']);
        $productB = $this->stockedProduct(['name' => 'Produit B', 'ref' => 'B-1']);
        $online = Warehouse::onlineWarehouse();
        $store = Warehouse::fulfillmentWarehouse();

        $this->actingAs($user)->post(route('supplier-invoices.store'), [
            'invoice_number' => 'FSI-TEST-1',
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-08-20',
            'currency' => 'dh - MAD',
            'warehouse_id' => $store->id,
            'items' => [
                [
                    'product_id' => $productA->id,
                    'ref' => $productA->ref,
                    'designation' => $productA->name,
                    'quantity' => 10,
                    'unit_price' => 10,
                    'tax_rate' => 20,
                    'discount' => 0,
                    'discount_type' => 'fixed',
                    'warehouse_id' => $online->id,
                ],
                [
                    'product_id' => $productB->id,
                    'ref' => $productB->ref,
                    'designation' => $productB->name,
                    'quantity' => 10,
                    'unit_price' => 10,
                    'tax_rate' => 20,
                    'discount' => 0,
                    'discount_type' => 'fixed',
                    'warehouse_id' => $store->id,
                ],
            ],
        ])->assertRedirect();

        $invoice = \App\Models\SupplierInvoice::query()->firstOrFail();
        $this->assertNotNull($invoice->stock_applied_at);
        $this->assertSame(10, app(StockMovementService::class)->quantityAtWarehouse($productA->fresh(), (int) $online->id));
        $this->assertSame(10, app(StockMovementService::class)->quantityAtWarehouse($productB->fresh(), (int) $store->id));
        $movementCount = StockMovement::query()->where('type', StockMovement::TYPE_PURCHASE)->count();
        $this->assertSame(2, $movementCount);

        $this->actingAs($user)->post(route('supplier-invoices.receive-stock', $invoice), [
            'warehouse_id' => $online->id,
            'items' => [
                [
                    'product_id' => $productA->id,
                    'quantity' => 10,
                    'warehouse_id' => $online->id,
                ],
            ],
        ])->assertSessionHas('error');

        $this->assertSame(10, app(StockMovementService::class)->quantityAtWarehouse($productA->fresh(), (int) $online->id));
        $this->assertSame($movementCount, StockMovement::query()->where('type', StockMovement::TYPE_PURCHASE)->count());
        $this->assertSame(0, Reception::query()->count());
    }

    public function test_purchase_order_does_not_increase_physical_stock(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Fournisseur BC']);
        $product = $this->stockedProduct();
        $warehouse = Warehouse::fulfillmentWarehouse();

        $this->actingAs($user)->post(route('supplier-purchase-orders.store'), [
            'order_number' => 'BCF-TEST-50',
            'supplier_id' => $supplier->id,
            'order_date' => '2026-08-20',
            'currency' => 'dh - MAD',
            'stock_location' => $warehouse->name,
            'items' => [[
                'product_id' => $product->id,
                'ref' => $product->ref,
                'designation' => $product->name,
                'quantity' => 50,
                'unit_price' => 10,
                'tax_rate' => 20,
                'discount' => 0,
                'discount_type' => 'fixed',
            ]],
        ]);

        $this->assertSame(0, app(StockMovementService::class)->quantityAtWarehouse($product->fresh(), (int) $warehouse->id));
        $this->assertSame(0, StockMovement::query()->where('type', StockMovement::TYPE_PURCHASE)->count());
    }

    public function test_partial_reception_tracks_remaining(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Fournisseur']);
        $product = $this->stockedProduct();
        $warehouse = Warehouse::fulfillmentWarehouse();

        $order = SupplierPurchaseOrder::create([
            'order_number' => 'BC-TEST-1',
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'currency' => 'dh - MAD',
            'stock_location' => $warehouse->name,
            'subtotal' => 100,
            'total' => 100,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'ref' => $product->ref,
            'designation' => $product->name,
            'quantity' => 10,
            'unit_price' => 10,
            'tax_rate' => 20,
            'discount' => 0,
            'discount_type' => 'fixed',
            'line_total' => 100,
        ]);

        $payload = fn (string $number, int $qty) => [
            'reception_number' => $number,
            'supplier_id' => $supplier->id,
            'reception_date' => '2026-08-19',
            'currency' => 'dh - MAD',
            'status' => 'accepté',
            'warehouse_id' => $warehouse->id,
            'supplier_purchase_order_id' => $order->id,
            'items' => [[
                'product_id' => $product->id,
                'ref' => $product->ref,
                'designation' => $product->name,
                'quantity' => $qty,
                'unit_price' => 10,
                'tax_rate' => 20,
                'discount' => 0,
                'discount_type' => 'fixed',
            ]],
        ];

        $this->actingAs($user)->post(route('receptions.store'), $payload('BR-P1', 6))->assertRedirect();
        $this->assertSame(6, app(StockMovementService::class)->quantityAtWarehouse($product->fresh(), (int) $warehouse->id));

        $this->actingAs($user)->post(route('receptions.store'), $payload('BR-OVER', 5))->assertSessionHas('error');

        $this->actingAs($user)->post(route('receptions.store'), $payload('BR-P2', 4))->assertRedirect();
        $this->assertSame(10, app(StockMovementService::class)->quantityAtWarehouse($product->fresh(), (int) $warehouse->id));
    }

    public function test_order_deducts_belvedere_and_never_uses_shopify_stock(): void
    {
        $product = $this->stockedProduct(['source' => 'shopify']);
        $online = Warehouse::onlineWarehouse();
        $store = Warehouse::fulfillmentWarehouse();
        $service = app(StockMovementService::class);

        $service->increase($product, 10, 'enligne', false, StockMovement::TYPE_PURCHASE, null, null, null, $online->id);
        $service->increase($product, 2, 'magasin', false, StockMovement::TYPE_PURCHASE, null, null, null, $store->id);

        $order = $this->orderWithProduct($product, 1);
        $result = app(OrderPhysicalStockService::class)->process($order);

        $this->assertSame([], $result['unavailable']);
        $this->assertSame(1, app(StockMovementService::class)->quantityAtWarehouse($product->fresh(), (int) $store->id));
        $this->assertSame(10, app(StockMovementService::class)->quantityAtWarehouse($product->fresh(), (int) $online->id));
        $this->assertTrue($order->fresh()->physical_stock_processed_at !== null);
    }

    public function test_unavailable_physical_stock_creates_replenishment_without_negative(): void
    {
        $product = $this->stockedProduct(['source' => 'shopify']);
        $online = Warehouse::onlineWarehouse();
        $store = Warehouse::fulfillmentWarehouse();
        app(StockMovementService::class)->increase($product, 10, 'enligne', false, StockMovement::TYPE_PURCHASE, null, null, null, $online->id);

        $order = $this->orderWithProduct($product, 1);
        $result = app(OrderPhysicalStockService::class)->process($order);

        $this->assertNotEmpty($result['unavailable']);
        $this->assertSame(0, app(StockMovementService::class)->quantityAtWarehouse($product->fresh(), (int) $store->id));
        $this->assertSame(10, app(StockMovementService::class)->quantityAtWarehouse($product->fresh(), (int) $online->id));
        $this->assertSame(1, StockReplenishmentNeed::query()->open()->count());
    }

    public function test_location_report_excludes_zero_and_ignores_shopify_qty(): void
    {
        $present = $this->stockedProduct(['name' => 'Present', 'ref' => 'P-1', 'cost_price_ht' => 100]);
        $absent = $this->stockedProduct(['name' => 'Absent', 'ref' => 'A-1', 'cost_price_ht' => 50]);
        $store = Warehouse::fulfillmentWarehouse();
        $online = Warehouse::onlineWarehouse();
        $service = app(StockMovementService::class);

        $service->increase($present, 5, 'magasin', false, StockMovement::TYPE_PURCHASE, null, null, null, $store->id);
        $service->increase($absent, 10, 'enligne', false, StockMovement::TYPE_PURCHASE, null, null, null, $online->id);

        $report = app(LocationStockReportService::class)->report($store);
        $skus = $report['rows']->pluck('sku')->all();

        $this->assertContains('P-1', $skus);
        $this->assertNotContains('A-1', $skus);
        $this->assertSame(1, $report['references']);
        $this->assertSame(5, $report['quantity']);
    }

    public function test_inventory_count_zero_sets_stock_to_zero_and_creates_adjustment(): void
    {
        $user = User::factory()->create();
        $product = $this->stockedProduct(['ref' => 'INV-ZERO']);
        $warehouse = Warehouse::fulfillmentWarehouse();
        $location = $warehouse->locations()->first() ?? \App\Models\WarehouseLocation::create([
            'warehouse_id' => $warehouse->id,
            'code' => 'INV-LOC',
            'name' => 'Inv Loc',
            'status' => 'active',
        ]);

        $service = app(StockMovementService::class);
        $service->adjustPhysicalStock($product, 1, (int) $warehouse->id, (int) $location->id, StockMovement::REASON_INVENTORY_CORRECTION);
        $slot = ProductStock::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('warehouse_location_id', $location->id)
            ->firstOrFail();

        $this->actingAs($user)->post(route('stock.locations.count.store', $warehouse), [
            'counts' => [[
                'product_id' => $product->id,
                'product_stock_id' => $slot->id,
                'warehouse_location_id' => $location->id,
                'counted' => 0,
            ]],
        ])->assertRedirect(route('stock.locations.show', $warehouse));

        $this->assertSame(0, $service->quantityAtSlot($product->fresh(), (int) $warehouse->id, (int) $location->id));

        $movement = StockMovement::query()
            ->where('product_id', $product->id)
            ->where('type', StockMovement::TYPE_INVENTORY_ADJUSTMENT)
            ->latest('id')
            ->first();
        $this->assertNotNull($movement);
        $this->assertSame(1, (int) $movement->quantity_before);
        $this->assertSame(0, (int) $movement->quantity_after);
        $this->assertSame(-1, (int) $movement->quantity);
        $this->assertSame((int) $user->id, (int) $movement->user_id);
    }

    public function test_inventory_count_ten_to_zero_creates_minus_ten_adjustment(): void
    {
        $user = User::factory()->create();
        $product = $this->stockedProduct(['ref' => 'INV-TEN']);
        $warehouse = Warehouse::fulfillmentWarehouse();
        $service = app(StockMovementService::class);
        $service->adjustPhysicalStock($product, 10, (int) $warehouse->id, null, StockMovement::REASON_INVENTORY_CORRECTION);
        $slot = ProductStock::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->whereNull('warehouse_location_id')
            ->firstOrFail();

        $this->actingAs($user)->post(route('stock.locations.count.store', $warehouse), [
            'counts' => [[
                'product_id' => $product->id,
                'product_stock_id' => $slot->id,
                'warehouse_location_id' => null,
                'counted' => 0,
            ]],
        ])->assertRedirect();

        $this->assertSame(0, $service->quantityAtWarehouse($product->fresh(), (int) $warehouse->id));
        $movement = StockMovement::query()
            ->where('product_id', $product->id)
            ->where('type', StockMovement::TYPE_INVENTORY_ADJUSTMENT)
            ->latest('id')
            ->first();
        $this->assertSame(-10, (int) $movement->quantity);
        $this->assertSame(10, (int) $movement->quantity_before);
        $this->assertSame(0, (int) $movement->quantity_after);
    }

    public function test_inventory_count_zero_on_null_location_ignores_product_default_location(): void
    {
        $user = User::factory()->create();
        $product = $this->stockedProduct(['ref' => 'INV-NULL-DEF']);
        $warehouse = Warehouse::fulfillmentWarehouse();
        $defaultLocation = $warehouse->locations()->first() ?? \App\Models\WarehouseLocation::create([
            'warehouse_id' => $warehouse->id,
            'code' => 'DEF-LOC',
            'name' => 'Default Loc',
            'status' => 'active',
        ]);
        // Real stock is on the null-emplacement slot, but the product default points elsewhere
        // (same shape as Dépôt principal lines that show Emplacement "—").
        $product->forceFill(['warehouse_location_id' => $defaultLocation->id])->save();

        $service = app(StockMovementService::class);
        $service->adjustPhysicalStock($product, 3, (int) $warehouse->id, null, StockMovement::REASON_INVENTORY_CORRECTION);
        $slot = ProductStock::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->whereNull('warehouse_location_id')
            ->firstOrFail();

        $this->assertSame(3, (int) $slot->quantity);
        $this->assertSame(0, $service->quantityAtSlot($product, (int) $warehouse->id, (int) $defaultLocation->id));

        $this->actingAs($user)->post(route('stock.locations.count.store', $warehouse), [
            'counts' => [[
                'product_id' => $product->id,
                'product_stock_id' => $slot->id,
                'warehouse_location_id' => '',
                'counted' => 0,
            ]],
        ])->assertRedirect(route('stock.locations.show', $warehouse));

        $this->assertSame(0, $service->quantityAtSlot($product->fresh(), (int) $warehouse->id, null));
        $this->assertSame(0, $service->quantityAtWarehouse($product->fresh(), (int) $warehouse->id));

        $movement = StockMovement::query()
            ->where('product_id', $product->id)
            ->where('type', StockMovement::TYPE_INVENTORY_ADJUSTMENT)
            ->latest('id')
            ->first();
        $this->assertNotNull($movement);
        $this->assertSame(-3, (int) $movement->quantity);
        $this->assertSame(3, (int) $movement->quantity_before);
        $this->assertSame(0, (int) $movement->quantity_after);
        $this->assertNull($movement->warehouse_location_id);
    }

    public function test_zero_qty_excluded_from_warehouse_and_location_report_cards(): void
    {
        $product = $this->stockedProduct(['ref' => 'ZERO-CARD', 'cost_price_ht' => 50]);
        $warehouse = Warehouse::fulfillmentWarehouse();
        $location = $warehouse->locations()->first() ?? \App\Models\WarehouseLocation::create([
            'warehouse_id' => $warehouse->id,
            'code' => 'Z-LOC',
            'name' => 'Z',
            'status' => 'active',
        ]);
        $service = app(StockMovementService::class);
        $service->adjustPhysicalStock($product, 3, (int) $warehouse->id, (int) $location->id, StockMovement::REASON_INVENTORY_CORRECTION);
        $service->adjustPhysicalStock($product, 0, (int) $warehouse->id, (int) $location->id, StockMovement::REASON_INVENTORY_CORRECTION);

        $report = app(LocationStockReportService::class)->report($warehouse);
        $this->assertNotContains('ZERO-CARD', $report['rows']->pluck('sku')->all());
        $this->assertSame(0, $report['references']);
        $this->assertSame(0, $report['quantity']);
        $this->assertSame(0.0, $report['value_ht']);
        $this->assertSame(0.0, $report['value_ttc']);

        $locReport = app(LocationStockReportService::class)->report($warehouse, null, (int) $location->id);
        $this->assertSame(0, $locReport['references']);
    }

    public function test_services_excluded_from_stock_report_and_cleanup(): void
    {
        $warehouse = Warehouse::fulfillmentWarehouse();
        $serviceProduct = Product::create([
            'name' => 'Prestation test',
            'ref' => 'SVC-1',
            'item_kind' => Product::KIND_SERVICE,
            'element_type' => 'Service',
            'stock_quantity' => 0,
            'stock_magasin' => 0,
        ]);
        ProductStock::create([
            'product_id' => $serviceProduct->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 4,
            'reserved' => 0,
        ]);

        $result = app(\App\Services\ServiceStockCleanupService::class)->neutralizeNonStockableSlots();
        $this->assertSame(1, $result['slots_cleared']);
        $this->assertSame(0, (int) ProductStock::query()->where('product_id', $serviceProduct->id)->value('quantity'));

        $stocked = $this->stockedProduct(['ref' => 'PHYS-1', 'cost_price_ht' => 10]);
        app(StockMovementService::class)->adjustPhysicalStock($stocked, 2, (int) $warehouse->id, null, StockMovement::REASON_INVENTORY_CORRECTION);

        $report = app(LocationStockReportService::class)->report($warehouse);
        $skus = $report['rows']->pluck('sku')->all();
        $this->assertContains('PHYS-1', $skus);
        $this->assertNotContains('SVC-1', $skus);
    }

    public function test_missing_purchase_cost_is_not_invented_in_report_or_export_row(): void
    {
        $product = $this->stockedProduct([
            'ref' => 'NO-COST',
            'cost_price_ht' => null,
            'last_purchase_price' => null,
        ]);
        $warehouse = Warehouse::fulfillmentWarehouse();
        app(StockMovementService::class)->adjustPhysicalStock($product, 2, (int) $warehouse->id, null, StockMovement::REASON_INVENTORY_CORRECTION);

        $report = app(LocationStockReportService::class)->report($warehouse);
        $row = $report['rows']->firstWhere('sku', 'NO-COST');
        $this->assertNotNull($row);
        $this->assertFalse($row->has_purchase_cost);
        $this->assertNull($row->price_ht);
        $this->assertSame(LocationStockReportService::PRICE_SOURCE_NONE, $row->price_source);
        $this->assertSame(0.0, $row->value_ht);
    }

    public function test_real_purchase_price_used_from_last_purchase(): void
    {
        $product = $this->stockedProduct([
            'ref' => 'WITH-COST',
            'cost_price_ht' => 999,
            'last_purchase_price' => 42.5,
            'vat_category' => 'TVA (20%)',
        ]);
        $warehouse = Warehouse::fulfillmentWarehouse();
        app(StockMovementService::class)->adjustPhysicalStock($product, 2, (int) $warehouse->id, null, StockMovement::REASON_INVENTORY_CORRECTION);

        $report = app(LocationStockReportService::class)->report($warehouse);
        $row = $report['rows']->firstWhere('sku', 'WITH-COST');
        $this->assertTrue($row->has_purchase_cost);
        // last_purchase_price is TTC — HT = 42.5 / 1.2
        $this->assertSame(35.42, $row->price_ht);
        $this->assertSame(42.5, $row->price_ttc);
        $this->assertSame(LocationStockReportService::PRICE_SOURCE_LAST_PURCHASE, $row->price_source);
        $this->assertSame(70.84, $row->value_ht);
        $this->assertSame(85.0, $row->value_ttc);
    }

    public function test_purchase_price_ttc_is_not_inflated_with_vat_again(): void
    {
        $product = $this->stockedProduct([
            'ref' => 'PA-TTC-285',
            'cost_price_ht' => null,
            'last_purchase_price' => 285,
            'vat_category' => 'TVA (20%)',
        ]);
        $warehouse = Warehouse::fulfillmentWarehouse();
        app(StockMovementService::class)->adjustPhysicalStock($product, 1, (int) $warehouse->id, null, StockMovement::REASON_INVENTORY_CORRECTION);

        $report = app(LocationStockReportService::class)->report($warehouse);
        $row = $report['rows']->firstWhere('sku', 'PA-TTC-285');
        $this->assertNotNull($row);
        $this->assertSame(237.5, $row->price_ht);
        $this->assertSame(285.0, $row->price_ttc);
        $this->assertSame(237.5, $row->value_ht);
        $this->assertSame(285.0, $row->value_ttc);
        $this->assertSame(47.5, $report['value_vat']);
    }

    public function test_product_cost_ht_fallback_still_derives_ttc(): void
    {
        $product = $this->stockedProduct([
            'ref' => 'COST-HT-ONLY',
            'cost_price_ht' => 100,
            'last_purchase_price' => null,
            'vat_category' => 'TVA (20%)',
        ]);
        $warehouse = Warehouse::fulfillmentWarehouse();
        app(StockMovementService::class)->adjustPhysicalStock($product, 3, (int) $warehouse->id, null, StockMovement::REASON_INVENTORY_CORRECTION);

        $report = app(LocationStockReportService::class)->report($warehouse);
        $row = $report['rows']->firstWhere('sku', 'COST-HT-ONLY');
        $this->assertSame(100.0, $row->price_ht);
        $this->assertSame(120.0, $row->price_ttc);
        $this->assertSame(300.0, $row->value_ht);
        $this->assertSame(360.0, $row->value_ttc);
    }

    public function test_purchase_price_can_be_set_from_location_stock_and_updates_product_sheet_field(): void
    {
        $user = User::factory()->create(['name' => 'Stock Manager']);
        $product = $this->stockedProduct([
            'ref' => 'SET-PA-FROM-STOCK',
            'cost_price_ht' => null,
            'last_purchase_price' => null,
            'vat_category' => 'TVA (20%)',
        ]);
        $warehouse = Warehouse::fulfillmentWarehouse();
        app(StockMovementService::class)->adjustPhysicalStock(
            $product,
            2,
            (int) $warehouse->id,
            null,
            StockMovement::REASON_INVENTORY_CORRECTION
        );

        $before = app(LocationStockReportService::class)->report($warehouse);
        $beforeRow = $before['rows']->firstWhere('sku', 'SET-PA-FROM-STOCK');
        $this->assertFalse($beforeRow->has_purchase_cost);
        $this->assertSame(0.0, $before['value_ttc']);

        $response = $this->actingAs($user)->patchJson(
            route('stock.locations.purchase-price', [$warehouse, $product]),
            ['last_purchase_price' => 70]
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('price_ttc', 70)
            ->assertJsonPath('price_ht', 58.33)
            ->assertJsonPath('totals.value_ttc', 140)
            ->assertJsonPath('totals.value_ht', 116.66);

        $product->refresh();
        $this->assertSame(70.0, (float) $product->last_purchase_price);
        $this->assertSame((int) $user->id, (int) $product->last_purchase_price_updated_by);
        $this->assertNotNull($product->last_purchase_price_updated_at);

        $after = app(LocationStockReportService::class)->report($warehouse);
        $afterRow = $after['rows']->firstWhere('sku', 'SET-PA-FROM-STOCK');
        $this->assertTrue($afterRow->has_purchase_cost);
        $this->assertSame(70.0, $afterRow->price_ttc);
        $this->assertSame(58.33, $afterRow->price_ht);
        $this->assertSame(116.66, $afterRow->value_ht);
        $this->assertSame(140.0, $afterRow->value_ttc);
        $this->assertSame(LocationStockReportService::PRICE_SOURCE_LAST_PURCHASE, $afterRow->price_source);
    }

    public function test_empty_warehouse_can_be_deleted_or_archived_with_history(): void
    {
        $user = User::factory()->create();
        $empty = Warehouse::create([
            'name' => 'Dépôt vide',
            'code' => 'EMPTY-DEL',
            'kind' => Warehouse::KIND_PHYSICAL,
            'status' => Warehouse::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)->delete(route('warehouses.destroy', $empty))
            ->assertRedirect(route('settings.stock', ['tab' => 'depots']));
        $this->assertDatabaseMissing('warehouses', ['id' => $empty->id]);

        $withHistory = Warehouse::create([
            'name' => 'Dépôt historique',
            'code' => 'HIST-ARCH',
            'kind' => Warehouse::KIND_PHYSICAL,
            'status' => Warehouse::STATUS_ACTIVE,
        ]);
        $product = $this->stockedProduct(['ref' => 'HIST-P']);
        app(StockMovementService::class)->adjustPhysicalStock($product, 2, (int) $withHistory->id, null, StockMovement::REASON_INVENTORY_CORRECTION);
        app(StockMovementService::class)->adjustPhysicalStock($product, 0, (int) $withHistory->id, null, StockMovement::REASON_INVENTORY_CORRECTION);

        $this->actingAs($user)->delete(route('warehouses.destroy', $withHistory))
            ->assertRedirect(route('settings.stock', ['tab' => 'depots']));
        $withHistory->refresh();
        $this->assertTrue($withHistory->isArchived());
        $this->assertNotNull($withHistory->archived_at);
    }

    public function test_warehouse_with_stock_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::create([
            'name' => 'Dépôt plein',
            'code' => 'FULL-BLOCK',
            'kind' => Warehouse::KIND_PHYSICAL,
            'status' => Warehouse::STATUS_ACTIVE,
        ]);
        $product = $this->stockedProduct(['ref' => 'FULL-P']);
        app(StockMovementService::class)->adjustPhysicalStock($product, 5, (int) $warehouse->id, null, StockMovement::REASON_INVENTORY_CORRECTION);

        $this->actingAs($user)->delete(route('warehouses.destroy', $warehouse))
            ->assertRedirect(route('settings.stock', ['tab' => 'depots']))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id, 'status' => Warehouse::STATUS_ACTIVE]);
    }

    public function test_available_stock_uses_physical_magasin_not_shopify_quantity(): void
    {
        $product = $this->stockedProduct([
            'source' => 'shopify',
            'shopify_product_id' => '999',
            'stock_quantity' => 40,
            'stock_enligne' => 40,
            'stock_magasin' => 12,
            'stock_reserved' => 2,
        ]);

        $this->assertSame(12, $product->physicalStock());
        $this->assertSame(2, $product->reservedStock());
        $this->assertSame(10, $product->availableStock());
        $this->assertSame(40, $product->onlineChannelStock());
    }

    public function test_reserve_and_release_stock_without_physical_exit(): void
    {
        $product = $this->stockedProduct();
        $belvedere = Warehouse::fulfillmentWarehouse();
        $this->assertNotNull($belvedere);
        $belvedere->update(['available_for_shopify' => true]);

        $service = app(StockMovementService::class);
        $service->declarePhysicalStock(
            $product,
            10,
            (int) $belvedere->id,
            null,
            StockMovement::REASON_PURCHASE
        );
        $product->refresh();

        $reservation = $service->reserveStock($product, 3, (int) $belvedere->id, null, null, [
            'source_type' => 'order',
            'source_id' => 123,
            'document_reference' => 'CMD-123',
        ]);

        $product->refresh();
        $this->assertSame(10, $product->physicalStock());
        $this->assertSame(3, $product->reservedStock());
        $this->assertSame(7, $product->availableStock());
        $this->assertSame(7, $service->shopifyAvailableQuantity($product));
        $this->assertTrue($reservation->isActive());

        // Idempotent re-reserve same source
        $again = $service->reserveStock($product, 3, (int) $belvedere->id, null, null, [
            'source_type' => 'order',
            'source_id' => 123,
        ]);
        $this->assertSame($reservation->id, $again->id);
        $this->assertSame(3, $product->fresh()->reservedStock());

        $service->releaseReservation($reservation, 'Annulation');
        $product->refresh();
        $this->assertSame(10, $product->physicalStock());
        $this->assertSame(0, $product->reservedStock());
        $this->assertSame(10, $product->availableStock());
        $this->assertSame(\App\Models\StockReservation::STATUS_RELEASED, $reservation->fresh()->status);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => StockMovement::TYPE_RESERVATION,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => StockMovement::TYPE_RESERVATION_RELEASE,
        ]);
    }

    public function test_cannot_reserve_more_than_available(): void
    {
        $product = $this->stockedProduct();
        $belvedere = Warehouse::fulfillmentWarehouse();
        $service = app(StockMovementService::class);
        $service->declarePhysicalStock($product, 2, (int) $belvedere->id, null, StockMovement::REASON_PURCHASE);

        $this->expectException(\RuntimeException::class);
        $service->reserveStock($product, 5, (int) $belvedere->id);
    }

    public function test_allocate_uses_belvedere_default_title_stock_and_only_needs_missing_sku(): void
    {
        // FAST12907 shape: order lines without product_variant_id, Belvédère stock tagged on Default Title,
        // Shopify mirror must never satisfy allocation.
        Setting::set('stock_picking_enabled', '1');
        StockSettings::recordPickingActivation(now()->subMinute());

        $belvedere = Warehouse::fulfillmentWarehouse();
        $online = Warehouse::onlineWarehouse();
        $this->assertNotNull($belvedere);
        $location = $belvedere->locations()->first() ?? \App\Models\WarehouseLocation::create([
            'warehouse_id' => $belvedere->id,
            'code' => 'BEL-STOCK',
            'name' => 'Bel Stock',
            'status' => 'active',
        ]);

        $tapis4d = $this->stockedProduct([
            'name' => 'Tapis sur mesure 4D',
            'ref' => 'FAST-TAP4D-211',
            'source' => 'shopify',
        ]);
        $tapis4dVariant = ProductVariant::create([
            'product_id' => $tapis4d->id,
            'title' => 'Default Title',
            'sku' => 'FAST-TAP4D-211',
            'shopify_variant_id' => '46077187096734',
            'position' => 1,
        ]);

        $coffre = $this->stockedProduct([
            'name' => 'Tapis de coffre 4D',
            'ref' => 'FAST-TCOF-250',
            'source' => 'shopify',
        ]);
        ProductVariant::create([
            'product_id' => $coffre->id,
            'title' => 'Default Title',
            'sku' => 'FAST-TCOF-250',
            'shopify_variant_id' => '47866144194718',
            'position' => 1,
        ]);

        $stock = app(StockMovementService::class);
        // Shopify mirror (must be ignored).
        $stock->increase($tapis4d, 3, 'enligne', false, StockMovement::TYPE_PURCHASE, null, null, null, $online->id, null, null, null, $tapis4dVariant->id);
        $stock->increase($coffre, 2, 'enligne', false, StockMovement::TYPE_PURCHASE, null, null, null, $online->id);
        // Belvédère physical stock on Default Title + location (same as Stock par emplacement).
        $stock->adjustPhysicalStock(
            $tapis4d,
            1,
            (int) $belvedere->id,
            (int) $location->id,
            StockMovement::REASON_INVENTORY_CORRECTION,
            null,
            $tapis4dVariant->id
        );

        $client = Client::create(['name' => 'Client FAST']);
        $user = User::factory()->create();
        $order = PosSale::create([
            'ticket_number' => 'FAST12907-TEST',
            'client_id' => $client->id,
            'user_id' => $user->id,
            'sold_at' => now(),
            'created_at' => now(),
            'status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'currency' => 'MAD',
            'subtotal' => 20,
            'total' => 20,
            'payment_method' => 'cash',
            'source' => 'shopify',
        ]);
        // Intentionally no product_variant_id (Shopify import often omits it).
        PosSaleItem::create([
            'pos_sale_id' => $order->id,
            'product_id' => $coffre->id,
            'product_variant_id' => null,
            'ref' => $coffre->ref,
            'designation' => $coffre->name,
            'quantity' => 1,
            'unit_price' => 10,
            'tax_rate' => 20,
            'discount' => 0,
            'line_total' => 10,
        ]);
        PosSaleItem::create([
            'pos_sale_id' => $order->id,
            'product_id' => $tapis4d->id,
            'product_variant_id' => null,
            'ref' => $tapis4d->ref,
            'designation' => $tapis4d->name,
            'quantity' => 1,
            'unit_price' => 10,
            'tax_rate' => 20,
            'discount' => 0,
            'line_total' => 10,
        ]);

        $result = app(OrderPhysicalStockService::class)->prepare($order->fresh());

        $this->assertSame('reservation', $result['mode']);
        $this->assertCount(1, $result['reserved']);
        $this->assertSame($tapis4d->id, $result['reserved'][0]['product_id']);
        $this->assertSame((int) $location->id, (int) $result['reserved'][0]['warehouse_location_id']);
        $this->assertSame($tapis4dVariant->id, $result['reserved'][0]['product_variant_id']);

        $this->assertCount(1, $result['unavailable']);
        $this->assertSame($coffre->id, $result['unavailable'][0]['product_id']);

        $this->assertSame(1, StockReservation::query()->active()->where('source_id', $order->id)->count());
        $this->assertSame(1, StockReplenishmentNeed::query()->open()->where('pos_sale_id', $order->id)->count());
        $this->assertSame(
            $coffre->id,
            (int) StockReplenishmentNeed::query()->open()->where('pos_sale_id', $order->id)->value('product_id')
        );

        // Disponibile Belvédère for tapis 4D is now fully reserved.
        $slot = ProductStock::query()
            ->where('product_id', $tapis4d->id)
            ->where('warehouse_id', $belvedere->id)
            ->where('warehouse_location_id', $location->id)
            ->first();
        $this->assertNotNull($slot);
        $this->assertSame(1, (int) $slot->quantity);
        $this->assertSame(1, (int) $slot->reserved);
        $this->assertSame(0, $slot->available());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function stockedProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Produit X',
            'ref' => 'SKU-X',
            'item_kind' => Product::KIND_STOCKED,
            'stock_quantity' => 0,
            'stock_enligne' => 0,
            'stock_magasin' => 0,
            'cost_price_ht' => 20,
            'vat_category' => 'TVA (20%)',
        ], $overrides));
    }

    private function orderWithProduct(Product $product, int $qty): PosSale
    {
        $client = Client::create(['name' => 'Client']);
        $user = User::factory()->create();
        $order = PosSale::create([
            'ticket_number' => 'CMD-TEST-'.$product->id,
            'client_id' => $client->id,
            'user_id' => $user->id,
            'sold_at' => now(),
            'status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'currency' => 'MAD',
            'subtotal' => 10,
            'total' => 10,
            'payment_method' => 'cash',
            'source' => 'shopify',
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
