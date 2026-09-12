<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Services\Hr\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    use FiltersIndexTables;

    public function index(Request $request, AttendanceService $service)
    {
        $mode = $request->input('mode', 'monthly') === 'daily' ? 'daily' : 'monthly';
        $employees = Employee::query()
            ->where('status', Employee::STATUS_ACTIF)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        if ($mode === 'monthly') {
            $employeeId = (int) $request->input('employee_id', $employees->first()?->id ?? 0);
            $year = (int) $request->input('year', now()->year);
            $month = (int) $request->input('month', now()->month);

            if ($month < 1 || $month > 12) {
                $month = (int) now()->month;
            }
            if ($year < 2000 || $year > 2100) {
                $year = (int) now()->year;
            }

            $employee = $employees->firstWhere('id', $employeeId);
            $grid = $employee
                ? $service->buildMonthGrid($employee, $year, $month)
                : ['days' => [], 'summary' => $service->summarizeMonthDays([])];

            $cursor = Carbon::create($year, $month, 1);
            $prev = $cursor->copy()->subMonth();
            $next = $cursor->copy()->addMonth();

            return view('hr.attendance.index', [
                'mode' => $mode,
                'employees' => $employees,
                'employee' => $employee,
                'employeeId' => $employeeId,
                'year' => $year,
                'month' => $month,
                'days' => $grid['days'],
                'summary' => $grid['summary'],
                'prevMonth' => $prev->month,
                'prevYear' => $prev->year,
                'nextMonth' => $next->month,
                'nextYear' => $next->year,
                'monthNames' => $this->monthNames(),
                'records' => null,
                'preset' => null,
            ]);
        }

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
            'mode' => $mode,
            'employees' => $employees,
            'records' => $this->paginateTable($query, $request),
            'preset' => $preset,
            'employee' => null,
            'employeeId' => null,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'days' => [],
            'summary' => null,
            'monthNames' => $this->monthNames(),
        ]);
    }

    public function store(Request $request, AttendanceService $service)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'work_date' => 'required|date',
            'clock_in' => 'nullable|date_format:H:i',
            'clock_out' => 'nullable|date_format:H:i',
            'status' => ['required', Rule::in(array_keys(AttendanceRecord::STATUSES))],
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

        return back()->with('success', $existing ? 'Pointage corrigé et historisé.' : 'Pointage enregistré.');
    }

    public function storeMonth(Request $request, AttendanceService $service)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'correction_reason' => 'nullable|string|max:500',
            'days' => 'required|array|min:1',
            'days.*.work_date' => 'required|date',
            'days.*.status' => ['required', Rule::in(array_keys(AttendanceRecord::STATUSES))],
            'days.*.clock_in' => 'nullable|date_format:H:i',
            'days.*.clock_out' => 'nullable|date_format:H:i',
            'days.*.notes' => 'nullable|string|max:500',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $hasExisting = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereYear('work_date', $validated['year'])
            ->whereMonth('work_date', $validated['month'])
            ->exists();

        if ($hasExisting && ! $request->filled('correction_reason')) {
            return back()->withInput()->with('error', 'Indiquez un motif de correction pour mettre à jour un mois déjà saisi.');
        }

        $count = $service->saveMonth(
            $employee,
            (int) $validated['year'],
            (int) $validated['month'],
            $validated['days'],
            $validated['correction_reason'] ?? 'Saisie mensuelle',
        );

        return redirect()
            ->route('hr.attendance.index', [
                'mode' => 'monthly',
                'employee_id' => $employee->id,
                'year' => $validated['year'],
                'month' => $validated['month'],
            ])
            ->with('success', "Mois enregistré ({$count} jour(s)).");
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

    /**
     * @return array<int, string>
     */
    private function monthNames(): array
    {
        return [
            1 => 'Janvier',
            2 => 'Février',
            3 => 'Mars',
            4 => 'Avril',
            5 => 'Mai',
            6 => 'Juin',
            7 => 'Juillet',
            8 => 'Août',
            9 => 'Septembre',
            10 => 'Octobre',
            11 => 'Novembre',
            12 => 'Décembre',
        ];
    }
}
