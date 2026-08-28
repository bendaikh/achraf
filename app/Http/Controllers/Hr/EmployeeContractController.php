<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Services\Hr\EmployeeService;
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

    public function store(Request $request, Employee $employee, EmployeeService $service)
    {
        $validated = $request->validate([
            'type' => 'required|in:cdi,cdd,stage,autre',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'job_title' => 'nullable|string|max:120',
            'salary' => 'nullable|numeric|min:0',
            'trial_start_date' => 'nullable|date',
            'trial_end_date' => 'nullable|date',
            'workplace' => 'nullable|string|max:120',
            'status' => 'nullable|in:en_cours,termine,renouvele',
            'notes' => 'nullable|string',
            'renew' => 'sometimes|boolean',
        ]);

        $renew = $request->boolean('renew');
        $validated['status'] = $validated['status'] ?? EmployeeContract::STATUS_EN_COURS;
        unset($validated['renew']);

        $service->addContract($employee, $validated, $renew);

        return back()->with('success', 'Contrat enregistré. L\'historique des contrats précédents est conservé.');
    }

    public function update(Request $request, Employee $employee, EmployeeContract $contract, EmployeeService $service)
    {
        abort_unless($contract->employee_id === $employee->id, 404);

        $validated = $request->validate([
            'type' => 'required|in:cdi,cdd,stage,autre',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'job_title' => 'nullable|string|max:120',
            'salary' => 'nullable|numeric|min:0',
            'trial_start_date' => 'nullable|date',
            'trial_end_date' => 'nullable|date',
            'workplace' => 'nullable|string|max:120',
            'status' => 'required|in:en_cours,termine,renouvele',
            'notes' => 'nullable|string',
        ]);

        $before = $contract->getAttributes();
        $contract->update($validated);
        app(\App\Services\Hr\HrAuditService::class)->logChanges($contract, $before, $contract->getChanges());

        return back()->with('success', 'Contrat mis à jour.');
    }
}
