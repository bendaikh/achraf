<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesSourceStockExitTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_does_not_exit_physical_stock(): void
    {
        $user = User::factory()->create();
        [$product, $warehouse, $location] = $this->stockedSku(3);

        $this->actingAs($user)->post(route('quotes.store'), [
            'client_id' => Client::create(['name' => 'Client Q'])->id,
            'quote_date' => '2026-10-01',
            'currency' => 'dh - MAD',
            'stock_location' => 'Magasin Belvédère',
            'status' => 'brouillon',
            'items' => [[
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'warehouse_location_id' => $location->id,
                'ref' => $product->ref,
                'designation' => $product->name,
                'quantity' => 1,
                'unit_price' => 100,
                'tax_rate' => 20,
                'discount' => 0,
                'discount_type' => 'fixed',
            ]],
        ])->assertRedirect();

        $this->assertSame(3, $this->qtyAt($product, $warehouse, $location));
        $this->assertSame(0, StockMovement::query()->where('type', StockMovement::TYPE_SALE)->count());
    }

    public function test_validated_delivery_note_exits_stock_once_from_source_location(): void
    {
        $user = User::factory()->create();
        [$product, $warehouse, $location] = $this->stockedSku(3);
        $client = Client::create(['name' => 'Client BL']);

        $this->actingAs($user)->post(route('delivery-notes.store'), [
            'client_id' => $client->id,
            'delivery_date' => '2026-10-01',
            'currency' => 'dh - MAD',
            'stock_location' => 'Magasin Belvédère',
            'status' => 'livré',
            'items' => [[
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'warehouse_location_id' => $location->id,
                'ref' => $product->ref,
                'designation' => $product->name,
                'quantity' => 1,
                'unit_price' => 100,
                'tax_rate' => 20,
                'discount' => 0,
                'discount_type' => 'fixed',
            ]],
        ])->assertRedirect();

        $note = DeliveryNote::query()->firstOrFail();
        $this->assertNotNull($note->stock_applied_at);
        $this->assertSame(2, $this->qtyAt($product, $warehouse, $location));

        $this->actingAs($user)->put(route('delivery-notes.update', $note), [
            'client_id' => $client->id,
            'delivery_date' => '2026-10-01',
            'currency' => 'dh - MAD',
            'stock_location' => 'Magasin Belvédère',
            'status' => 'livré',
            'items' => [[
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'warehouse_location_id' => $location->id,
                'ref' => $product->ref,
                'designation' => $product->name,
                'quantity' => 1,
                'unit_price' => 100,
                'tax_rate' => 20,
                'discount' => 0,
                'discount_type' => 'fixed',
            ]],
        ])->assertRedirect();

        $this->assertSame(2, $this->qtyAt($product, $warehouse, $location));
        $this->assertSame(1, StockMovement::query()->where('type', StockMovement::TYPE_SALE)->count());
    }

    public function test_invoice_from_already_exited_delivery_note_does_not_exit_again(): void
    {
        $user = User::factory()->create();
        [$product, $warehouse, $location] = $this->stockedSku(3);
        $client = Client::create(['name' => 'Client Conv']);

        $note = DeliveryNote::create([
            'delivery_number' => 'BL-SRC-1',
            'client_id' => $client->id,
            'delivery_date' => '2026-10-01',
            'currency' => 'dh - MAD',
            'status' => 'livré',
            'stock_location' => 'Magasin Belvédère',
            'subtotal' => 100,
            'total' => 100,
        ]);
        $note->items()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'warehouse_location_id' => $location->id,
            'ref' => $product->ref,
            'designation' => $product->name,
            'quantity' => 1,
            'unit_price' => 100,
            'tax_rate' => 20,
            'discount' => 0,
            'discount_type' => 'fixed',
            'line_total' => 100,
        ]);

        app(\App\Services\SalesStockIssueService::class)->applyForDeliveryNoteIfNeeded($note->fresh('items'));
        $this->assertSame(2, $this->qtyAt($product, $warehouse, $location));

        $this->actingAs($user)->postJson(route('delivery-notes.bulk-convert'), [
            'ids' => [$note->id],
            'mode' => 'separate',
            'target' => 'invoice',
        ])->assertOk();

        $invoice = Invoice::query()->firstOrFail();
        $this->assertNotNull($invoice->stock_applied_at);
        $this->assertSame(2, $this->qtyAt($product, $warehouse, $location));
        $this->assertSame(1, StockMovement::query()->where('type', StockMovement::TYPE_SALE)->count());
        $this->assertSame($warehouse->id, (int) $invoice->items()->first()->warehouse_id);
        $this->assertSame($location->id, (int) $invoice->items()->first()->warehouse_location_id);
    }

    public function test_direct_invoice_exits_physical_stock_from_source_location(): void
    {
        $user = User::factory()->create();
        [$product, $warehouse, $location] = $this->stockedSku(3);

        $this->actingAs($user)->post(route('invoices.store'), [
            'client_id' => Client::create(['name' => 'Client Fact'])->id,
            'invoice_date' => '2026-10-01',
            'currency' => 'dh - MAD',
            'stock_location' => 'Magasin Belvédère',
            'items' => [[
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'warehouse_location_id' => $location->id,
                'ref' => $product->ref,
                'designation' => $product->name,
                'quantity' => 1,
                'unit_price' => 100,
                'tax_rate' => 20,
                'discount' => 0,
                'discount_type' => 'fixed',
            ]],
        ])->assertRedirect(route('invoices.index'));

        $invoice = Invoice::query()->firstOrFail();
        $this->assertNotNull($invoice->stock_applied_at);
        $this->assertSame(2, $this->qtyAt($product, $warehouse, $location));
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'warehouse_location_id' => $location->id,
            'type' => StockMovement::TYPE_SALE,
            'quantity' => -1,
        ]);
    }

    /**
     * @return array{0: Product, 1: Warehouse, 2: WarehouseLocation}
     */
    private function stockedSku(int $qty): array
    {
        $warehouse = Warehouse::fulfillmentWarehouse();
        $this->assertNotNull($warehouse);

        $location = $warehouse->locations()->first() ?? WarehouseLocation::create([
            'warehouse_id' => $warehouse->id,
            'code' => 'T4D-T01-H01',
            'name' => 'Tapis 4D',
            'status' => 'active',
        ]);

        $product = Product::create([
            'name' => 'Tapis 4D Audi A3',
            'ref' => 'TAP4D-AUDI-A3',
            'item_kind' => Product::KIND_STOCKED,
            'stock_quantity' => 0,
            'stock_magasin' => 0,
        ]);

        app(StockMovementService::class)->declarePhysicalStock(
            $product,
            $qty,
            (int) $warehouse->id,
            (int) $location->id,
            StockMovement::REASON_OTHER,
            'Seed test'
        );

        return [$product->fresh(), $warehouse, $location];
    }

    private function qtyAt(Product $product, Warehouse $warehouse, WarehouseLocation $location): int
    {
        return (int) ProductStock::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('warehouse_location_id', $location->id)
            ->value('quantity');
    }
}
