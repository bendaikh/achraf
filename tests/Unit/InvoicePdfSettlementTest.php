<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Support\CommercialDocumentView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePdfSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_unpaid_invoice_settlement_comes_from_real_payments(): void
    {
        $invoice = $this->makeInvoice(1200);

        $view = CommercialDocumentView::forInvoice($invoice, ['total_ttc' => 1200]);
        $settlement = $view['doc']['settlement'];

        $this->assertSame('unpaid', $settlement['status']);
        $this->assertSame('NON PAYÉE', $settlement['status_label']);
        $this->assertSame(0.0, $settlement['total_paid']);
        $this->assertSame(1200.0, $settlement['remaining']);
        $this->assertSame([], $settlement['payments']);
    }

    public function test_single_payment_settlement_uses_gestion_paiement_rows(): void
    {
        $invoice = $this->makeInvoice(1200);
        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_date' => '2026-10-01',
            'amount' => 1200,
            'payment_method' => 'Carte bancaire',
            'payment_reference' => 'XXXX',
            'source' => InvoicePayment::SOURCE_MANUAL,
        ]);
        $invoice->update(['payment_status' => Invoice::PAYMENT_UNPAID]);

        $view = CommercialDocumentView::forInvoice($invoice->fresh(['client', 'items', 'payments']), [
            'total_ttc' => 1200,
        ]);
        $settlement = $view['doc']['settlement'];

        $this->assertSame('paid', $settlement['status']);
        $this->assertSame('PAYÉE', $settlement['status_label']);
        $this->assertSame(1200.0, $settlement['total_paid']);
        $this->assertSame(0.0, $settlement['remaining']);
        $this->assertCount(1, $settlement['payments']);
        $this->assertSame('Carte bancaire', $settlement['payments'][0]['method']);
        $this->assertSame('01/10/2026', $settlement['payments'][0]['date']);
        $this->assertSame('XXXX', $settlement['payments'][0]['reference']);
    }

    public function test_multiple_payments_are_listed_separately_with_remaining_balance(): void
    {
        $invoice = $this->makeInvoice(1200);
        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_date' => '2026-09-15',
            'amount' => 500,
            'payment_method' => 'Espèces',
            'payment_reference' => 'ESP-1',
            'source' => InvoicePayment::SOURCE_MANUAL,
        ]);
        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_date' => '2026-10-01',
            'amount' => 300,
            'payment_method' => 'Virement bancaire',
            'payment_reference' => null,
            'source' => InvoicePayment::SOURCE_MANUAL,
        ]);

        $view = CommercialDocumentView::forInvoice($invoice->fresh(['client', 'items', 'payments']), [
            'total_ttc' => 1200,
        ]);
        $settlement = $view['doc']['settlement'];

        $this->assertSame('partial', $settlement['status']);
        $this->assertSame('PARTIELLEMENT PAYÉE', $settlement['status_label']);
        $this->assertSame(800.0, $settlement['total_paid']);
        $this->assertSame(400.0, $settlement['remaining']);
        $this->assertCount(2, $settlement['payments']);
        $this->assertSame(500.0, $settlement['payments'][0]['amount']);
        $this->assertSame(300.0, $settlement['payments'][1]['amount']);
        $this->assertNull($settlement['payments'][1]['reference']);
    }

    private function makeInvoice(float $total): Invoice
    {
        $client = Client::create(['name' => 'Client PDF']);

        $invoice = Invoice::create([
            'invoice_number' => 'FAC-PDF-'.uniqid(),
            'client_id' => $client->id,
            'invoice_date' => '2026-10-01',
            'currency' => 'dh - MAD',
            'stock_location' => 'magasin',
            'subtotal' => $total,
            'discount' => 0,
            'adjustment' => 0,
            'total' => $total,
            'payment_status' => Invoice::PAYMENT_UNPAID,
        ]);

        $invoice->items()->create([
            'designation' => 'Article',
            'quantity' => 1,
            'unit_price' => $total,
            'tax_rate' => 0,
            'discount' => 0,
            'discount_type' => 'fixed',
            'line_total' => $total,
        ]);

        return $invoice->fresh(['client', 'items', 'payments']);
    }
}
