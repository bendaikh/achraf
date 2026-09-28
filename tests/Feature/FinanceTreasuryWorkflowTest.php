<?php

namespace Tests\Feature;

use App\Models\BankCard;
use App\Models\BankStatementLine;
use App\Models\Endowment;
use App\Models\Expense;
use App\Models\FinanceOpeningBalance;
use App\Models\FinancialMovement;
use App\Models\User;
use App\Services\FinanceTreasuryService;
use App\Support\FinanceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinanceTreasuryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_pending_does_not_consume_endowment_until_paid(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $card = BankCard::create([
            'label' => 'Visa Pro',
            'account' => 'banque',
            'last_four' => '4242',
            'is_active' => true,
        ]);

        $endowment = Endowment::create([
            'label' => 'Dotation Q4',
            'initial_amount' => 5000,
            'consumed_amount' => 0,
            'bank_account' => 'banque',
            'is_active' => true,
        ]);
        $endowment->cards()->attach($card->id);

        $expense = Expense::create([
            'designation' => 'Fournitures',
            'expense_type' => 'without_invoice',
            'expense_date' => '2026-10-05',
            'amount' => 250,
            'currency' => 'dh - MAD',
            'payment_status' => Expense::PAYMENT_PENDING,
            'bank_card_id' => $card->id,
            'endowment_id' => $endowment->id,
        ]);

        $this->assertDatabaseMissing('financial_movements', [
            'source_type' => Expense::class,
            'source_id' => $expense->id,
        ]);
        $this->assertSame(0.0, (float) $endowment->fresh()->consumed_amount);

        $this->post(route('expenses-without-invoice.mark-paid', $expense))->assertRedirect();

        $expense->refresh();
        $this->assertSame(Expense::PAYMENT_PAID, $expense->payment_status);
        $this->assertDatabaseHas('financial_movements', [
            'source_type' => Expense::class,
            'source_id' => $expense->id,
            'amount_out' => 250,
        ]);
        $this->assertSame(250.0, (float) $endowment->fresh()->consumed_amount);
        $this->assertDatabaseHas('endowment_consumptions', [
            'endowment_id' => $endowment->id,
            'source_type' => Expense::class,
            'source_id' => $expense->id,
            'amount_mad' => 250,
        ]);
    }

    public function test_opening_balances_and_cutoff_balance(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->assertSame('2026-10-01', FinanceSettings::cutoffDateString());

        $this->post(route('financial.cutoff.openings'), [
            'balances' => [
                'caisse' => 1000,
                'banque' => 5000,
                'other' => 0,
            ],
            'notes' => 'Soldes bascule',
        ])->assertRedirect(route('financial.cutoff.index'));

        $opening = FinanceOpeningBalance::query()
            ->where('account', 'banque')
            ->whereDate('as_of_date', '2026-10-01')
            ->first();
        $this->assertNotNull($opening, 'Opening balance for banque should exist after save.');
        $this->assertEquals(5000.0, (float) $opening->amount);

        FinancialMovement::create([
            'reference' => 'MVT-TEST-1',
            'movement_date' => '2026-10-05',
            'origin' => FinancialMovement::ORIGIN_MANUEL,
            'type' => FinancialMovement::TYPE_ENTREE,
            'label' => 'Encaissement test',
            'account' => FinancialMovement::ACCOUNT_BANQUE,
            'amount_in' => 200,
            'amount_out' => 0,
            'status' => FinancialMovement::STATUS_VALIDE,
            'is_manual' => true,
            'is_opening_balance' => false,
        ]);

        $balance = app(FinanceTreasuryService::class)->accountBalance('banque', '2026-10-10');
        $this->assertEquals(5200.0, $balance);
    }

    public function test_bank_statement_import_suggests_and_matches_existing_movement(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->actingAs($user);

        $movement = FinancialMovement::create([
            'reference' => 'MVT-BANK-1',
            'movement_date' => '2026-10-08',
            'origin' => FinancialMovement::ORIGIN_MANUEL,
            'type' => FinancialMovement::TYPE_SORTIE,
            'label' => 'Paiement fournisseur',
            'account' => FinancialMovement::ACCOUNT_BANQUE,
            'amount_in' => 0,
            'amount_out' => 150.50,
            'status' => FinancialMovement::STATUS_VALIDE,
            'is_manual' => true,
            'is_opening_balance' => false,
        ]);

        $csv = "Date;Libellé;Débit;Crédit\n"
            ."08/10/2026;Paiement fournisseur;150,50;0\n";

        $file = UploadedFile::fake()->createWithContent('releve.csv', $csv);

        $response = $this->post(route('financial.bank-imports.store'), [
            'account' => 'banque',
            'statement_file' => $file,
        ]);

        $response->assertRedirect();
        $importId = (int) preg_replace('/\D/', '', (string) $response->headers->get('Location'));
        $this->assertGreaterThan(0, $importId);

        $line = BankStatementLine::query()->first();
        $this->assertNotNull($line);
        $this->assertSame(BankStatementLine::STATUS_SUGGESTED, $line->status);
        $this->assertSame($movement->id, (int) $line->financial_movement_id);

        $this->post(route('financial.bank-imports.lines.confirm', $line))->assertRedirect();

        $line->refresh();
        $movement->refresh();
        $this->assertSame(BankStatementLine::STATUS_MATCHED, $line->status);
        $this->assertSame(FinancialMovement::STATUS_POINTE, $movement->status);
        $this->assertEquals(1, FinancialMovement::query()->where('amount_out', 150.50)->count());
    }
}
