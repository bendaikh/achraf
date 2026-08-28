<?php

namespace App\Services\Hr;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\LeaveRequest;
use App\Models\ManagedDocument;
use App\Models\PayrollSlip;
use App\Models\Setting;
use Carbon\Carbon;

class HrDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function stats(array $filters): array
    {
        $month = (int) ($filters['month'] ?? now()->month);
        $year = (int) ($filters['year'] ?? now()->year);
        $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();
        $today = now()->toDateString();

        $employees = Employee::query()
            ->when($filters['employee_id'] ?? null, fn ($q, $id) => $q->where('id', $id))
            ->when($filters['department_id'] ?? null, fn ($q, $id) => $q->where('department_id', $id))
            ->when($filters['job_title'] ?? null, fn ($q, $title) => $q->where('job_title', $title))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status));

        $contractDays = (int) Setting::get('hr.alert.contract_expiry_days', 30);
        $documentDays = (int) Setting::get('hr.alert.document_expiry_days', 30);
        $trialDays = (int) Setting::get('hr.alert.trial_end_days', 15);

        $employeeIds = (clone $employees)->pluck('id');

        $payroll = PayrollSlip::query()
            ->whereHas('run', fn ($q) => $q->where('period_year', $year)->where('period_month', $month))
            ->when($employeeIds->isNotEmpty(), fn ($q) => $q->whereIn('employee_id', $employeeIds));

        return [
            'total' => (clone $employees)->count(),
            'actifs' => (clone $employees)->where('status', Employee::STATUS_ACTIF)->count(),
            'sortis' => (clone $employees)->where('status', Employee::STATUS_SORTI)->count(),
            'embauches' => (clone $employees)->whereBetween('hire_date', [$periodStart, $periodEnd])->count(),
            'contrats_echeance' => EmployeeContract::query()
                ->whereIn('employee_id', $employeeIds)
                ->where('status', EmployeeContract::STATUS_EN_COURS)
                ->whereNotNull('end_date')
                ->whereBetween('end_date', [now()->toDateString(), now()->addDays($contractDays)->toDateString()])
                ->count(),
            'presents_today' => AttendanceRecord::query()
                ->whereIn('employee_id', $employeeIds)
                ->whereDate('work_date', $today)
                ->whereIn('status', [AttendanceRecord::STATUS_PRESENT, AttendanceRecord::STATUS_LATE])
                ->count(),
            'absents_today' => AttendanceRecord::query()
                ->whereIn('employee_id', $employeeIds)
                ->whereDate('work_date', $today)
                ->where('status', AttendanceRecord::STATUS_ABSENT)
                ->count(),
            'conges_today' => AttendanceRecord::query()
                ->whereIn('employee_id', $employeeIds)
                ->whereDate('work_date', $today)
                ->where('status', AttendanceRecord::STATUS_LEAVE)
                ->count()
                ?: LeaveRequest::query()
                    ->whereIn('employee_id', $employeeIds)
                    ->where('status', LeaveRequest::STATUS_APPROVED)
                    ->whereDate('start_date', '<=', $today)
                    ->whereDate('end_date', '>=', $today)
                    ->count(),
            'retards' => AttendanceRecord::query()
                ->whereIn('employee_id', $employeeIds)
                ->whereBetween('work_date', [$periodStart, $periodEnd])
                ->where(function ($q) {
                    $q->where('status', AttendanceRecord::STATUS_LATE)->orWhere('late_minutes', '>', 0);
                })
                ->count(),
            'conges_a_valider' => LeaveRequest::query()
                ->whereIn('employee_id', $employeeIds)
                ->where('status', LeaveRequest::STATUS_PENDING)
                ->count(),
            'documents_expiration' => ManagedDocument::query()
                ->where('section_key', 'hr-employees')
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [now()->toDateString(), now()->addDays($documentDays)->toDateString()])
                ->count(),
            'masse_salariale' => (float) (clone $payroll)->sum('net'),
            'primes_mois' => (float) (clone $payroll)->sum('primes') + (float) (clone $payroll)->sum('indemnites'),
            'cout_employeur' => (float) (clone $payroll)->sum('employer_cost'),
            'essais_fin' => EmployeeContract::query()
                ->whereIn('employee_id', $employeeIds)
                ->where('status', EmployeeContract::STATUS_EN_COURS)
                ->whereNotNull('trial_end_date')
                ->whereBetween('trial_end_date', [now()->toDateString(), now()->addDays($trialDays)->toDateString()])
                ->count(),
            'period' => $periodStart,
            'alerts' => $this->alerts($employeeIds, $contractDays, $trialDays, $documentDays),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>  $employeeIds
     * @return list<array{type: string, label: string, count: int, url: string}>
     */
    public function alerts($employeeIds, int $contractDays, int $trialDays, int $documentDays): array
    {
        return array_values(array_filter([
            [
                'type' => 'contract',
                'label' => 'Contrats arrivant à échéance',
                'count' => EmployeeContract::query()
                    ->whereIn('employee_id', $employeeIds)
                    ->where('status', EmployeeContract::STATUS_EN_COURS)
                    ->whereNotNull('end_date')
                    ->whereBetween('end_date', [now()->toDateString(), now()->addDays($contractDays)->toDateString()])
                    ->count(),
                'url' => route('hr.contracts.index', ['expiring' => 1]),
            ],
            [
                'type' => 'trial',
                'label' => 'Fins de période d’essai',
                'count' => EmployeeContract::query()
                    ->whereIn('employee_id', $employeeIds)
                    ->where('status', EmployeeContract::STATUS_EN_COURS)
                    ->whereNotNull('trial_end_date')
                    ->whereBetween('trial_end_date', [now()->toDateString(), now()->addDays($trialDays)->toDateString()])
                    ->count(),
                'url' => route('hr.contracts.index', ['trial' => 1]),
            ],
            [
                'type' => 'leave',
                'label' => 'Congés à valider',
                'count' => LeaveRequest::query()
                    ->whereIn('employee_id', $employeeIds)
                    ->where('status', LeaveRequest::STATUS_PENDING)
                    ->count(),
                'url' => route('hr.leaves.index', ['status' => LeaveRequest::STATUS_PENDING]),
            ],
            [
                'type' => 'document',
                'label' => 'Documents arrivant à expiration',
                'count' => ManagedDocument::query()
                    ->where('section_key', 'hr-employees')
                    ->whereNotNull('expires_at')
                    ->whereBetween('expires_at', [now()->toDateString(), now()->addDays($documentDays)->toDateString()])
                    ->count(),
                'url' => route('hr.documents.index', ['expiring' => 1]),
            ],
        ], fn (array $alert) => $alert['count'] > 0));
    }
}
