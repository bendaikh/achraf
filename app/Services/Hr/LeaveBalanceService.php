<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\LeaveBalanceEntry;
use App\Models\LeaveRequest;

class LeaveBalanceService
{
    public function __construct(
        protected HrTimelineService $timeline,
    ) {}

    public function currentBalance(Employee $employee): float
    {
        $last = LeaveBalanceEntry::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('id')
            ->first();

        return $last ? (float) $last->balance_after : 0.0;
    }

    public function addEntry(
        Employee $employee,
        string $type,
        float $days,
        \DateTimeInterface|string $date,
        ?string $notes = null,
        ?LeaveRequest $request = null,
        ?int $userId = null,
    ): LeaveBalanceEntry {
        $balance = round($this->currentBalance($employee) + $days, 2);

        $entry = LeaveBalanceEntry::create([
            'employee_id' => $employee->id,
            'entry_date' => $date,
            'type' => $type,
            'days' => $days,
            'balance_after' => $balance,
            'leave_request_id' => $request?->id,
            'notes' => $notes,
            'created_by' => $userId ?? auth()->id(),
        ]);

        $this->timeline->record(
            $employee,
            'leave_balance',
            LeaveBalanceEntry::TYPES[$type] ?? $type,
            $date,
            sprintf('%s : %+s j — solde restant : %s j', LeaveBalanceEntry::TYPES[$type] ?? $type, number_format($days, 2, ',', ' '), number_format($balance, 2, ',', ' ')),
            $entry,
            $userId
        );

        return $entry;
    }

    public function ensureInitialBalance(Employee $employee, ?int $userId = null): void
    {
        $exists = LeaveBalanceEntry::query()
            ->where('employee_id', $employee->id)
            ->where('type', LeaveBalanceEntry::TYPE_INITIAL)
            ->exists();

        if ($exists) {
            return;
        }

        $days = (float) $employee->initial_leave_balance;
        if ($days == 0.0) {
            return;
        }

        $this->addEntry(
            $employee,
            LeaveBalanceEntry::TYPE_INITIAL,
            $days,
            $employee->hire_date ?? now(),
            'Solde repris à l\'ouverture du dossier',
            null,
            $userId
        );
    }

    public function applyApprovedLeave(LeaveRequest $request, ?int $userId = null): void
    {
        $request->loadMissing(['employee', 'leaveType']);

        if (! $request->leaveType?->impacts_balance) {
            return;
        }

        $already = LeaveBalanceEntry::query()
            ->where('leave_request_id', $request->id)
            ->where('type', LeaveBalanceEntry::TYPE_TAKEN)
            ->exists();

        if ($already) {
            return;
        }

        $this->addEntry(
            $request->employee,
            LeaveBalanceEntry::TYPE_TAKEN,
            -1 * (float) $request->days,
            $request->start_date,
            sprintf('Congé du %s au %s', $request->start_date->format('d/m/Y'), $request->end_date->format('d/m/Y')),
            $request,
            $userId
        );
    }

    public function reverseApprovedLeave(LeaveRequest $request, ?int $userId = null): void
    {
        $taken = LeaveBalanceEntry::query()
            ->where('leave_request_id', $request->id)
            ->where('type', LeaveBalanceEntry::TYPE_TAKEN)
            ->first();

        if (! $taken) {
            return;
        }

        $this->addEntry(
            $request->employee,
            LeaveBalanceEntry::TYPE_ADJUSTMENT,
            abs((float) $taken->days),
            now(),
            'Annulation / refus du congé #'.$request->id,
            $request,
            $userId
        );
    }

    public static function calendarDays(\DateTimeInterface $from, \DateTimeInterface $to): float
    {
        $start = \Carbon\Carbon::parse($from)->startOfDay();
        $end = \Carbon\Carbon::parse($to)->startOfDay();

        return max(1, $start->diffInDays($end) + 1);
    }
}
