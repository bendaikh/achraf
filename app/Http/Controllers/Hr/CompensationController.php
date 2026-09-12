<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Controller;
use App\Models\CompensationItem;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Services\Hr\EmployeeService;
use App\Services\Hr\HrAuditService;
use App\Services\Hr\HrTimelineService;
use Carbon\Carbon;
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

        $adjustments = PayrollAdjustment::query()->with('employee')->latest()->limit(100)->get();

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
        $validated['status'] = CompensationItem::STATUS_ACTIF;

        $item = CompensationItem::create($validated);
        $timeline->record($item->employee, 'compensation', $item->kindLabel(), $item->start_date, number_format((float) $item->amount, 2, ',', ' ').' MAD', $item);

        return back()->with('success', 'Prime / indemnité enregistrée. L\'élément récurrent sera repris automatiquement dans les mois concernés.');
    }

    public function update(Request $request, CompensationItem $compensation, HrAuditService $audit, HrTimelineService $timeline)
    {
        $validated = $request->validate([
            'kind' => 'required|in:'.implode(',', array_keys(CompensationItem::KINDS)),
            'recurrence' => 'required|in:'.implode(',', array_keys(CompensationItem::RECURRENCES)),
            'amount' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'notes' => 'nullable|string',
            'change_reason' => 'nullable|string|max:500',
        ]);

        $before = $compensation->getAttributes();
        $compensation->update($validated);
        $audit->logChanges($compensation, $before, $compensation->fresh()->getAttributes(), $validated['change_reason'] ?? 'Modification prime/indemnité');
        $timeline->record($compensation->employee, 'compensation', 'Modification '.$compensation->kindLabel(), now(), $validated['change_reason'] ?? null, $compensation);

        return back()->with('success', 'Élément mis à jour. L’historique est conservé.');
    }

    public function changeStatus(Request $request, CompensationItem $compensation, HrAuditService $audit, HrTimelineService $timeline)
    {
        $validated = $request->validate([
            'status' => 'required|in:actif,suspendu,arrete',
            'effective_date' => 'required|date',
            'reason' => 'nullable|string|max:500',
        ]);

        $before = $compensation->getAttributes();
        $effective = Carbon::parse($validated['effective_date']);

        $payload = [
            'status' => $validated['status'],
            'status_effective_date' => $effective->toDateString(),
        ];

        if (in_array($validated['status'], [CompensationItem::STATUS_SUSPENDU, CompensationItem::STATUS_ARRETE], true)) {
            // Stop applying from the day before the effective date (history preserved).
            $payload['end_date'] = $effective->copy()->subDay()->toDateString();
        }

        if ($validated['status'] === CompensationItem::STATUS_ACTIF) {
            $payload['end_date'] = null;
        }

        $compensation->update($payload);
        $audit->logChanges($compensation, $before, $compensation->fresh()->getAttributes(), $validated['reason'] ?? 'Changement de statut');
        $timeline->record(
            $compensation->employee,
            'compensation',
            $compensation->statusLabel().' — '.$compensation->kindLabel(),
            $effective,
            $validated['reason'] ?? null,
            $compensation
        );

        return back()->with('success', 'Statut mis à jour à compter du '.$effective->format('d/m/Y').'.');
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
            'amount' => 'required|numeric|min:0.01',
            'monthly_amount' => 'nullable|numeric|min:0.01',
            'period_year' => 'required|integer|min:2000',
            'period_month' => 'required|integer|min:1|max:12',
            'start_date' => 'nullable|date',
            'reason' => 'nullable|string',
            'payment_method' => 'nullable|string|max:40',
            'reference' => 'nullable|string|max:80',
        ]);

        $start = $validated['start_date']
            ?? Carbon::create($validated['period_year'], $validated['period_month'], 1)->toDateString();

        $validated['start_date'] = $start;
        $validated['remaining_amount'] = $validated['amount'];
        $validated['status'] = PayrollAdjustment::STATUS_ACTIF;
        $validated['monthly_amount'] = $validated['monthly_amount'] ?? null;

        $adj = PayrollAdjustment::create($validated);
        $timeline->record(
            $adj->employee,
            'adjustment',
            $adj->typeLabel(),
            Carbon::parse($start),
            trim(number_format((float) $adj->amount, 2, ',', ' ').' MAD'
                .($adj->monthly_amount ? ' · '.$adj->monthly_amount.' MAD/mois' : '')
                .' — '.($adj->reason ?? '')),
            $adj
        );

        return back()->with('success', 'Retenue / avance enregistrée. Le solde sera suivi automatiquement jusqu’à extinction.');
    }

    public function updateAdjustment(Request $request, PayrollAdjustment $adjustment, HrAuditService $audit)
    {
        $validated = $request->validate([
            'monthly_amount' => 'nullable|numeric|min:0.01',
            'status' => 'required|in:actif,suspendu,termine',
            'end_date' => 'nullable|date',
            'reason' => 'nullable|string',
            'change_reason' => 'nullable|string|max:500',
        ]);

        $before = $adjustment->getAttributes();

        if ($validated['status'] === PayrollAdjustment::STATUS_TERMINE) {
            $validated['remaining_amount'] = 0;
            $validated['recovered_at'] = $validated['recovered_at'] ?? now();
            $validated['end_date'] = $validated['end_date'] ?? now()->toDateString();
        }

        $adjustment->update($validated);
        $audit->logChanges($adjustment, $before, $adjustment->fresh()->getAttributes(), $validated['change_reason'] ?? 'Mise à jour retenue/avance');

        return back()->with('success', 'Retenue / avance mise à jour.');
    }
}
