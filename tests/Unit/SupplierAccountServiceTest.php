<?php

namespace Tests\Unit;

use App\Models\Supplier;
use App\Models\SupplierCreditNote;
use App\Models\SupplierInvoice;
use App\Services\SupplierAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierAccountServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_of_3000_with_900_credit_is_settled_by_2100_cash(): void
    {
        $supplier = Supplier::create(['name' => 'CAR SHOP']);
        $invoice = $this->invoice($supplier, 'FA-3000', 3000);
        $this->credit($supplier, 'AV-900', 900);

        $service = app(SupplierAccountService::class);
        $this->assertSame(2100.0, $service->statement($supplier)['balance']);

        $service->recordSettlement($supplier, [
            'payment_date' => '2026-08-19',
            'amount' => 2100,
            'payment_method' => 'Chèque',
            'cheque_number' => 'CHQ-1',
            'cheque_bank' => 'CIH BANK',
            'cheque_date' => '2026-08-19',
            'cheque_status' => 'cashed',
            'invoice_ids' => [$invoice->id],
            'use_credits' => true,
            'use_advances' => true,
        ]);

        $invoice->refresh();
        $this->assertSame(0.0, $service->invoiceRemaining($invoice));
        $this->assertSame('paid', $invoice->computed_payment_status);
        $this->assertSame(0.0, $service->statement($supplier)['balance']);
        $this->assertSame(0.0, $service->availableCreditsTotal($supplier));
    }

    public function test_credit_partially_used_remains_available(): void
    {
        $supplier = Supplier::create(['name' => 'Fournisseur avoir']);
        $invoice = $this->invoice($supplier, 'FA-500', 500);
        $credit = $this->credit($supplier, 'AV-900', 900);
        $service = app(SupplierAccountService::class);

        $service->recordSettlement($supplier, [
            'payment_date' => '2026-08-19',
            'amount' => 0,
            'payment_method' => 'Avoir',
            'invoice_ids' => [$invoice->id],
            'use_credits' => true,
        ]);

        $this->assertSame(0.0, $service->invoiceRemaining($invoice->refresh()));
        $this->assertSame(400.0, $service->creditNoteRemaining($credit->refresh()));
        $this->assertSame(400.0, $service->availableCreditsTotal($supplier));
        $this->assertSame(-400.0, $service->statement($supplier)['balance']);
    }

    public function test_one_cheque_settles_several_invoices_after_credits(): void
    {
        $supplier = Supplier::create(['name' => 'Multi']);
        $a = $this->invoice($supplier, 'FA-A', 10000);
        $b = $this->invoice($supplier, 'FA-B', 15000);
        $c = $this->invoice($supplier, 'FA-C', 8000);
        $this->credit($supplier, 'AV-3K', 3000);
        $service = app(SupplierAccountService::class);

        $header = $service->recordSettlement($supplier, [
            'payment_date' => '2026-08-19',
            'amount' => 30000,
            'payment_method' => 'Chèque',
            'cheque_number' => 'CHQ-30',
            'cheque_bank' => 'CIH BANK',
            'cheque_date' => '2026-08-19',
            'cheque_status' => 'cashed',
            'invoice_ids' => [$a->id, $b->id, $c->id],
            'use_credits' => true,
        ]);

        $this->assertSame(30000.0, (float) $header->amount);
        $this->assertSame(0.0, (float) $header->unallocated_amount);
        $this->assertSame(0.0, $service->invoiceRemaining($a->refresh()));
        $this->assertSame(0.0, $service->invoiceRemaining($b->refresh()));
        $this->assertSame(0.0, $service->invoiceRemaining($c->refresh()));
        $this->assertSame(0.0, $service->statement($supplier)['balance']);
        $this->assertCount(3, $header->allocations);
    }

    public function test_overpayment_becomes_advance_then_applies_to_next_invoice(): void
    {
        $supplier = Supplier::create(['name' => 'Avance']);
        $first = $this->invoice($supplier, 'FA260772', 35300);
        $service = app(SupplierAccountService::class);

        $header = $service->recordSettlement($supplier, [
            'payment_date' => '2026-08-19',
            'amount' => 37542,
            'payment_method' => 'Chèque',
            'cheque_number' => 'CHQ-37542',
            'cheque_bank' => 'CIH BANK',
            'cheque_date' => '2026-08-19',
            'cheque_status' => 'cashed',
            'invoice_ids' => [$first->id],
            'use_credits' => false,
        ]);

        $this->assertSame(0.0, $service->invoiceRemaining($first->refresh()));
        $this->assertSame(2242.0, (float) $header->unallocated_amount);
        $this->assertSame(-2242.0, $service->statement($supplier)['balance']);

        $second = $this->invoice($supplier, 'FA-NEXT', 3000);
        $service->recordSettlement($supplier, [
            'payment_date' => '2026-08-20',
            'amount' => 758,
            'payment_method' => 'Virement bancaire',
            'invoice_ids' => [$second->id],
            'use_credits' => false,
            'use_advances' => true,
        ]);

        $this->assertSame(0.0, $service->invoiceRemaining($second->refresh()));
        $this->assertSame(0.0, $service->availableAdvancesTotal($supplier->refresh()));
        $this->assertSame(0.0, $service->statement($supplier->fresh())['balance']);
    }

    public function test_payment_keeps_number_and_history_after_cancel(): void
    {
        $supplier = Supplier::create(['name' => 'SODIREP']);
        $invoice = $this->invoice($supplier, 'FF-2026-00125', 6416);
        $service = app(SupplierAccountService::class);

        $header = $service->recordSettlement($supplier, [
            'payment_date' => '2026-08-22',
            'amount' => 6416,
            'payment_method' => 'Virement bancaire',
            'payment_reference' => 'VIR-220826',
            'invoice_ids' => [$invoice->id],
        ]);

        $this->assertMatchesRegularExpression('/^REG-2026-\d{6}$/', $header->payment_number);
        $this->assertSame(0.0, $service->invoiceRemaining($invoice->refresh()));

        $service->cancelPayment($header, 'règlement saisi en double');

        $header->refresh();
        $this->assertTrue($header->isCancelled());
        $this->assertSame(6416.0, $service->invoiceRemaining($invoice->refresh()));
        $this->assertSame(6416.0, $service->statement($supplier->fresh())['balance']);
        $this->assertDatabaseHas('supplier_payments', ['id' => $header->id, 'status' => 'cancelled']);
        $this->assertCount(1, $service->paymentHistory($supplier));
        $this->assertSame('Annulé', $service->paymentHistory($supplier)[0]['status']);
    }

    public function test_updating_amount_recalculates_invoice_balance(): void
    {
        $supplier = Supplier::create(['name' => 'Corr']);
        $invoice = $this->invoice($supplier, 'FA-ERR', 6416);
        $service = app(SupplierAccountService::class);

        $header = $service->recordSettlement($supplier, [
            'payment_date' => '2026-08-22',
            'amount' => 6416,
            'payment_method' => 'Virement bancaire',
            'invoice_ids' => [$invoice->id],
        ]);

        $service->updateSettlement($header, [
            'payment_date' => '2026-08-22',
            'amount' => 6146,
            'payment_method' => 'Virement bancaire',
            'payment_reference' => 'VIR-CORR',
            'invoice_ids' => [$invoice->id],
            'use_credits' => false,
            'use_advances' => false,
            'reallocate' => true,
            'reason' => 'correction montant',
        ]);

        $this->assertSame(270.0, $service->invoiceRemaining($invoice->refresh()));
        $header->refresh();
        $this->assertSame(6146.0, (float) $header->amount);
        $this->assertSame($header->payment_number, $header->payment_number);
        $this->assertSame(1, \App\Models\SupplierPayment::query()->where('supplier_id', $supplier->id)->count());
    }

    public function test_descriptive_update_does_not_change_balances(): void
    {
        $supplier = Supplier::create(['name' => 'Meta']);
        $invoice = $this->invoice($supplier, 'FA-META', 1000);
        $service = app(SupplierAccountService::class);
        $header = $service->recordSettlement($supplier, [
            'payment_date' => '2026-08-22',
            'amount' => 1000,
            'payment_method' => 'Virement bancaire',
            'payment_reference' => 'OLD',
            'invoice_ids' => [$invoice->id],
        ]);

        $service->updateSettlement($header, [
            'payment_date' => '2026-08-22',
            'payment_method' => 'Virement bancaire',
            'payment_reference' => 'NEW-REF',
            'notes' => 'corrigé',
            'reallocate' => false,
        ]);

        $this->assertSame(0.0, $service->invoiceRemaining($invoice->refresh()));
        $this->assertSame('NEW-REF', $header->fresh()->payment_reference);
    }

    public function test_one_payment_snapshot_splits_across_invoices(): void
    {
        $supplier = Supplier::create(['name' => 'Multi2']);
        $a = $this->invoice($supplier, 'FA-1', 4000);
        $b = $this->invoice($supplier, 'FA-2', 3000);
        $c = $this->invoice($supplier, 'FA-3', 3000);
        $service = app(SupplierAccountService::class);
        $header = $service->recordSettlement($supplier, [
            'payment_date' => '2026-08-22',
            'amount' => 10000,
            'payment_method' => 'Virement bancaire',
            'invoice_ids' => [$a->id, $b->id, $c->id],
        ]);

        $rows = $header->fresh()->allocation_snapshot['invoices'];
        $this->assertCount(3, $rows);
        $this->assertEquals(10000.0, collect($rows)->sum('cash_applied'));
    }

    private function invoice(Supplier $supplier, string $number, float $total): SupplierInvoice
    {
        return SupplierInvoice::create([
            'invoice_number' => $number,
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-08-01',
            'total' => $total,
        ]);
    }

    private function credit(Supplier $supplier, string $number, float $total): SupplierCreditNote
    {
        return SupplierCreditNote::create([
            'credit_note_number' => $number,
            'supplier_id' => $supplier->id,
            'credit_note_date' => '2026-08-02',
            'total' => $total,
        ]);
    }
}
