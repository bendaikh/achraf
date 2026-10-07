<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\FinancialMovement;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PosSale;
use App\Models\User;
use App\Services\ClientCardPaymentAllocationRepairService;
use App\Services\PaymentRecordingService;
use App\Support\CommercialDocumentView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceCardPaymentAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_card_payment_marks_invoice_paid_and_removes_from_unpaid(): void
    {
        $user = User::factory()->create();
        $invoice = $this->makeInvoice(350);

        app(PaymentRecordingService::class)->recordInvoicePayment($invoice, [
            'payment_date' => now()->toDateString(),
            'amount' => 350,
            'payment_method' => 'Carte bancaire',
            'payment_reference' => 'CB-350',
            'source' => InvoicePayment::SOURCE_MANUAL,
        ]);

        $invoice->refresh()->load('payments');

        $this->assertEquals(350.0, $invoice->total_paid);
        $this->assertEquals(0.0, $invoice->remaining_balance);
        $this->assertSame(Invoice::PAYMENT_PAID, $invoice->payment_status);
        $this->assertSame(Invoice::PAYMENT_PAID, $invoice->computed_payment_status);
        $this->assertCount(1, $invoice->payments);
        $this->assertSame('Carte bancaire', $invoice->payments->first()->payment_method);

        $this->actingAs($user)
            ->get(route('sales-finance.unpaid'))
            ->assertOk()
            ->assertDontSee($invoice->invoice_number, false);

        $this->actingAs($user)
            ->get(route('sales.payments.index', ['payment_status' => 'unpaid']))
            ->assertOk()
            ->assertDontSee($invoice->invoice_number, false);

        $settlement = CommercialDocumentView::forInvoice($invoice->fresh(['client', 'items', 'payments']), [
            'total_ttc' => 350,
        ])['doc']['settlement'];

        $this->assertSame('PAYÉE', $settlement['status_label']);
        $this->assertSame(350.0, $settlement['total_paid']);
        $this->assertSame(0.0, $settlement['remaining']);
        $this->assertSame('Carte bancaire', $settlement['payments'][0]['method']);
    }

    public function test_partial_card_payment_keeps_invoice_partial_with_remaining(): void
    {
        $user = User::factory()->create();
        $invoice = $this->makeInvoice(350);

        app(PaymentRecordingService::class)->recordInvoicePayment($invoice, [
            'payment_date' => now()->toDateString(),
            'amount' => 200,
            'payment_method' => 'Carte bancaire',
            'payment_reference' => 'CB-PARTIAL',
            'source' => InvoicePayment::SOURCE_MANUAL,
        ]);

        $invoice->refresh()->load('payments');

        $this->assertEquals(200.0, $invoice->total_paid);
        $this->assertEquals(150.0, $invoice->remaining_balance);
        $this->assertSame(Invoice::PAYMENT_PARTIAL, $invoice->payment_status);
        $this->assertSame(Invoice::PAYMENT_PARTIAL, $invoice->computed_payment_status);

        $this->actingAs($user)
            ->get(route('sales-finance.unpaid'))
            ->assertOk()
            ->assertSee($invoice->invoice_number, false);

        $settlement = CommercialDocumentView::forInvoice($invoice->fresh(['client', 'items', 'payments']), [
            'total_ttc' => 350,
        ])['doc']['settlement'];

        $this->assertSame('PARTIELLEMENT PAYÉE', $settlement['status_label']);
        $this->assertSame(200.0, $settlement['total_paid']);
        $this->assertSame(150.0, $settlement['remaining']);
        $this->assertSame('Carte bancaire', $settlement['payments'][0]['method']);
    }

    public function test_card_financial_movement_alone_does_not_settle_invoice(): void
    {
        $invoice = $this->makeInvoice(350);
        $order = PosSale::create([
            'ticket_number' => 'POS-CARD-ORPHAN',
            'client_id' => $invoice->client_id,
            'sold_at' => now(),
            'currency' => 'dh - MAD',
            'subtotal' => 350,
            'discount' => 0,
            'tax_total' => 0,
            'total' => 350,
            'payment_method' => PosSale::PAYMENT_CARD,
            'status' => PosSale::STATUS_COMPLETED,
            'payment_status' => 'paid',
            'fulfillment_status' => 'fulfilled',
            'amount_received' => 350,
        ]);
        $invoice->update(['pos_sale_id' => $order->id]);

        // Mouvement trésorerie POS sans InvoicePayment.
        FinancialMovement::create([
            'reference' => 'FM-ORPHAN-CARD',
            'movement_date' => now()->toDateString(),
            'origin' => FinancialMovement::ORIGIN_POS,
            'type' => FinancialMovement::TYPE_ENTREE,
            'label' => 'Encaissement POS '.$order->ticket_number.' — '.$invoice->client->name,
            'account' => FinancialMovement::ACCOUNT_BANQUE,
            'amount_in' => 350,
            'amount_out' => 0,
            'status' => FinancialMovement::STATUS_VALIDE,
            'source_type' => PosSale::class,
            'source_id' => $order->id,
        ]);

        $invoice->refresh();
        $this->assertEquals(0.0, $invoice->total_paid);
        $this->assertEquals(350.0, $invoice->remaining_balance);
        $this->assertNotSame(Invoice::PAYMENT_PAID, $invoice->computed_payment_status);
    }

    public function test_repair_command_allocates_orphan_card_payment_idempotently(): void
    {
        $invoice = $this->makeInvoice(350);
        $order = PosSale::create([
            'ticket_number' => 'POS-CARD-REPAIR',
            'client_id' => $invoice->client_id,
            'sold_at' => now(),
            'currency' => 'dh - MAD',
            'subtotal' => 350,
            'discount' => 0,
            'tax_total' => 0,
            'total' => 350,
            'payment_method' => PosSale::PAYMENT_CARD,
            'status' => PosSale::STATUS_COMPLETED,
            'payment_status' => 'paid',
            'fulfillment_status' => 'fulfilled',
            'amount_received' => 350,
        ]);
        $invoice->update(['pos_sale_id' => $order->id]);

        FinancialMovement::create([
            'reference' => 'FM-REPAIR-CARD',
            'movement_date' => now()->toDateString(),
            'origin' => FinancialMovement::ORIGIN_POS,
            'type' => FinancialMovement::TYPE_ENTREE,
            'label' => 'Encaissement POS '.$order->ticket_number.' — '.$invoice->client->name,
            'account' => FinancialMovement::ACCOUNT_BANQUE,
            'amount_in' => 350,
            'amount_out' => 0,
            'status' => FinancialMovement::STATUS_VALIDE,
            'source_type' => PosSale::class,
            'source_id' => $order->id,
        ]);

        $this->artisan('invoices:repair-card-payment-allocations')
            ->assertSuccessful()
            ->expectsOutputToContain('DRY-RUN');

        $this->assertSame(0, InvoicePayment::query()->where('invoice_id', $invoice->id)->count());

        $this->artisan('invoices:repair-card-payment-allocations', ['--apply' => true])
            ->assertSuccessful();

        $invoice->refresh()->load('payments');
        $this->assertSame(1, $invoice->payments()->count());
        $this->assertEquals(350.0, $invoice->total_paid);
        $this->assertEquals(0.0, $invoice->remaining_balance);
        $this->assertSame(Invoice::PAYMENT_PAID, $invoice->payment_status);
        $this->assertSame('Carte bancaire', $invoice->payments->first()->payment_method);

        // Idempotent : pas de doublon.
        $this->artisan('invoices:repair-card-payment-allocations', ['--apply' => true])
            ->assertSuccessful();

        $this->assertSame(1, InvoicePayment::query()->where('invoice_id', $invoice->id)->count());

        $report = app(ClientCardPaymentAllocationRepairService::class)->repair(false, $invoice->id);
        $this->assertSame(0, $report['matched']);
    }

    private function makeInvoice(float $total): Invoice
    {
        $client = Client::create(['name' => 'Client Carte '.uniqid()]);

        $invoice = Invoice::create([
            'invoice_number' => 'FAC-CB-'.uniqid(),
            'client_id' => $client->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'currency' => 'dh - MAD',
            'stock_location' => 'magasin',
            'subtotal' => $total,
            'discount' => 0,
            'adjustment' => 0,
            'total' => $total,
            'payment_status' => Invoice::PAYMENT_UNPAID,
        ]);

        $invoice->items()->create([
            'designation' => 'Article carte',
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
