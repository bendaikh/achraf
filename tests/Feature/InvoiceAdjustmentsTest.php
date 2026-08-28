<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\DocumentTaxBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceAdjustmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_invoice_adjustments_change_total_not_payment(): void
    {
        $user = User::factory()->create();
        $client = Client::create(['name' => 'Client FSI']);
        $product = Product::create([
            'name' => 'Huile',
            'ref' => 'HUILE-1',
            'item_kind' => Product::KIND_STOCKED,
            'stock_quantity' => 10,
            'stock_magasin' => 10,
            'stock_enligne' => 10,
        ]);

        $this->actingAs($user)->post(route('invoices.store'), [
            'client_id' => $client->id,
            'invoice_date' => '2026-08-20',
            'currency' => 'dh - MAD',
            'stock_location' => 'DEPOT',
            'items' => [[
                'product_id' => $product->id,
                'ref' => $product->ref,
                'designation' => $product->name,
                'quantity' => 1,
                'unit_price' => 990,
                'tax_rate' => 20,
                'discount' => 0,
                'discount_type' => 'fixed',
            ]],
            'adjustments' => [
                ['label' => 'Droit de timbre', 'type' => 'add', 'amount' => 16, 'is_taxable' => '0', 'tax_rate' => 0],
                ['label' => 'Paiement à la livraison', 'type' => 'add', 'amount' => 1, 'is_taxable' => '0', 'tax_rate' => 0],
                ['label' => 'Arrondi', 'type' => 'deduct', 'amount' => 0.5, 'is_taxable' => '0', 'tax_rate' => 0],
            ],
        ])->assertRedirect(route('invoices.index'));

        $invoice = Invoice::query()->with(['items', 'adjustments', 'payments'])->first();
        $this->assertNotNull($invoice);
        $this->assertCount(3, $invoice->adjustments);
        $this->assertEquals(1204.5, (float) $invoice->total);
        $this->assertEquals(1204.5, $invoice->computed_total);
        $this->assertEquals(16.5, (float) $invoice->adjustment);
        $this->assertEquals(1204.5, $invoice->remaining_balance);

        $this->actingAs($user)->post(route('invoices.payments.store', $invoice), [
            'payment_date' => '2026-08-20',
            'amount' => 1204.50,
            'payment_method' => 'Espèces',
        ])->assertRedirect(route('invoices.payments.index', $invoice));

        $invoice->refresh();
        $this->assertEquals(1204.5, (float) $invoice->total);
        $this->assertEquals(0.0, $invoice->remaining_balance);
        $this->assertSame(Invoice::PAYMENT_PAID, $invoice->computed_payment_status);
        $this->assertCount(1, $invoice->payments);
        $this->assertEquals(1204.5, (float) $invoice->payments->first()->amount);
    }

    public function test_supplier_invoice_adjustments_are_included_in_remaining_balance(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create(['name' => 'FSI MAROC']);
        $warehouse = Warehouse::fulfillmentWarehouse() ?? Warehouse::query()->first();
        $this->assertNotNull($warehouse);

        $this->actingAs($user)->post(route('supplier-invoices.store'), [
            'invoice_number' => 'FSI-2026-00125',
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-08-20',
            'currency' => 'dh - MAD',
            'warehouse_id' => $warehouse->id,
            'items' => [[
                'designation' => 'Huile',
                'quantity' => 1,
                'unit_price' => 1188,
                'tax_rate' => 20,
                'discount' => 0,
                'discount_type' => 'fixed',
            ]],
            'adjustments' => [
                ['label' => 'Droit de timbre', 'type' => 'add', 'amount' => 16, 'is_taxable' => '0'],
                ['label' => 'Paiement à la livraison', 'type' => 'add', 'amount' => 1, 'is_taxable' => '0'],
                ['label' => 'Arrondi', 'type' => 'deduct', 'amount' => 0.5, 'is_taxable' => '0'],
            ],
        ])->assertRedirect(route('supplier-invoices.index'));

        $invoice = SupplierInvoice::query()->with('adjustments')->first();
        $this->assertNotNull($invoice);
        $this->assertCount(3, $invoice->adjustments);
        $this->assertEquals(1204.5, (float) $invoice->total);
        $this->assertEquals(1204.5, $invoice->remaining_balance);

        $invoice->payments()->create([
            'amount' => 1204.50,
            'payment_date' => '2026-08-20',
            'payment_method' => 'Espèces',
        ]);

        $invoice->refresh();
        $this->assertEquals(0.0, $invoice->remaining_balance);
        $this->assertSame('paid', $invoice->computed_payment_status);
    }

    public function test_taxable_adjustment_adds_vat_to_invoice_total(): void
    {
        $taxes = DocumentTaxBreakdown::fromItems(
            [[
                'quantity' => 1,
                'unit_price' => 100,
                'tax_rate' => 20,
                'discount' => 0,
                'discount_type' => 'fixed',
            ]],
            0,
            0,
            'sale',
            [[
                'label' => 'Frais port',
                'type' => 'add',
                'amount' => 10,
                'is_taxable' => true,
                'tax_rate' => 20,
            ]],
        );

        $this->assertEquals(120.0, $taxes['items_ttc']);
        $this->assertEquals(12.0, $taxes['adjustments_positive']);
        $this->assertEquals(132.0, $taxes['total_ttc']);
        $this->assertEquals(2.0, $taxes['adjustment_tax_total']);
    }
}
