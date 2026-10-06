<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierDeliveryNote;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\StockMovementService;
use App\Services\SupplierInvoiceLineWarehouseBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierInvoiceConvertedWarehouseTest extends TestCase
{
    use RefreshDatabase;

    public function test_bl_conversion_copies_line_warehouse_location_and_does_not_duplicate_stock(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Fournisseur Multi Dépôts']);
        $productA = $this->stockedProduct(['name' => 'Produit A', 'ref' => 'PA-1']);
        $productB = $this->stockedProduct(['name' => 'Produit B', 'ref' => 'PB-1']);

        $warehouseA = Warehouse::fulfillmentWarehouse();
        $warehouseB = Warehouse::onlineWarehouse() ?? Warehouse::create([
            'name' => 'Dépôt B',
            'code' => 'DEP-B',
            'kind' => 'physical',
            'status' => 'active',
        ]);
        $this->assertNotNull($warehouseA);
        $this->assertNotNull($warehouseB);

        $locationA = WarehouseLocation::query()->firstOrCreate(
            ['warehouse_id' => $warehouseA->id, 'code' => 'A-01'],
            ['name' => 'Zone A', 'status' => 'active']
        );
        $locationB = WarehouseLocation::query()->firstOrCreate(
            ['warehouse_id' => $warehouseB->id, 'code' => 'B-01'],
            ['name' => 'Zone B', 'status' => 'active']
        );

        $this->actingAs($user)->post(route('supplier-delivery-notes.store'), [
            'delivery_number' => 'BLF-WH-A',
            'supplier_id' => $supplier->id,
            'delivery_date' => '2026-10-01',
            'currency' => 'dh - MAD',
            'status' => 'accepté',
            'warehouse_id' => $warehouseA->id,
            'items' => [[
                'product_id' => $productA->id,
                'ref' => $productA->ref,
                'designation' => $productA->name,
                'quantity' => 5,
                'unit_price' => 10,
                'tax_rate' => 20,
                'discount' => 0,
                'discount_type' => 'fixed',
                'warehouse_id' => $warehouseA->id,
                'warehouse_location_id' => $locationA->id,
            ]],
        ])->assertRedirect();

        $this->actingAs($user)->post(route('supplier-delivery-notes.store'), [
            'delivery_number' => 'BLF-WH-B',
            'supplier_id' => $supplier->id,
            'delivery_date' => '2026-10-02',
            'currency' => 'dh - MAD',
            'status' => 'accepté',
            'warehouse_id' => $warehouseB->id,
            'items' => [[
                'product_id' => $productB->id,
                'ref' => $productB->ref,
                'designation' => $productB->name,
                'quantity' => 3,
                'unit_price' => 20,
                'tax_rate' => 20,
                'discount' => 0,
                'discount_type' => 'fixed',
                'warehouse_id' => $warehouseB->id,
                'warehouse_location_id' => $locationB->id,
            ]],
        ])->assertRedirect();

        $notes = SupplierDeliveryNote::query()->orderBy('id')->get();
        $this->assertCount(2, $notes);
        $this->assertTrue($notes->every(fn ($n) => $n->stock_applied_at !== null));

        $stockBefore = StockMovement::query()->where('type', StockMovement::TYPE_PURCHASE)->count();
        $qtyA = app(StockMovementService::class)->quantityAtSlot($productA->fresh(), (int) $warehouseA->id, (int) $locationA->id);
        $qtyB = app(StockMovementService::class)->quantityAtSlot($productB->fresh(), (int) $warehouseB->id, (int) $locationB->id);
        $this->assertSame(5, $qtyA);
        $this->assertSame(3, $qtyB);

        $this->actingAs($user)->postJson(route('supplier-delivery-notes.bulk-convert'), [
            'ids' => $notes->pluck('id')->all(),
            'mode' => 'combined',
        ])->assertOk();

        $invoice = SupplierInvoice::query()->with(['items', 'stockAllocations'])->firstOrFail();
        $this->assertNotNull($invoice->stock_applied_at);
        $this->assertSame(
            $stockBefore,
            StockMovement::query()->where('type', StockMovement::TYPE_PURCHASE)->count()
        );
        $this->assertSame(5, app(StockMovementService::class)->quantityAtSlot($productA->fresh(), (int) $warehouseA->id, (int) $locationA->id));
        $this->assertSame(3, app(StockMovementService::class)->quantityAtSlot($productB->fresh(), (int) $warehouseB->id, (int) $locationB->id));

        $lineA = $invoice->items->firstWhere('product_id', $productA->id);
        $lineB = $invoice->items->firstWhere('product_id', $productB->id);
        $this->assertNotNull($lineA);
        $this->assertNotNull($lineB);
        $this->assertSame((int) $warehouseA->id, (int) $lineA->warehouse_id);
        $this->assertSame((int) $locationA->id, (int) $lineA->warehouse_location_id);
        $this->assertSame((int) $warehouseB->id, (int) $lineB->warehouse_id);
        $this->assertSame((int) $locationB->id, (int) $lineB->warehouse_location_id);
        $this->assertGreaterThanOrEqual(2, $invoice->stockAllocations->count());

        // Modification sans ressaisir : conserver les dépôts/emplacements d’origine.
        $this->actingAs($user)->put(route('supplier-invoices.update', $invoice), [
            'invoice_number' => $invoice->invoice_number,
            'supplier_id' => $supplier->id,
            'invoice_date' => $invoice->invoice_date->format('Y-m-d'),
            'currency' => $invoice->currency,
            'warehouse_id' => $warehouseA->id,
            'items' => [
                [
                    'product_id' => $productA->id,
                    'ref' => $productA->ref,
                    'designation' => $productA->name,
                    'quantity' => 5,
                    'unit_price' => 10,
                    'tax_rate' => 20,
                    'discount' => 0,
                    'discount_type' => 'fixed',
                    'source_document_reference' => $lineA->source_document_reference,
                    'warehouse_id' => $lineA->warehouse_id,
                    'warehouse_location_id' => $lineA->warehouse_location_id,
                ],
                [
                    'product_id' => $productB->id,
                    'ref' => $productB->ref,
                    'designation' => $productB->name,
                    'quantity' => 3,
                    'unit_price' => 20,
                    'tax_rate' => 20,
                    'discount' => 0,
                    'discount_type' => 'fixed',
                    'source_document_reference' => $lineB->source_document_reference,
                    'warehouse_id' => $lineB->warehouse_id,
                    'warehouse_location_id' => $lineB->warehouse_location_id,
                ],
            ],
        ])->assertRedirect();

        $invoice->refresh()->load('items');
        $lineA = $invoice->items->firstWhere('product_id', $productA->id);
        $lineB = $invoice->items->firstWhere('product_id', $productB->id);
        $this->assertSame((int) $warehouseA->id, (int) $lineA->warehouse_id);
        $this->assertSame((int) $locationA->id, (int) $lineA->warehouse_location_id);
        $this->assertSame((int) $warehouseB->id, (int) $lineB->warehouse_id);
        $this->assertSame((int) $locationB->id, (int) $lineB->warehouse_location_id);
        $this->assertSame(
            $stockBefore,
            StockMovement::query()->where('type', StockMovement::TYPE_PURCHASE)->count()
        );
    }

    public function test_backfill_command_repairs_legacy_converted_invoice_lines_idempotently(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Fournisseur Legacy']);
        $product = $this->stockedProduct(['name' => 'Produit Legacy', 'ref' => 'LEG-1']);
        $warehouse = Warehouse::fulfillmentWarehouse();
        $location = WarehouseLocation::query()->firstOrCreate(
            ['warehouse_id' => $warehouse->id, 'code' => 'LEG-01'],
            ['name' => 'Legacy Loc', 'status' => 'active']
        );

        $this->actingAs($user)->post(route('supplier-delivery-notes.store'), [
            'delivery_number' => 'BLF-LEGACY',
            'supplier_id' => $supplier->id,
            'delivery_date' => '2026-10-03',
            'currency' => 'dh - MAD',
            'status' => 'accepté',
            'warehouse_id' => $warehouse->id,
            'items' => [[
                'product_id' => $product->id,
                'ref' => $product->ref,
                'designation' => $product->name,
                'quantity' => 4,
                'unit_price' => 15,
                'tax_rate' => 20,
                'discount' => 0,
                'discount_type' => 'fixed',
                'warehouse_id' => $warehouse->id,
                'warehouse_location_id' => $location->id,
            ]],
        ])->assertRedirect();

        $note = SupplierDeliveryNote::query()->firstOrFail();
        $this->actingAs($user)->postJson(route('supplier-delivery-notes.bulk-convert'), [
            'ids' => [$note->id],
            'mode' => 'separate',
        ])->assertOk();

        $invoice = SupplierInvoice::query()->with('items')->firstOrFail();
        // Simule une ancienne facture convertie sans dépôt/emplacement sur les lignes.
        foreach ($invoice->items as $item) {
            $item->update(['warehouse_id' => null, 'warehouse_location_id' => null]);
        }
        $invoice->stockAllocations()->delete();

        $service = app(SupplierInvoiceLineWarehouseBackfillService::class);
        $dry = $service->backfill(true, $invoice->id);
        $this->assertSame(1, $dry['updated_lines']);
        $this->assertNull($invoice->fresh()->items->first()->warehouse_id);

        $applied = $service->backfill(false, $invoice->id);
        $this->assertSame(1, $applied['updated_lines']);
        $line = $invoice->fresh()->items->first();
        $this->assertSame((int) $warehouse->id, (int) $line->warehouse_id);
        $this->assertSame((int) $location->id, (int) $line->warehouse_location_id);

        $again = $service->backfill(false, $invoice->id);
        $this->assertSame(0, $again['updated_lines']);

        $this->artisan('supplier-invoices:repair-converted-warehouses', [
            '--invoice' => $invoice->invoice_number,
        ])->assertSuccessful();
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
}
