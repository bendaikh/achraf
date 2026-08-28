<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\EmployeeExit;
use App\Models\SalaryRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EmployeeService
{
    public function __construct(
        protected EmployeeMatriculeService $matricules,
        protected LeaveBalanceService $leaveBalances,
        protected AttendanceService $attendance,
        protected HrTimelineService $timeline,
        protected HrAuditService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?int $userId = null): Employee
    {
        return DB::transaction(function () use ($data, $userId) {
            $data['matricule'] = $data['matricule'] ?? $this->matricules->next();
            $data['commission_eligible'] = (bool) ($data['commission_eligible'] ?? false);
            $data['initial_leave_balance'] = $data['initial_leave_balance'] ?? 0;
            $employee = Employee::create($data);

            $this->attendance->seedDefaultSchedule($employee);
            $this->leaveBalances->ensureInitialBalance($employee, $userId);
            $this->timeline->record($employee, 'hire', 'Embauche', $employee->hire_date, 'Date réelle d\'entrée dans l\'entreprise', $employee, $userId);

            return $employee;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Employee $employee, array $data, ?int $userId = null): Employee
    {
        $oldJob = $employee->job_title;
        $before = $employee->getAttributes();
        $employee->update($data);
        $this->audit->logChanges($employee, $before, $employee->getChanges(), null, $userId);

        if ($oldJob && isset($data['job_title']) && $oldJob !== $data['job_title']) {
            $this->timeline->record($employee, 'job_change', 'Nouvelle fonction', now(), $oldJob.' → '.$data['job_title'], $employee, $userId);
        }

        return $employee->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addContract(Employee $employee, array $data, bool $renew = false, ?int $userId = null): EmployeeContract
    {
        return DB::transaction(function () use ($employee, $data, $renew, $userId) {
            $current = $employee->contracts()->where('status', EmployeeContract::STATUS_EN_COURS)->first();
            if ($current && $renew) {
                $current->update(['status' => EmployeeContract::STATUS_RENOUVELE]);
                $data['previous_contract_id'] = $current->id;
            }

            $contract = $employee->contracts()->create($data);
            $this->timeline->record(
                $employee,
                'contract',
                'Début contrat '.$contract->typeLabel(),
                $contract->start_date,
                $contract->job_title,
                $contract,
                $userId
            );

            return $contract;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addSalary(Employee $employee, array $data, ?int $userId = null): SalaryRecord
    {
        $record = $employee->salaryRecords()->create($data);
        $this->timeline->record(
            $employee,
            'salary',
            'Modification salaire',
            $record->effective_date,
            number_format((float) $record->base_salary, 2, ',', ' ').' MAD ('.$record->negotiated_as.')',
            $record,
            $userId
        );
        $this->audit->log($record, 'create', 'base_salary', null, $record->base_salary, null, $userId);

        return $record;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function exit(Employee $employee, array $data, ?int $userId = null): EmployeeExit
    {
        return DB::transaction(function () use ($employee, $data, $userId) {
            $exit = $employee->exitRecord()->create($data);
            $employee->update(['status' => Employee::STATUS_SORTI]);

            $employee->contracts()
                ->where('status', EmployeeContract::STATUS_EN_COURS)
                ->update([
                    'status' => EmployeeContract::STATUS_TERMINE,
                    'end_date' => $data['exit_date'] ?? now(),
                ]);

            $this->timeline->record(
                $employee,
                'exit',
                'Sortie du salarié',
                $exit->exit_date,
                $exit->reason,
                $exit,
                $userId
            );

            return $exit;
        });
    }

    public function storePhoto(Employee $employee, \Illuminate\Http\UploadedFile $file): string
    {
        if ($employee->photo_path) {
            Storage::disk('public')->delete($employee->photo_path);
        }

        return $file->store('hr/photos', 'public');
    }
}
