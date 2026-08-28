<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Controller;
use App\Models\CompensationItem;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Services\Hr\EmployeeService;
use App\Services\Hr\HrTimelineService;
use Illuminate\Http\Request;

class CompensationController extends Controller
{
    use FiltersIndexTables;

    public function index(Request $request)
    {
        $query = CompensationItem::query()->with('employee');
        $this->applyTableSearch($query, $request, ['employee.last_name', 'employee.first_name', 'employee.matricule']);
        $this->applyTableFilter($query, $request, 'kind', 'kind');
        $this->applyTableFilter($query, $request, 'employee_id', 'employee_id');
        $this->applyTableSort($query, $request, ['start_date' => 'start_date'], 'start_date', 'desc');

        $adjustments = PayrollAdjustment::query()->with('employee')->latest()->limit(50)->get();

        return view('hr.compensations.index', [
            'items' => $this->paginateTable($query, $request),
            'adjustments' => $adjustments,
            'employees' => Employee::query()->orderBy('last_name')->get(),
        ]);
    }

    public function store(Request $request, HrTimelineService $timeline)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'kind' => 'required|in:'.implode(',', array_keys(CompensationItem::KINDS)),
            'recurrence' => 'required|in:'.implode(',', array_keys(CompensationItem::RECURRENCES)),
            'amount' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'notes' => 'nullable|string',
        ]);

        $item = CompensationItem::create($validated);
        $timeline->record($item->employee, 'compensation', $item->kindLabel(), $item->start_date, number_format((float) $item->amount, 2, ',', ' ').' MAD', $item);

        return back()->with('success', 'Prime / indemnité enregistrée. L\'élément récurrent sera repris automatiquement dans les mois concernés.');
    }

    public function storeSalary(Request $request, Employee $employee, EmployeeService $service)
    {
        $validated = $request->validate([
            'effective_date' => 'required|date',
            'base_salary' => 'required|numeric|min:0',
            'negotiated_as' => 'required|in:brut,net',
            'notes' => 'nullable|string',
        ]);

        $service->addSalary($employee, $validated);

        return back()->with('success', 'Nouveau salaire enregistré. L\'ancien salaire reste dans l\'historique.');
    }

    public function storeAdjustment(Request $request, HrTimelineService $timeline)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'type' => 'required|in:'.implode(',', array_keys(PayrollAdjustment::TYPES)),
            'amount' => 'required|numeric',
            'period_year' => 'required|integer|min:2000',
            'period_month' => 'required|integer|min:1|max:12',
            'reason' => 'nullable|string',
            'payment_method' => 'nullable|string|max:40',
            'reference' => 'nullable|string|max:80',
        ]);
        $validated['remaining_amount'] = $validated['amount'];

        $adj = PayrollAdjustment::create($validated);
        $timeline->record(
            $adj->employee,
            'adjustment',
            $adj->typeLabel(),
            now()->setDate($adj->period_year, $adj->period_month, 1),
            trim(number_format((float) $adj->amount, 2, ',', ' ').' MAD — '.($adj->reason ?? '')),
            $adj
        );

        return back()->with('success', 'Retenue / avance enregistrée.');
    }
}
