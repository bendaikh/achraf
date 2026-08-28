<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Services\SupplierAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierPaymentHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_fiche_and_pdf_are_available(): void
    {
        $user = User::factory()->create(['name' => 'hsabati']);
        $this->actingAs($user);

        $supplier = Supplier::create(['name' => 'SODIREP']);
        $invoice = SupplierInvoice::create([
            'invoice_number' => 'FF-2026-00125',
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-08-01',
            'total' => 6416,
        ]);

        $header = app(SupplierAccountService::class)->recordSettlement($supplier, [
            'payment_date' => '2026-08-22',
            'amount' => 6416,
            'payment_method' => 'Virement bancaire',
            'payment_reference' => 'VIR-220826',
            'invoice_ids' => [$invoice->id],
            'user_id' => $user->id,
        ]);

        $this->get(route('purchases.payments.show', $header))
            ->assertOk()
            ->assertSee('REG-2026-')
            ->assertSee('VIR-220826')
            ->assertSee('FF-2026-00125')
            ->assertSee('Affectation du règlement');

        $this->get(route('purchases.payments.pdf', $header))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->get(route('purchases.payments.settle', $supplier))
            ->assertOk()
            ->assertSee('Historique des règlements');

        $this->get(route('suppliers.show', $supplier))
            ->assertOk()
            ->assertSee('Historique des règlements');
    }
}
