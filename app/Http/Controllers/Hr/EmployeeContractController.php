<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\HrAuditLog;
use App\Models\PayrollRun;
use App\Services\Hr\EmployeeService;
use App\Services\Hr\HrAuditService;
use Illuminate\Http\Request;

class EmployeeContractController extends Controller
{
    use FiltersIndexTables;

    public function index(Request $request)
    {
        $query = EmployeeContract::query()->with('employee.department');

        $this->applyTableSearch($query, $request, ['employee.last_name', 'employee.first_name', 'employee.matricule', 'job_title']);
        $this->applyTableFilter($query, $request, 'status', 'status');
        $this->applyTableFilter($query, $request, 'type', 'type');

        if ($request->boolean('expiring')) {
            $days = (int) \App\Models\Setting::get('hr.alert.contract_expiry_days', 30);
            $query->where('status', EmployeeContract::STATUS_EN_COURS)
                ->whereNotNull('end_date')
                ->whereBetween('end_date', [now()->toDateString(), now()->addDays($days)->toDateString()]);
        }

        if ($request->boolean('trial')) {
            $days = (int) \App\Models\Setting::get('hr.alert.trial_end_days', 15);
            $query->where('status', EmployeeContract::STATUS_EN_COURS)
                ->whereNotNull('trial_end_date')
                ->whereBetween('trial_end_date', [now()->toDateString(), now()->addDays($days)->toDateString()]);
        }

        $this->applyTableSort($query, $request, [
            'start_date' => 'start_date',
            'end_date' => 'end_date',
        ], 'start_date', 'desc');

        return view('hr.contracts.index', [
            'contracts' => $this->paginateTable($query, $request),
        ]);
    }

    public function show(EmployeeContract $contract)
    {
        $contract->load(['employee.department', 'previousContract', 'renewals']);
        $audits = HrAuditLog::query()
            ->where('auditable_type', $contract->getMorphClass())
            ->where('auditable_id', $contract->id)
            ->with('user')
            ->latest()
            ->limit(50)
            ->get();

        return view('hr.contracts.show', [
            'contract' => $contract,
            'audits' => $audits,
        ]);
    }

    public function edit(EmployeeContract $contract)
    {
        $contract->load('employee.department');

        return view('hr.contracts.edit', [
            'contract' => $contract,
            'mode' => 'edit',
        ]);
    }

    public function store(Request $request, Employee $employee, EmployeeService $service)
    {
        $validated = $this->validatedContract($request);
        $renew = $request->boolean('renew');
        $isAmendment = $request->boolean('is_amendment');
        $validated['status'] = $validated['status'] ?? EmployeeContract::STATUS_EN_COURS;
        $validated['is_amendment'] = $isAmendment;

        if ($isAmendment) {
            $service->addContract($employee, $validated, renew: true, userId: $request->user()?->id);
            $message = 'Avenant enregistré. L’ancien contrat reste dans l’historique.';
        } else {
            $service->addContract($employee, $validated, $renew, $request->user()?->id);
            $message = 'Contrat enregistré. L’historique des contrats précédents est conservé.';
        }

        return back()->with('success', $message);
    }

    public function update(Request $request, EmployeeContract $contract, HrAuditService $audit)
    {
        $employee = $contract->employee;
        abort_unless($employee, 404);

        if ($this->hasValidatedPayrollAfter($contract)) {
            return back()->with('error', 'Impossible de modifier ce contrat : une paie déjà validée couvre cette période. Créez un avenant avec date d’effet.');
        }

        $validated = $this->validatedContract($request, requireStatus: true);
        $before = $contract->getAttributes();
        $contract->update($validated);
        $audit->logChanges($contract, $before, $contract->getChanges(), $request->input('change_reason') ?: 'Correction de saisie');

        return redirect()
            ->route('hr.contracts.show', $contract)
            ->with('success', 'Contrat mis à jour. Les modifications sont tracées.');
    }

    public function amend(Request $request, EmployeeContract $contract, EmployeeService $service)
    {
        abort_unless($contract->employee_id, 404);

        $validated = $this->validatedContract($request);
        $validated['status'] = EmployeeContract::STATUS_EN_COURS;
        $validated['is_amendment'] = true;
        $validated['notes'] = trim(($validated['notes'] ?? '')."\nAvenant à effet du ".$validated['start_date']);

        $service->addContract($contract->employee, $validated, renew: true, userId: $request->user()?->id);

        return redirect()
            ->route('hr.contracts.index')
            ->with('success', 'Avenant / nouvelle version créé(e) avec date d’effet. L’ancien historique est conservé.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedContract(Request $request, bool $requireStatus = false): array
    {
        return $request->validate([
            'type' => 'required|in:cdi,cdd,stage,autre',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'job_title' => 'nullable|string|max:120',
            'workplace' => 'nullable|string|max:120',
            'department_name' => 'nullable|string|max:120',
            'salary' => 'nullable|numeric|min:0',
            'trial_start_date' => 'nullable|date',
            'trial_end_date' => 'nullable|date',
            'status' => ($requireStatus ? 'required' : 'nullable').'|in:en_cours,termine,renouvele',
            'notes' => 'nullable|string',
            'change_reason' => 'nullable|string|max:500',
            'is_amendment' => 'sometimes|boolean',
            'renew' => 'sometimes|boolean',
        ]);
    }

    private function hasValidatedPayrollAfter(EmployeeContract $contract): bool
    {
        $start = $contract->start_date?->copy()?->startOfMonth();
        if (! $start) {
            return false;
        }

        return PayrollRun::query()
            ->whereIn('status', [
                PayrollRun::STATUS_VALIDEE,
                PayrollRun::STATUS_PAYEE,
            ])
            ->where(function ($q) use ($start) {
                $q->where('period_year', '>', $start->year)
                    ->orWhere(function ($q2) use ($start) {
                        $q2->where('period_year', $start->year)
                            ->where('period_month', '>=', $start->month);
                    });
            })
            ->whereHas('slips', fn ($q) => $q->where('employee_id', $contract->employee_id))
            ->exists();
    }
}
