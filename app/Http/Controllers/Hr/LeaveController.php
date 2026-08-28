<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Hr\LeaveBalanceService;
use App\Services\Hr\HrTimelineService;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    use FiltersIndexTables;

    public function index(Request $request)
    {
        $leaveQuery = LeaveRequest::query()->with(['employee', 'leaveType']);
        $this->applyTableSearch($leaveQuery, $request, ['employee.last_name', 'employee.first_name', 'employee.matricule']);
        $this->applyTableFilter($leaveQuery, $request, 'status', 'status');
        $this->applyTableFilter($leaveQuery, $request, 'employee_id', 'employee_id');
        $this->applyTableSort($leaveQuery, $request, ['start_date' => 'start_date'], 'start_date', 'desc');

        $absenceQuery = EmployeeAbsence::query()->with('employee');
        $this->applyTableFilter($absenceQuery, $request, 'employee_id', 'employee_id');
        $this->applyTableSort($absenceQuery, $request, ['start_date' => 'start_date'], 'start_date', 'desc');

        return view('hr.leaves.index', [
            'leaves' => $this->paginateTable($leaveQuery, $request),
            'absences' => $absenceQuery->limit(50)->get(),
            'employees' => Employee::query()->orderBy('last_name')->get(),
            'leaveTypes' => LeaveType::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, LeaveBalanceService $balances, HrTimelineService $timeline)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'days' => 'nullable|numeric|min:0.5',
            'comment' => 'nullable|string',
        ]);

        $validated['days'] = $validated['days'] ?? LeaveBalanceService::calendarDays($validated['start_date'], $validated['end_date']);
        $validated['status'] = LeaveRequest::STATUS_PENDING;
        $leave = LeaveRequest::create($validated);

        $timeline->record($leave->employee, 'leave', 'Demande de congé', $leave->start_date, $leave->comment, $leave);

        return back()->with('success', 'Demande de congé enregistrée.');
    }

    public function review(Request $request, LeaveRequest $leave, LeaveBalanceService $balances)
    {
        $validated = $request->validate([
            'status' => 'required|in:approved,rejected,cancelled',
            'review_comment' => 'nullable|string',
        ]);

        $previous = $leave->status;
        $leave->update([
            'status' => $validated['status'],
            'review_comment' => $validated['review_comment'] ?? null,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        if ($validated['status'] === LeaveRequest::STATUS_APPROVED) {
            $balances->applyApprovedLeave($leave);
        } elseif ($previous === LeaveRequest::STATUS_APPROVED) {
            $balances->reverseApprovedLeave($leave);
        }

        app(HrTimelineService::class)->record(
            $leave->employee,
            'leave',
            'Congé '.($validated['status'] === LeaveRequest::STATUS_APPROVED ? 'validé' : ($validated['status'] === 'rejected' ? 'refusé' : 'annulé')),
            now(),
            $validated['review_comment'] ?? null,
            $leave
        );

        return back()->with('success', 'Statut du congé mis à jour.');
    }

    public function storeAbsence(Request $request, HrTimelineService $timeline)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'type' => 'required|in:'.implode(',', array_keys(EmployeeAbsence::TYPES)),
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'days' => 'nullable|numeric|min:0.5',
            'comment' => 'nullable|string',
            'impacts_payroll' => 'sometimes|boolean',
        ]);

        $validated['days'] = $validated['days'] ?? LeaveBalanceService::calendarDays($validated['start_date'], $validated['end_date']);
        $validated['impacts_payroll'] = $request->boolean('impacts_payroll', true);
        $absence = EmployeeAbsence::create($validated);

        $timeline->record($absence->employee, 'absence', $absence->typeLabel(), $absence->start_date, $absence->comment, $absence);

        return back()->with('success', 'Absence enregistrée.');
    }

    public function storeBalance(Request $request, Employee $employee, LeaveBalanceService $balances)
    {
        $validated = $request->validate([
            'type' => 'required|in:initial,accrual,adjustment,carryover',
            'days' => 'required|numeric',
            'entry_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $balances->addEntry(
            $employee,
            $validated['type'],
            (float) $validated['days'],
            $validated['entry_date'],
            $validated['notes'] ?? null
        );

        return back()->with('success', 'Mouvement de solde de congés enregistré.');
    }
}
