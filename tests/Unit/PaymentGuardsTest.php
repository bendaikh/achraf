<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\FinancialMovement;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\PaymentRecordingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentGuardsTest extends TestCase
{
    use RefreshDatabase;

    private PaymentRecordingService $recorder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recorder = app(PaymentRecordingService::class);
    }

    public function test_fully_paid_client_invoice_rejects_new_payment(): void
    {
        $invoice = $this->makeClientInvoice(100);
        $this->recorder->recordInvoicePayment($invoice, [
            'payment_date' => '2026-09-18',
            'amount' => 100,
            'payment_method' => 'virement',
        ]);

        $this->expectException(ValidationException::class);
        $this->recorder->recordInvoicePayment($invoice->fresh(), [
            'payment_date' => '2026-09-18',
            'amount' => 10,
            'payment_method' => 'virement',
        ]);
    }

    public function test_duplicate_client_payment_is_rejected(): void
    {
        $invoice = $this->makeClientInvoice(200);
        $payload = [
            'payment_date' => '2026-09-18',
            'amount' => 50,
            'payment_method' => 'espèces',
        ];

        $this->recorder->recordInvoicePayment($invoice, $payload);

        $this->expectException(ValidationException::class);
        $this->recorder->recordInvoicePayment($invoice->fresh(), $payload);
    }

    public function test_future_client_payment_does_not_mark_paid_or_create_cash(): void
    {
        Carbon::setTestNow('2026-09-18');
        $invoice = $this->makeClientInvoice(120);

        $payment = $this->recorder->recordInvoicePayment($invoice, [
            'payment_date' => '2026-10-01',
            'amount' => 120,
            'payment_method' => 'virement',
        ]);

        $invoice->refresh();
        $this->assertTrue($payment->isScheduled());
        $this->assertSame('unpaid', $invoice->payment_status);
        $this->assertEquals(120.0, $invoice->remaining_balance);
        $this->assertDatabaseMissing('financial_movements', [
            'source_type' => InvoicePayment::class,
            'source_id' => $payment->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_fully_paid_supplier_invoice_rejects_new_payment(): void
    {
        $invoice = $this->makeSupplierInvoice(80);
        $this->recorder->recordSupplierPayment($invoice, [
            'payment_date' => '2026-09-18',
            'amount' => 80,
            'payment_method' => 'Virement bancaire',
            'use_credits' => false,
            'use_advances' => false,
        ]);

        $this->expectException(ValidationException::class);
        $this->recorder->recordSupplierPayment($invoice->fresh(), [
            'payment_date' => '2026-09-18',
            'amount' => 10,
            'payment_method' => 'Virement bancaire',
            'use_credits' => false,
            'use_advances' => false,
        ]);
    }

    public function test_future_supplier_payment_does_not_create_cash_movement(): void
    {
        Carbon::setTestNow('2026-09-18');
        $invoice = $this->makeSupplierInvoice(90);

        $payment = $this->recorder->recordSupplierPayment($invoice, [
            'payment_date' => '2026-11-01',
            'amount' => 90,
            'payment_method' => 'Virement bancaire',
            'use_credits' => false,
            'use_advances' => false,
        ]);

        $invoice->refresh();
        $this->assertTrue($payment->isScheduled());
        $this->assertGreaterThan(0.009, $invoice->remaining_balance);
        $this->assertSame(0, FinancialMovement::query()
            ->where('source_type', SupplierInvoicePayment::class)
            ->where('source_id', $payment->id)
            ->count());

        Carbon::setTestNow();
    }

    private function makeClientInvoice(float $total): Invoice
    {
        $client = Client::create(['name' => 'Client Guard']);

        return Invoice::create([
            'invoice_number' => 'FAC-G-'.uniqid(),
            'client_id' => $client->id,
            'invoice_date' => '2026-09-01',
            'currency' => 'dh - MAD',
            'stock_location' => 'magasin',
            'subtotal' => $total,
            'discount' => 0,
            'adjustment' => 0,
            'total' => $total,
            'payment_status' => 'unpaid',
        ]);
    }

    private function makeSupplierInvoice(float $total): SupplierInvoice
    {
        $supplier = Supplier::create(['name' => 'Fournisseur Guard']);

        return SupplierInvoice::create([
            'invoice_number' => 'FF-G-'.uniqid(),
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-09-01',
            'currency' => 'dh - MAD',
            'subtotal' => $total,
            'discount' => 0,
            'total' => $total,
        ]);
    }
}
