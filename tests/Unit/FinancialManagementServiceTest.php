<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\PosSale;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\PurchaseItem;
use App\Services\FinancialManagementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    private FinancialManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FinancialManagementService::class);
    }

    public function test_revenue_does_not_double_count_pos_with_invoice(): void
    {
        $client = Client::create(['name' => 'Client A']);

        $sale = PosSale::create([
            'ticket_number' => 'POS-001',
            'client_id' => $client->id,
            'sold_at' => Carbon::parse('2026-08-01 10:00:00'),
            'currency' => 'dh - MAD',
            'subtotal' => 100,
            'discount' => 0,
            'tax_total' => 20,
            'total' => 120,
            'payment_method' => 'cash',
            'status' => PosSale::STATUS_COMPLETED,
        ]);

        Invoice::create([
            'invoice_number' => 'FAC-001',
            'client_id' => $client->id,
            'pos_sale_id' => $sale->id,
            'is_auto_generated' => true,
            'invoice_date' => '2026-08-01',
            'currency' => 'dh - MAD',
            'stock_location' => 'magasin',
            'subtotal' => 120,
            'discount' => 0,
            'adjustment' => 0,
            'total' => 120,
            'payment_status' => Invoice::PAYMENT_PAID,
        ]);

        PosSale::create([
            'ticket_number' => 'POS-002',
            'sold_at' => Carbon::parse('2026-08-02 10:00:00'),
            'currency' => 'dh - MAD',
            'subtotal' => 50,
            'discount' => 0,
            'tax_total' => 10,
            'total' => 60,
            'payment_method' => 'cash',
            'status' => PosSale::STATUS_COMPLETED,
        ]);

        $from = Carbon::parse('2026-08-01')->startOfDay();
        $to = Carbon::parse('2026-08-31')->endOfDay();
        $sales = $this->service->getSalesBreakdown($from, $to);

        $this->assertEquals(120.0, $sales['invoices']);
        $this->assertEquals(60.0, $sales['pos']);
        $this->assertEquals(180.0, $sales['revenue']);
    }

    public function test_vat_collected_and_deductible_and_net(): void
    {
        $client = Client::create(['name' => 'Client B']);
        $supplier = Supplier::create(['name' => 'Fournisseur B', 'code' => 'F-B']);

        $invoice = Invoice::create([
            'invoice_number' => 'FAC-VAT-1',
            'client_id' => $client->id,
            'invoice_date' => '2026-08-05',
            'currency' => 'dh - MAD',
            'stock_location' => 'magasin',
            'subtotal' => 120,
            'discount' => 0,
            'adjustment' => 0,
            'total' => 120,
            'payment_status' => Invoice::PAYMENT_UNPAID,
        ]);

        InvoiceItem::create([
            'itemable_type' => Invoice::class,
            'itemable_id' => $invoice->id,
            'designation' => 'Produit',
            'quantity' => 1,
            'unit_price' => 100,
            'tax_rate' => 20,
            'discount' => 0,
            'discount_type' => 'fixed',
            'line_total' => 120,
        ]);

        $purchase = SupplierInvoice::create([
            'invoice_number' => 'FF-VAT-1',
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-08-06',
            'currency' => 'dh - MAD',
            'stock_location' => 'magasin',
            'subtotal' => 60,
            'discount' => 0,
            'adjustment' => 0,
            'total' => 60,
        ]);

        PurchaseItem::create([
            'purchaseable_type' => SupplierInvoice::class,
            'purchaseable_id' => $purchase->id,
            'designation' => 'Achat',
            'quantity' => 1,
            'unit_price' => 60,
            'tax_rate' => 20,
            'discount' => 0,
            'discount_type' => 'fixed',
            'line_total' => 60,
        ]);

        Expense::create([
            'designation' => 'Frais',
            'expense_type' => 'without_invoice',
            'expense_date' => '2026-08-07',
            'amount' => 120,
            'tax_type' => 'TVA 20%',
            'account' => 'Caisse',
        ]);

        $from = Carbon::parse('2026-08-01')->startOfDay();
        $to = Carbon::parse('2026-08-31')->endOfDay();
        $vat = $this->service->getVatBreakdown($from, $to);

        $this->assertEquals(20.0, $vat['collected']);
        $this->assertEquals(10.0, $vat['deductible_purchases']);
        $this->assertEquals(20.0, $vat['deductible_expenses']);
        $this->assertEquals(30.0, $vat['deductible']);
        $this->assertEquals(-10.0, $vat['net']);
    }

    public function test_estimated_result_uses_purchases_and_expenses(): void
    {
        $client = Client::create(['name' => 'Client C']);
        $supplier = Supplier::create(['name' => 'Fournisseur C', 'code' => 'F-C']);

        Invoice::create([
            'invoice_number' => 'FAC-R-1',
            'client_id' => $client->id,
            'invoice_date' => '2026-08-10',
            'currency' => 'dh - MAD',
            'stock_location' => 'magasin',
            'subtotal' => 1000,
            'discount' => 0,
            'adjustment' => 0,
            'total' => 1000,
            'payment_status' => Invoice::PAYMENT_UNPAID,
        ]);

        SupplierInvoice::create([
            'invoice_number' => 'FF-R-1',
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-08-11',
            'currency' => 'dh - MAD',
            'stock_location' => 'magasin',
            'subtotal' => 300,
            'discount' => 0,
            'adjustment' => 0,
            'total' => 300,
        ]);

        Expense::create([
            'designation' => 'Loyer',
            'expense_type' => 'without_invoice',
            'expense_date' => '2026-08-12',
            'amount' => 200,
            'tax_type' => 'NO TAXE',
            'account' => 'Banque',
        ]);

        $overview = $this->service->getOverview(
            Carbon::parse('2026-08-01')->startOfDay(),
            Carbon::parse('2026-08-31')->endOfDay()
        );

        $this->assertEquals(1000.0, $overview['revenue']);
        $this->assertEquals(300.0, $overview['purchases']);
        $this->assertEquals(200.0, $overview['expenses']);
        $this->assertEquals(500.0, $overview['estimated_result']);
    }

    public function test_treasury_splits_caisse_and_banque(): void
    {
        $client = Client::create(['name' => 'Client D']);

        PosSale::create([
            'ticket_number' => 'POS-CASH',
            'sold_at' => now(),
            'currency' => 'dh - MAD',
            'subtotal' => 100,
            'discount' => 0,
            'tax_total' => 0,
            'total' => 100,
            'payment_method' => 'cash',
            'status' => PosSale::STATUS_COMPLETED,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'FAC-BANK',
            'client_id' => $client->id,
            'invoice_date' => now()->toDateString(),
            'currency' => 'dh - MAD',
            'stock_location' => 'magasin',
            'subtotal' => 200,
            'discount' => 0,
            'adjustment' => 0,
            'total' => 200,
            'payment_status' => Invoice::PAYMENT_PAID,
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_date' => now()->toDateString(),
            'amount' => 200,
            'payment_method' => 'Virement bancaire',
        ]);

        Expense::create([
            'designation' => 'Fournitures',
            'expense_type' => 'without_invoice',
            'expense_date' => now()->toDateString(),
            'amount' => 30,
            'tax_type' => 'NO TAXE',
            'account' => 'Caisse',
        ]);

        $treasury = $this->service->getTreasuryBalances();

        $this->assertEquals(70.0, $treasury['caisse']);
        $this->assertEquals(200.0, $treasury['banque']);
        $this->assertEquals(270.0, $treasury['total']);
    }

    public function test_expense_deductible_vat_from_ttc_amount(): void
    {
        $expense = new Expense([
            'amount' => 120,
            'tax_type' => 'TVA 20%',
        ]);

        $this->assertEquals(20.0, $this->service->expenseDeductibleVat($expense));

        $noTax = new Expense([
            'amount' => 120,
            'tax_type' => 'NO TAXE',
        ]);

        $this->assertEquals(0.0, $this->service->expenseDeductibleVat($noTax));
    }
}
