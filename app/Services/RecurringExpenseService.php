<?php

namespace App\Services;

use App\Models\Expense;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RecurringExpenseService
{
    public function generateDueOccurrences(?CarbonImmutable $through = null): int
    {
        $through ??= CarbonImmutable::today();
        $generated = 0;

        $ids = Expense::query()
            ->whereNull('recurrence_parent_id')
            ->where('is_recurring', true)
            ->where('recurrence_status', Expense::RECURRENCE_ACTIVE)
            ->whereNotNull('next_due_date')
            ->whereDate('next_due_date', '<=', $through)
            ->pluck('id');

        foreach ($ids as $id) {
            $generated += DB::transaction(function () use ($id, $through): int {
                $template = Expense::query()->lockForUpdate()->find($id);

                if (! $template || $template->recurrence_status !== Expense::RECURRENCE_ACTIVE) {
                    return 0;
                }

                $count = 0;
                $dueDate = $template->next_due_date
                    ? CarbonImmutable::parse($template->next_due_date)
                    : null;

                while ($dueDate && $dueDate->lte($through) && $count < 1000) {
                    if ($template->recurrence_end_date && $dueDate->gt($template->recurrence_end_date)) {
                        $template->update([
                            'next_due_date' => null,
                            'recurrence_status' => Expense::RECURRENCE_STOPPED,
                        ]);
                        break;
                    }

                    $occurrence = Expense::firstOrCreate(
                        [
                            'recurrence_parent_id' => $template->id,
                            'occurrence_date' => $dueDate->toDateString(),
                        ],
                        $this->occurrenceAttributes($template, $dueDate)
                    );

                    if ($occurrence->wasRecentlyCreated) {
                        $count++;
                    }

                    $dueDate = $this->nextDate($template, $dueDate);
                    $template->next_due_date = $dueDate->toDateString();
                }

                if ($template->recurrence_end_date && $dueDate?->gt($template->recurrence_end_date)) {
                    $template->next_due_date = null;
                    $template->recurrence_status = Expense::RECURRENCE_STOPPED;
                }

                $template->save();

                return $count;
            });
        }

        return $generated;
    }

    public function nextDate(Expense $template, CarbonImmutable $from): CarbonImmutable
    {
        $interval = max(1, (int) $template->recurrence_interval);

        return match ($template->recurrence_frequency) {
            'weekly' => $from->addWeeks($interval),
            'monthly' => $from->addMonthsNoOverflow($interval),
            'quarterly' => $from->addMonthsNoOverflow(3 * $interval),
            'semiannual' => $from->addMonthsNoOverflow(6 * $interval),
            'annual' => $from->addYearsNoOverflow($interval),
            'custom' => $template->recurrence_interval_unit === 'day'
                ? $from->addDays($interval)
                : $from->addMonthsNoOverflow($interval),
            default => $from->addMonthNoOverflow(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function occurrenceAttributes(Expense $template, CarbonImmutable $dueDate): array
    {
        return [
            'designation' => $template->designation,
            'expense_type' => $template->expense_type,
            'expense_category' => $template->expense_category,
            'expense_date' => $dueDate->toDateString(),
            'amount' => $template->amount,
            'currency' => $template->currency,
            'reference' => $template->reference,
            'client_id' => $template->client_id,
            'supplier_id' => $template->supplier_id,
            'payment_method' => $template->payment_method,
            'account' => $template->account,
            'tax_type' => $template->tax_type,
            'invoice_file_path' => null,
            'payment_status' => Expense::PAYMENT_PENDING,
            'paid_at' => null,
            'is_recurring' => true,
        ];
    }
}
