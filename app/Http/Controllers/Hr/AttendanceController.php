<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Services\Hr\AttendanceService;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    use FiltersIndexTables;

    public function index(Request $request)
    {
        $query = AttendanceRecord::query()->with('employee');

        $this->applyTableSearch($query, $request, ['employee.last_name', 'employee.first_name', 'employee.matricule']);
        $this->applyTableFilter($query, $request, 'status', 'status');
        $this->applyTableFilter($query, $request, 'employee_id', 'employee_id');

        $preset = $request->input('preset', 'month');
        if ($preset === 'today') {
            $query->whereDate('work_date', now()->toDateString());
        } elseif ($preset === 'week') {
            $query->whereBetween('work_date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()]);
        } elseif ($preset === 'month' && ! $request->filled('date_from')) {
            $query->whereBetween('work_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]);
        } else {
            $this->applyTableDateRange($query, $request, 'work_date');
        }

        $this->applyTableSort($query, $request, [
            'work_date' => 'work_date',
        ], 'work_date', 'desc');

        return view('hr.attendance.index', [
            'records' => $this->paginateTable($query, $request),
            'employees' => Employee::query()->where('status', Employee::STATUS_ACTIF)->orderBy('last_name')->get(),
            'preset' => $preset,
        ]);
    }

    public function store(Request $request, AttendanceService $service)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'work_date' => 'required|date',
            'clock_in' => 'nullable|date_format:H:i',
            'clock_out' => 'nullable|date_format:H:i',
            'status' => 'required|in:present,absent,leave,rest,holiday,late',
            'notes' => 'nullable|string',
            'correction_reason' => 'nullable|string|max:500',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $existing = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $validated['work_date'])
            ->exists();

        if ($existing && ! $request->filled('correction_reason')) {
            return back()->withInput()->with('error', 'Toute correction de pointage doit indiquer un motif.');
        }

        $service->upsertManual($employee, $validated, $validated['correction_reason'] ?? null);
        unset($validated);

        return back()->with('success', $existing ? 'Pointage corrigé et historisé.' : 'Pointage enregistré.');
    }

    public function updateSchedule(Request $request, Employee $employee, AttendanceService $service)
    {
        $validated = $request->validate([
            'effective_from' => 'required|date',
            'days' => 'required|array',
            'days.*.weekday' => 'required|integer|min:1|max:7',
            'days.*.start_time' => 'nullable|date_format:H:i',
            'days.*.end_time' => 'nullable|date_format:H:i',
            'days.*.break_minutes' => 'nullable|integer|min:0',
            'days.*.is_off' => 'sometimes|boolean',
        ]);

        $service->applyScheduleVersion($employee, $validated['days'], $validated['effective_from']);

        return back()->with('success', 'Planning enregistré avec date d’effet. Les anciens horaires restent dans l’historique.');
    }
}
