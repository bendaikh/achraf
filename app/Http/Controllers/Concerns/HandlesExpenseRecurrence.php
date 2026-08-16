<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Expense;
use App\Services\RecurringExpenseService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

trait HandlesExpenseRecurrence
{
    /**
     * @return array<string, mixed>
     */
    protected function recurrenceRules(): array
    {
        return [
            'is_recurring' => ['nullable', 'boolean'],
            'recurrence_frequency' => [
                'nullable',
                Rule::requiredIf(fn () => request()->boolean('is_recurring')),
                Rule::in(array_keys(Expense::FREQUENCIES)),
            ],
            'recurrence_interval' => [
                'nullable',
                Rule::requiredIf(fn () => request()->boolean('is_recurring')),
                'integer',
                'min:1',
                'max:999',
            ],
            'recurrence_interval_unit' => [
                'nullable',
                Rule::requiredIf(fn () => request()->boolean('is_recurring') && request('recurrence_frequency') === 'custom'),
                Rule::in(['day', 'month']),
            ],
            'recurrence_start_date' => [
                'nullable',
                Rule::requiredIf(fn () => request()->boolean('is_recurring')),
                'date',
            ],
            'recurrence_end_date' => [
                'nullable',
                'date',
                'after_or_equal:recurrence_start_date',
                Rule::requiredIf(fn () => request()->boolean('is_recurring') && ! request()->boolean('recurrence_no_end')),
            ],
            'recurrence_no_end' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function prepareRecurrence(Request $request, array $validated): array
    {
        $isRecurring = $request->boolean('is_recurring');
        $validated['is_recurring'] = $isRecurring;
        $validated['payment_status'] = Expense::PAYMENT_PAID;

        if (! $isRecurring) {
            unset($validated['recurrence_no_end']);

            return array_merge($validated, [
                'recurrence_frequency' => null,
                'recurrence_interval' => 1,
                'recurrence_interval_unit' => null,
                'recurrence_start_date' => null,
                'recurrence_end_date' => null,
                'next_due_date' => null,
                'recurrence_status' => null,
            ]);
        }

        $validated['recurrence_interval'] = (int) ($validated['recurrence_interval'] ?? 1);
        $validated['recurrence_interval_unit'] = $validated['recurrence_frequency'] === 'custom'
            ? ($validated['recurrence_interval_unit'] ?? 'month')
            : null;
        $validated['recurrence_end_date'] = $request->boolean('recurrence_no_end')
            ? null
            : ($validated['recurrence_end_date'] ?? null);
        $validated['next_due_date'] = $validated['recurrence_start_date'];
        $validated['recurrence_status'] = Expense::RECURRENCE_ACTIVE;

        unset($validated['recurrence_no_end']);

        return $validated;
    }

    /**
     * Create a new effective-dated template so previous expenses remain immutable.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createFutureRecurrenceVersion(
        Expense $current,
        array $attributes,
        RecurringExpenseService $recurringExpenses
    ): Expense {
        $effectiveDate = CarbonImmutable::parse($attributes['recurrence_start_date']);
        $future = new Expense($attributes);
        $future->payment_status = Expense::PAYMENT_PENDING;
        $future->paid_at = null;
        $future->expense_date = $effectiveDate;
        $future->occurrence_date = $effectiveDate;
        $future->next_due_date = $recurringExpenses->nextDate($future, $effectiveDate);
        $future->save();

        $current->update([
            'recurrence_status' => Expense::RECURRENCE_STOPPED,
            'next_due_date' => null,
        ]);

        return $future;
    }

    protected function applyRecurringFilter($query, Request $request): void
    {
        match ($request->query('recurring')) {
            'yes' => $query->where('is_recurring', true),
            'no' => $query->where('is_recurring', false),
            default => null,
        };
    }

    protected function ensureExpenseType(Expense $expense, string $expectedType): void
    {
        abort_unless($expense->expense_type === $expectedType, 404);
    }
}
