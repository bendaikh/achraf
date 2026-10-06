<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\SupplierCreditNote;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Services\SupplierAccountService;
use App\Services\SupplierAllocationRepairService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SupplierPaymentAllocationRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_orphan_payment_is_restored_then_applied_to_open_invoice(): void
    {
        $supplier = Supplier::create(['name' => 'AZMI FRERES']);
        $invoice = $this->invoice($supplier, 'FSI-2026/000063', 39378);

        // Règlement compté sur le compte mais jamais affecté (unallocated=0 sans allocation).
        SupplierPayment::query()->create([
            'supplier_id' => $supplier->id,
            'payment_number' => 'REG-2026-009999',
            'payment_date' => '2026-06-01',
            'amount' => 36531,
            'unallocated_amount' => 0,
            'status' => SupplierPayment::STATUS_VALIDATED,
            'payment_method' => 'Virement bancaire',
            'source' => 'manual',
        ]);

        $accounts = app(SupplierAccountService::class);
        $repair = app(SupplierAllocationRepairService::class);

        $before = $accounts->statement($supplier);
        $this->assertSame(2847.0, $before['balance']);
        $this->assertSame(39378.0, $before['open_invoices']);
        $this->assertSame(0.0, $before['available_advances']);

        $audit = $repair->auditSupplier($supplier);
        $this->assertTrue($audit['needs_repair']);
        $this->assertSame(36531.0, $audit['orphan_unallocated']);
        $this->assertSame(36531.0, $audit['open_vs_balance_gap']);

        $this->artisan('suppliers:repair-payment-allocations', [
            '--supplier' => $supplier->id,
            '--apply' => true,
        ])->assertSuccessful();

        $invoice->refresh();
        $this->assertSame(2847.0, $accounts->invoiceRemaining($invoice));
        $this->assertSame(0.0, $accounts->availableAdvancesTotal($supplier));

        $after = $accounts->statement($supplier->fresh());
        $this->assertSame(2847.0, $after['balance']);
        $this->assertSame(2847.0, $after['open_invoices']);

        $trace = $accounts->invoiceTrace($invoice);
        $this->assertSame(39378.0, $trace['total']);
        $this->assertSame(36531.0, $trace['covered']);
        $this->assertSame(2847.0, $trace['net_to_pay']);

        // Idempotent
        $second = $repair->auditSupplier($supplier->fresh());
        $this->assertFalse($second['needs_repair']);
    }

    public function test_unused_credit_is_applied_with_orphan_advance(): void
    {
        $supplier = Supplier::create(['name' => 'AZMI+AVOIR']);
        $invoice = $this->invoice($supplier, 'FSI-CREDIT', 10000);
        $this->credit($supplier, 'AV-1', 2000);

        SupplierPayment::query()->create([
            'supplier_id' => $supplier->id,
            'payment_number' => 'REG-2026-008888',
            'payment_date' => '2026-06-01',
            'amount' => 7000,
            'unallocated_amount' => 0,
            'status' => SupplierPayment::STATUS_VALIDATED,
            'payment_method' => 'Virement bancaire',
            'source' => 'manual',
        ]);

        $accounts = app(SupplierAccountService::class);
        $this->assertSame(1000.0, $accounts->statement($supplier)['balance']);
        $this->assertSame(10000.0, $accounts->statement($supplier)['open_invoices']);

        $this->artisan('suppliers:repair-payment-allocations', [
            '--supplier' => $supplier->id,
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame(1000.0, $accounts->invoiceRemaining($invoice->refresh()));
        $this->assertSame(0.0, $accounts->availableCreditsTotal($supplier));
        $this->assertSame(0.0, $accounts->availableAdvancesTotal($supplier));
        $this->assertSame(1000.0, $accounts->statement($supplier->fresh())['open_invoices']);
    }

    public function test_cash_above_supplier_balance_requires_allow_advance(): void
    {
        $supplier = Supplier::create(['name' => 'Ctrl solde']);
        $invoice = $this->invoice($supplier, 'FA-CTRL', 10000);
        $accounts = app(SupplierAccountService::class);

        $this->expectException(ValidationException::class);

        $accounts->recordSettlement($supplier, [
            'payment_date' => '2026-10-06',
            'amount' => 11000,
            'payment_method' => 'Virement bancaire',
            'invoice_ids' => [$invoice->id],
            'use_credits' => false,
            'use_advances' => false,
            'allow_advance' => false,
        ]);
    }

    public function test_exact_supplier_balance_payment_is_allowed(): void
    {
        $supplier = Supplier::create(['name' => 'Exact']);
        $invoice = $this->invoice($supplier, 'FA-EXACT', 10000);
        $accounts = app(SupplierAccountService::class);

        // Premier règlement partiel pour laisser un solde
        $accounts->recordSettlement($supplier, [
            'payment_date' => '2026-10-01',
            'amount' => 7000,
            'payment_method' => 'Virement bancaire',
            'invoice_ids' => [$invoice->id],
        ]);

        $accounts->recordSettlement($supplier, [
            'payment_date' => '2026-10-06',
            'amount' => 3000,
            'payment_method' => 'Virement bancaire',
            'invoice_ids' => [$invoice->id],
            'allow_advance' => false,
        ]);

        $this->assertSame(0.0, $accounts->invoiceRemaining($invoice->refresh()));
        $this->assertSame(0.0, $accounts->statement($supplier->fresh())['balance']);
    }

    public function test_open_invoice_payload_exposes_covered_and_net_to_pay(): void
    {
        $supplier = Supplier::create(['name' => 'Payload']);
        $invoice = $this->invoice($supplier, 'FA-PAY', 39378);
        $accounts = app(SupplierAccountService::class);

        $accounts->recordSettlement($supplier, [
            'payment_date' => '2026-10-01',
            'amount' => 36531,
            'payment_method' => 'Virement bancaire',
            'invoice_ids' => [],
            'allow_advance' => true,
        ]);

        $rows = $accounts->openInvoicesPayload($supplier);
        $this->assertCount(1, $rows);
        $this->assertSame(39378.0, $rows[0]['total']);
        $this->assertSame(36531.0, $rows[0]['covered']);
        $this->assertSame(2847.0, $rows[0]['net_to_pay']);
        $this->assertSame($invoice->id, $rows[0]['id']);
    }

    private function invoice(Supplier $supplier, string $number, float $total): SupplierInvoice
    {
        return SupplierInvoice::create([
            'invoice_number' => $number,
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-08-01',
            'due_date' => '2026-08-15',
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
