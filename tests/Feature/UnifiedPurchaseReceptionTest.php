<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Reception;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierDeliveryNote;
use App\Models\SupplierInvoice;
use App\Models\SupplierPurchaseOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchaseReceiptService;
use App\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedPurchaseReceptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_linked_chain_shows_shared_reception_progress_without_double_stock(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Fournisseur Chain']);
        $product = $this->stockedProduct();
        $warehouse = Warehouse::fulfillmentWarehouse();

        $order = SupplierPurchaseOrder::create([
            'order_number' => 'BC-CHAIN-1',
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

        $note = SupplierDeliveryNote::create([
            'delivery_number' => 'BL-CHAIN-1',
            'supplier_id' => $supplier->id,
            'supplier_purchase_order_id' => $order->id,
            'delivery_date' => now()->toDateString(),
            'currency' => 'dh - MAD',
            'status' => 'accepté',
            'stock_location' => $warehouse->name,
            'warehouse_id' => $warehouse->id,
            'subtotal' => 100,
            'total' => 100,
        ]);
        $note->items()->create([
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

        $this->actingAs($user)->post(route('receptions.store'), [
            'reception_number' => 'BR-CHAIN-1',
            'supplier_id' => $supplier->id,
            'supplier_purchase_order_id' => $order->id,
            'supplier_delivery_note_id' => $note->id,
            'reception_date' => '2026-08-28',
            'currency' => 'dh - MAD',
            'status' => 'accepté',
            'warehouse_id' => $warehouse->id,
            'items' => [[
                'product_id' => $product->id,
                'ref' => $product->ref,
                'designation' => $product->name,
                'quantity' => 10,
                'unit_price' => 10,
                'tax_rate' => 20,
                'discount' => 0,
                'discount_type' => 'fixed',
            ]],
        ])->assertRedirect();

        $this->assertSame(10, app(StockMovementService::class)->quantityAtWarehouse($product->fresh(), (int) $warehouse->id));
        $this->assertSame(1, StockMovement::query()->where('type', StockMovement::TYPE_PURCHASE)->count());

        $receipts = app(PurchaseReceiptService::class);
        $orderProgress = $receipts->progressForDocument($order->fresh())->first();
        $noteProgress = $receipts->progressForDocument($note->fresh())->first();

        $this->assertSame(10, $orderProgress['received']);
        $this->assertSame(0, $orderProgress['remaining']);
        $this->assertSame(10, $noteProgress['received']);
        $this->assertSame(0, $noteProgress['remaining']);
        $this->assertSame(PurchaseReceiptService::STATUS_COMPLETE, $receipts->documentReceptionStatus($order));
    }

    public function test_direct_invoice_adds_stock_once_and_blocks_receive_stock(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'Fournisseur Facture']);
        $product = $this->stockedProduct(['name' => 'Produit A', 'ref' => 'A-1']);
        $warehouse = Warehouse::fulfillmentWarehouse();

        $this->actingAs($user)->post(route('supplier-invoices.store'), [
            'invoice_number' => 'FSI-UNIFIED-1',
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-08-28',
            'currency' => 'dh - MAD',
            'warehouse_id' => $warehouse->id,
            'items' => [[
                'product_id' => $product->id,
                'ref' => $product->ref,
                'designation' => $product->name,
                'quantity' => 10,
                'unit_price' => 10,
                'tax_rate' => 20,
                'discount' => 0,
                'discount_type' => 'fixed',
            ]],
        ])->assertRedirect();

        $invoice = SupplierInvoice::query()->firstOrFail();

        // Facture directe : entrée en stock unique à la création (pas besoin de BR).
        $this->assertNotNull($invoice->stock_applied_at);
        $this->assertSame(10, app(StockMovementService::class)->quantityAtWarehouse($product->fresh(), (int) $warehouse->id));
        $this->assertSame(1, StockMovement::query()->where('type', StockMovement::TYPE_PURCHASE)->count());

        $this->actingAs($user)->post(route('supplier-invoices.receive-stock', $invoice), [
            'warehouse_id' => $warehouse->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10],
            ],
        ])->assertSessionHas('error');

        $this->assertSame(0, Reception::query()->count());
        $this->assertSame(10, app(StockMovementService::class)->quantityAtWarehouse($product->fresh(), (int) $warehouse->id));
        $this->assertSame(1, StockMovement::query()->where('type', StockMovement::TYPE_PURCHASE)->count());
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
