<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\FinancialMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringExpenseCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_existing_forms_can_create_a_recurring_expense(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ([
            'expenses-with-invoice.store' => 'with_invoice',
            'expenses-without-invoice.store' => 'without_invoice',
        ] as $route => $type) {
            $response = $this->post(route($route), [
                'designation' => 'Abonnement '.$type,
                'expense_date' => '2026-08-15',
                'amount' => 200,
                'currency' => 'dh - MAD',
                'tax_type' => 'NO TAXE',
                'is_recurring' => '1',
                'recurrence_frequency' => 'monthly',
                'recurrence_interval' => 1,
                'recurrence_start_date' => '2026-09-15',
                'recurrence_no_end' => '1',
            ]);

            $response->assertRedirect();
            $expense = Expense::query()
                ->where('designation', 'Abonnement '.$type)
                ->where('expense_type', $type)
                ->first();

            $this->assertNotNull($expense);
            $this->assertTrue($expense->is_recurring);
            $this->assertSame('2026-09-15', $expense->next_due_date->format('Y-m-d'));
            $this->assertSame(Expense::RECURRENCE_ACTIVE, $expense->recurrence_status);
        }
    }

    public function test_generated_occurrence_can_be_marked_paid(): void
    {
        $this->actingAs(User::factory()->create());

        $expense = Expense::create([
            'designation' => 'Gardiennage',
            'expense_type' => 'without_invoice',
            'expense_date' => '2026-09-01',
            'amount' => 500,
            'currency' => 'dh - MAD',
            'payment_status' => Expense::PAYMENT_PENDING,
            'is_recurring' => true,
        ]);

        $this->assertDatabaseMissing('financial_movements', [
            'source_type' => Expense::class,
            'source_id' => $expense->id,
        ]);

        $this->post(route('expenses.mark-paid', $expense))->assertRedirect();

        $this->assertSame(Expense::PAYMENT_PAID, $expense->fresh()->payment_status);
        $this->assertDatabaseHas('financial_movements', [
            'source_type' => Expense::class,
            'source_id' => $expense->id,
            'type' => FinancialMovement::TYPE_SORTIE,
        ]);
    }
}
