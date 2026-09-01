<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Reception;
use App\Models\StockMovement;
use App\Models\StockReplenishmentNeed;
use App\Models\Supplier;
use App\Models\SupplierPurchaseOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\LocationStockReportService;
use App\Services\OrderPhysicalStockService;
use App\Services\StockMovementService;
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

    public function test_delivery_note_does_not_add_stock(): void
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
            ]],
        ])->assertRedirect(route('supplier-delivery-notes.index'));

        $this->assertSame(0, app(StockMovementService::class)->quantityAtWarehouse($product->fresh(), (int) $warehouse->id));
        $this->assertSame(0, StockMovement::query()->where('type', StockMovement::TYPE_PURCHASE)->count());

        $note = \App\Models\SupplierDeliveryNote::query()->firstOrFail();
        $this->actingAs($user)->postJson(route('supplier-delivery-notes.bulk-convert'), [
            'ids' => [$note->id],
            'mode' => 'separate',
        ])->assertOk();

        // Conversion ne crée toujours pas de stock
        $this->assertSame(0, app(StockMovementService::class)->quantityAtWarehouse($product->fresh(), (int) $warehouse->id));
        $this->assertSame(0, StockMovement::query()->where('type', StockMovement::TYPE_PURCHASE)->count());
    }

    public function test_direct_supplier_invoice_stock_only_after_receive_action(): void
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
            'warehouse_id' => $online->id,
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
                ],
            ],
        ])->assertRedirect();

        $invoice = \App\Models\SupplierInvoice::query()->firstOrFail();
        $this->assertNull($invoice->stock_applied_at);
        $this->assertSame(0, app(StockMovementService::class)->quantityAtWarehouse($productA->fresh(), (int) $online->id));

        $this->actingAs($user)->post(route('supplier-invoices.receive-stock', $invoice), [
            'warehouse_id' => $online->id,
            'items' => [
                [
                    'product_id' => $productA->id,
                    'quantity' => 10,
                    'allocations' => [
                        ['warehouse_id' => $online->id, 'quantity' => 7],
                        ['warehouse_id' => $store->id, 'quantity' => 3],
                    ],
                ],
                [
                    'product_id' => $productB->id,
                    'quantity' => 10,
                    'allocations' => [
                        ['warehouse_id' => $online->id, 'quantity' => 10],
                    ],
                ],
            ],
        ])->assertRedirect(route('supplier-invoices.show', $invoice));

        $this->assertNotNull($invoice->fresh()->stock_applied_at);
        $this->assertSame(7, app(StockMovementService::class)->quantityAtWarehouse($productA->fresh(), (int) $online->id));
        $this->assertSame(3, app(StockMovementService::class)->quantityAtWarehouse($productA->fresh(), (int) $store->id));
        $this->assertSame(10, app(StockMovementService::class)->quantityAtWarehouse($productB->fresh(), (int) $online->id));
        $this->assertSame(0, app(StockMovementService::class)->quantityAtWarehouse($productB->fresh(), (int) $store->id));

        // Seconde tentative bloquée
        $this->actingAs($user)->post(route('supplier-invoices.receive-stock', $invoice), [
            'warehouse_id' => $online->id,
            'items' => [
                ['product_id' => $productA->id, 'quantity' => 10],
            ],
        ])->assertRedirect();
        $this->assertSame(7, app(StockMovementService::class)->quantityAtWarehouse($productA->fresh(), (int) $online->id));
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
