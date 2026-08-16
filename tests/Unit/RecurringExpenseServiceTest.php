<?php

namespace Tests\Unit;

use App\Models\Expense;
use App\Services\RecurringExpenseService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringExpenseServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_pending_occurrences_once_without_copying_invoice(): void
    {
        $template = Expense::create([
            'designation' => 'Loyer magasin',
            'expense_type' => 'with_invoice',
            'expense_date' => '2026-08-01',
            'amount' => 3500,
            'currency' => 'dh - MAD',
            'invoice_file_path' => 'expenses/invoices/august.pdf',
            'payment_status' => Expense::PAYMENT_PAID,
            'is_recurring' => true,
            'recurrence_frequency' => 'monthly',
            'recurrence_interval' => 1,
            'recurrence_start_date' => '2026-09-01',
            'next_due_date' => '2026-09-01',
            'recurrence_status' => Expense::RECURRENCE_ACTIVE,
        ]);

        $service = app(RecurringExpenseService::class);

        $this->assertSame(2, $service->generateDueOccurrences(CarbonImmutable::parse('2026-10-01')));
        $this->assertSame(0, $service->generateDueOccurrences(CarbonImmutable::parse('2026-10-01')));

        $occurrences = $template->occurrences()->orderBy('occurrence_date')->get();

        $this->assertCount(2, $occurrences);
        $this->assertSame(['2026-09-01', '2026-10-01'], $occurrences->pluck('occurrence_date')->map->format('Y-m-d')->all());
        $this->assertTrue($occurrences->every(fn (Expense $expense) => $expense->payment_status === Expense::PAYMENT_PENDING));
        $this->assertTrue($occurrences->every(fn (Expense $expense) => $expense->invoice_file_path === null));
        $this->assertSame('2026-11-01', $template->fresh()->next_due_date->format('Y-m-d'));
    }

    public function test_it_stops_at_the_end_date(): void
    {
        $template = Expense::create([
            'designation' => 'Abonnement',
            'expense_type' => 'without_invoice',
            'expense_date' => '2026-08-15',
            'amount' => 200,
            'currency' => 'dh - MAD',
            'payment_status' => Expense::PAYMENT_PAID,
            'is_recurring' => true,
            'recurrence_frequency' => 'monthly',
            'recurrence_interval' => 1,
            'recurrence_start_date' => '2026-09-15',
            'recurrence_end_date' => '2026-10-15',
            'next_due_date' => '2026-09-15',
            'recurrence_status' => Expense::RECURRENCE_ACTIVE,
        ]);

        $count = app(RecurringExpenseService::class)
            ->generateDueOccurrences(CarbonImmutable::parse('2026-12-31'));

        $this->assertSame(2, $count);
        $this->assertSame(Expense::RECURRENCE_STOPPED, $template->fresh()->recurrence_status);
        $this->assertNull($template->fresh()->next_due_date);
    }
}
