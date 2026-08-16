<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Expense;
use App\Models\FinancialMovement;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PosSale;
use App\Services\FinancialMovementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialMovementServiceTest extends TestCase
{
    use RefreshDatabase;

    private FinancialMovementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FinancialMovementService::class);
    }

    public function test_invoice_payment_creates_entree_movement(): void
    {
        $client = Client::create(['name' => 'Client Test']);
        $invoice = Invoice::create([
            'invoice_number' => 'FAC-MVT-1',
            'client_id' => $client->id,
            'invoice_date' => '2026-08-01',
            'currency' => 'dh - MAD',
            'stock_location' => 'magasin',
            'subtotal' => 100,
            'discount' => 0,
            'adjustment' => 0,
            'total' => 120,
            'payment_status' => 'unpaid',
        ]);

        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_date' => '2026-08-02',
            'amount' => 120,
            'payment_method' => 'virement',
        ]);

        $movement = FinancialMovement::query()
            ->where('source_type', InvoicePayment::class)
            ->where('source_id', $payment->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(FinancialMovement::TYPE_ENTREE, $movement->type);
        $this->assertEquals(120.0, (float) $movement->amount_in);
        $this->assertEquals(FinancialMovement::ACCOUNT_BANQUE, $movement->account);
    }

    public function test_expense_creates_sortie_movement(): void
    {
        Expense::create([
            'designation' => 'Essence',
            'expense_type' => 'without_invoice',
            'expense_date' => '2026-08-03',
            'amount' => 200,
            'currency' => 'dh - MAD',
            'payment_method' => 'espèces',
            'account' => 'caisse',
        ]);

        $movement = FinancialMovement::query()->where('origin', FinancialMovement::ORIGIN_DEPENSE)->first();

        $this->assertNotNull($movement);
        $this->assertEquals(FinancialMovement::TYPE_SORTIE, $movement->type);
        $this->assertEquals(200.0, (float) $movement->amount_out);
        $this->assertEquals(FinancialMovement::ACCOUNT_CAISSE, $movement->account);
    }

    public function test_pending_expense_only_impacts_treasury_after_payment(): void
    {
        $expense = Expense::create([
            'designation' => 'Loyer à venir',
            'expense_type' => 'with_invoice',
            'expense_date' => '2026-09-01',
            'amount' => 3500,
            'currency' => 'dh - MAD',
            'payment_status' => Expense::PAYMENT_PENDING,
        ]);

        $this->assertDatabaseMissing('financial_movements', [
            'source_type' => Expense::class,
            'source_id' => $expense->id,
        ]);

        $expense->update([
            'payment_status' => Expense::PAYMENT_PAID,
            'paid_at' => '2026-09-03 10:00:00',
        ]);

        $movement = FinancialMovement::query()
            ->where('source_type', Expense::class)
            ->where('source_id', $expense->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertSame('2026-09-03', $movement->movement_date->format('Y-m-d'));
        $this->assertEquals(3500, (float) $movement->amount_out);
        $this->assertDatabaseHas('financial_movements', [
            'source_type' => Expense::class,
            'source_id' => $expense->id,
        ]);
    }

    public function test_pos_with_invoice_payments_is_not_duplicated(): void
    {
        $client = Client::create(['name' => 'POS Client']);

        $sale = PosSale::create([
            'ticket_number' => 'POS-MVT-1',
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

        $this->assertEquals(1, FinancialMovement::query()->where('source_type', PosSale::class)->count());

        $invoice = Invoice::create([
            'invoice_number' => 'FAC-POS-1',
            'client_id' => $client->id,
            'pos_sale_id' => $sale->id,
            'invoice_date' => '2026-08-01',
            'currency' => 'dh - MAD',
            'stock_location' => 'magasin',
            'subtotal' => 120,
            'discount' => 0,
            'adjustment' => 0,
            'total' => 120,
            'payment_status' => 'paid',
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_date' => '2026-08-01',
            'amount' => 120,
            'payment_method' => 'espèces',
        ]);

        $sale->refresh();
        $this->service->syncFromPosSale($sale);

        $this->assertEquals(0, FinancialMovement::query()->where('source_type', PosSale::class)->count());
        $this->assertEquals(1, FinancialMovement::query()->where('source_type', InvoicePayment::class)->count());
    }

    public function test_manual_movement_and_treasury(): void
    {
        $this->service->createManual([
            'movement_date' => '2026-08-01',
            'type' => FinancialMovement::TYPE_ENTREE,
            'origin' => FinancialMovement::ORIGIN_MANUEL,
            'label' => 'Ajustement',
            'account' => FinancialMovement::ACCOUNT_BANQUE,
            'amount' => 500,
        ], null);

        $treasury = $this->service->treasuryFromMovements();

        $this->assertEquals(500.0, $treasury['banque']);
        $this->assertEquals(500.0, $treasury['total']);
    }
}
