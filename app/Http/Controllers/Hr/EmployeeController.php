<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\HrDepartment;
use App\Services\Hr\EmployeeMatriculeService;
use App\Services\Hr\EmployeeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    use FiltersIndexTables;

    public function __construct(
        protected EmployeeService $employees,
        protected EmployeeMatriculeService $matricules,
    ) {}

    public function index(Request $request)
    {
        $query = Employee::query()->with(['department', 'currentContract']);

        $this->applyTableSearch($query, $request, [
            'matricule', 'first_name', 'last_name', 'cin', 'email', 'phone', 'job_title',
        ]);
        $this->applyTableFilter($query, $request, 'status', 'status');
        $this->applyTableFilter($query, $request, 'department_id', 'department_id');
        $this->applyTableFilter($query, $request, 'job_title', 'job_title');
        $this->applyTableSort($query, $request, [
            'matricule' => 'matricule',
            'last_name' => 'last_name',
            'hire_date' => 'hire_date',
            'created_at' => 'created_at',
        ], 'last_name', 'asc');

        return view('hr.employees.index', [
            'employees' => $this->paginateTable($query, $request),
            'departments' => HrDepartment::query()->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('hr.employees.create', $this->formData([
            'matricule' => $this->matricules->next(),
        ]));
    }

    public function store(Request $request)
    {
        $validated = $this->validatedPayload($request);
        if ($request->hasFile('photo')) {
            $validated['photo_path'] = $request->file('photo')->store('hr/photos', 'public');
        }

        $employee = $this->employees->create($validated);

        if ($request->filled('base_salary')) {
            $this->employees->addSalary($employee, [
                'effective_date' => $employee->hire_date,
                'base_salary' => $request->input('base_salary'),
                'negotiated_as' => $request->input('negotiated_as', 'brut'),
            ]);
        }

        return redirect()->route('hr.employees.show', $employee)
            ->with('success', 'Salarié créé. La date d\'entrée saisie est bien celle du dossier, indépendamment de la date de création dans Libromart.');
    }

    public function show(Employee $employee, Request $request)
    {
        $employee->load([
            'department', 'manager', 'user', 'contracts', 'schedules',
            'salaryRecords', 'compensationItems', 'payrollAdjustments',
            'leaveRequests.leaveType', 'leaveBalanceEntries', 'absences',
            'attendanceRecords' => fn ($q) => $q->latest('work_date')->limit(31),
            'events.user', 'exitRecord', 'payrollSlips.run', 'documents.currentVersion',
        ]);

        return view('hr.employees.show', [
            'employee' => $employee,
            'tab' => $request->input('tab', 'identite'),
            'departments' => HrDepartment::query()->orderBy('name')->get(),
            'managers' => Employee::query()->where('id', '!=', $employee->id)->orderBy('last_name')->get(),
            'leaveTypes' => \App\Models\LeaveType::query()->orderBy('name')->get(),
            'users' => \App\Models\User::query()->orderBy('name')->get(),
            'canSeeSalary' => $request->user()?->canHr(\App\Support\HrPermission::VIEW_SALARIES) ?? false,
        ]);
    }

    public function edit(Employee $employee)
    {
        return view('hr.employees.edit', $this->formData([
            'employee' => $employee,
        ]));
    }

    public function update(Request $request, Employee $employee)
    {
        $validated = $this->validatedPayload($request, $employee);
        if ($request->hasFile('photo')) {
            $validated['photo_path'] = $this->employees->storePhoto($employee, $request->file('photo'));
        }

        $this->employees->update($employee, $validated);

        return redirect()->route('hr.employees.show', $employee)->with('success', 'Fiche salarié mise à jour.');
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function formData(array $extra = []): array
    {
        return array_merge([
            'departments' => HrDepartment::query()->orderBy('name')->get(),
            'managers' => Employee::query()->orderBy('last_name')->get(),
            'users' => \App\Models\User::query()->orderBy('name')->get(),
        ], $extra);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, ?Employee $employee = null): array
    {
        $data = $request->validate([
            'last_name' => 'required|string|max:120',
            'first_name' => 'required|string|max:120',
            'birth_date' => 'nullable|date',
            'cin' => ['nullable', 'string', 'max:40', Rule::unique('employees', 'cin')->ignore($employee?->id)],
            'nationality' => 'nullable|string|max:80',
            'gender' => 'nullable|in:homme,femme,autre',
            'marital_status' => 'nullable|in:celibataire,marie,divorce,veuf',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:80',
            'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:120',
            'photo' => 'nullable|image|max:4096',
            'cnss_number' => 'nullable|string|max:40',
            'amo_number' => 'nullable|string|max:40',
            'rib' => 'nullable|string|max:40',
            'bank_name' => 'nullable|string|max:80',
            'hire_date' => 'required|date',
            'job_title' => 'nullable|string|max:120',
            'department_id' => 'nullable|exists:hr_departments,id',
            'manager_id' => 'nullable|exists:employees,id',
            'workplace' => 'nullable|string|max:120',
            'status' => 'nullable|in:actif,suspendu,sorti',
            'timeclock_external_id' => 'nullable|string|max:80',
            'user_id' => 'nullable|exists:users,id',
            'commission_eligible' => 'sometimes|boolean',
            'initial_leave_balance' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $data['commission_eligible'] = $request->boolean('commission_eligible');
        $data['status'] = $data['status'] ?? Employee::STATUS_ACTIF;

        return $data;
    }
}
