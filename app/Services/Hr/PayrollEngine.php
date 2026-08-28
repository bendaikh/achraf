<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\LeaveRequest;
use App\Models\PayrollAdjustment;
use App\Models\PayrollRuleSet;
use App\Models\SalaryRecord;
use Carbon\CarbonInterface;

class PayrollEngine
{
    /**
     * @return array<string, mixed>
     */
    public function simulate(Employee $employee, CarbonInterface $periodStart, ?PayrollRuleSet $rules = null): array
    {
        $periodEnd = $periodStart->copy()->endOfMonth();
        $rules ??= PayrollRuleSet::forDate($periodStart);

        if (! $rules) {
            throw new \RuntimeException('Aucun paramètre de paie n’est défini pour cette période. Créez une version dans Paramètres RH.');
        }

        $salary = $employee->salaryOn($periodStart);
        $base = (float) ($salary?->base_salary ?? 0);
        $negotiatedAs = $salary?->negotiated_as ?? SalaryRecord::AS_BRUT;

        $primes = 0.0;
        $indemnites = 0.0;
        foreach ($employee->compensationItems as $item) {
            if (! $item->appliesTo($periodStart, $periodEnd)) {
                continue;
            }
            if ($item->isPrime()) {
                $primes += (float) $item->amount;
            } else {
                $indemnites += (float) $item->amount;
            }
        }

        $monthlyHours = (float) $rules->rule('monthly_hours', 191);
        $hourly = $monthlyHours > 0 ? $base / $monthlyHours : 0;
        $overtimeMinutes = (int) $employee->attendanceRecords()
            ->whereBetween('work_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->sum('overtime_minutes');
        $overtimeAmount = round(($overtimeMinutes / 60) * $hourly * (float) $rules->rule('overtime_multiplier', 1.25), 2);

        $absenceDays = (float) EmployeeAbsence::query()
            ->where('employee_id', $employee->id)
            ->where('impacts_payroll', true)
            ->whereDate('start_date', '<=', $periodEnd)
            ->whereDate('end_date', '>=', $periodStart)
            ->sum('days');

        $unpaidLeaveDays = (float) LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereHas('leaveType', fn ($q) => $q->where('impacts_payroll', true))
            ->whereDate('start_date', '<=', $periodEnd)
            ->whereDate('end_date', '>=', $periodStart)
            ->sum('days');
        $absenceDays += $unpaidLeaveDays;

        $workingDays = 26;
        $absenceDeduction = $workingDays > 0 ? round(($base / $workingDays) * $absenceDays, 2) : 0;

        $retenues = (float) PayrollAdjustment::query()
            ->where('employee_id', $employee->id)
            ->where('period_year', $periodStart->year)
            ->where('period_month', $periodStart->month)
            ->where('type', PayrollAdjustment::TYPE_RETENUE)
            ->sum('amount');

        $avanceQuery = PayrollAdjustment::query()
            ->where('employee_id', $employee->id)
            ->where('period_year', $periodStart->year)
            ->where('period_month', $periodStart->month)
            ->where('type', PayrollAdjustment::TYPE_AVANCE);
        $avances = (float) ($avanceQuery->get()->sum(fn ($row) => (float) ($row->remaining_amount ?? $row->amount)));

        $regularisations = (float) PayrollAdjustment::query()
            ->where('employee_id', $employee->id)
            ->where('period_year', $periodStart->year)
            ->where('period_month', $periodStart->month)
            ->whereIn('type', [PayrollAdjustment::TYPE_REGULARISATION, PayrollAdjustment::TYPE_AUTRE])
            ->sum('amount');

        $additions = $primes + $indemnites + $overtimeAmount + $regularisations;

        if ($negotiatedAs === SalaryRecord::AS_NET) {
            $targetNet = max(0, $base + $additions - $absenceDeduction - $retenues - $avances);
            $computed = $this->solveFromNet($targetNet, $rules);
            $gross = $computed['gross'];
        } else {
            $gross = max(0, $base + $additions - $absenceDeduction);
            $computed = $this->fromGross($gross, $rules);
        }

        $netBeforeAdjust = $computed['net'];
        $net = round($netBeforeAdjust - $retenues - $avances, 2);
        if ($negotiatedAs === SalaryRecord::AS_NET) {
            $net = round($targetNet, 2);
        }

        return [
            'rule_set_id' => $rules->id,
            'rule_set_name' => $rules->name,
            'effective_from' => $rules->effective_from?->format('Y-m-d'),
            'negotiated_as' => $negotiatedAs,
            'base_salary' => round($base, 2),
            'primes' => round($primes, 2),
            'indemnites' => round($indemnites, 2),
            'overtime_minutes' => $overtimeMinutes,
            'overtime_amount' => $overtimeAmount,
            'absence_days' => $absenceDays,
            'absence_deduction' => $absenceDeduction,
            'retenues' => round($retenues, 2),
            'avances' => round($avances, 2),
            'regularisations' => round($regularisations, 2),
            'gross' => $computed['gross'],
            'employee_cnss' => $computed['employee_cnss'],
            'employee_amo' => $computed['employee_amo'],
            'employee_contributions' => $computed['employee_contributions'],
            'taxable' => $computed['taxable'],
            'income_tax' => $computed['income_tax'],
            'net' => $net,
            'employer_cnss' => $computed['employer_cnss'],
            'employer_amo' => $computed['employer_amo'],
            'employer_contributions' => $computed['employer_contributions'],
            'employer_cost' => round($computed['gross'] + $computed['employer_contributions'], 2),
        ];
    }

    /**
     * @return array<string, float>
     */
    public function fromGross(float $gross, PayrollRuleSet $rules): array
    {
        $employeeCnss = round($gross * ((float) $rules->rule('employee_cnss_rate', 0) / 100), 2);
        $employeeAmo = round($gross * ((float) $rules->rule('employee_amo_rate', 0) / 100), 2);
        $employerCnss = round($gross * ((float) $rules->rule('employer_cnss_rate', 0) / 100), 2);
        $employerAmo = round($gross * ((float) $rules->rule('employer_amo_rate', 0) / 100), 2);

        $expensesRate = (float) $rules->rule('professional_expenses_rate', 0);
        $expensesCap = (float) $rules->rule('professional_expenses_cap', 0);
        $expenses = round(min($gross * ($expensesRate / 100), $expensesCap), 2);

        $taxable = max(0, $gross - $employeeCnss - $employeeAmo - $expenses);
        $ir = $this->computeIr($taxable, $rules->rule('ir_brackets', []));
        $contributions = round($employeeCnss + $employeeAmo, 2);
        $net = round($gross - $contributions - $ir, 2);

        return [
            'gross' => round($gross, 2),
            'employee_cnss' => $employeeCnss,
            'employee_amo' => $employeeAmo,
            'employee_contributions' => $contributions,
            'taxable' => round($taxable, 2),
            'income_tax' => $ir,
            'net' => $net,
            'employer_cnss' => $employerCnss,
            'employer_amo' => $employerAmo,
            'employer_contributions' => round($employerCnss + $employerAmo, 2),
        ];
    }

    /**
     * @return array<string, float>
     */
    public function solveFromNet(float $targetNet, PayrollRuleSet $rules): array
    {
        $low = $targetNet;
        $high = max($targetNet * 2, $targetNet + 5000);

        for ($i = 0; $i < 40; $i++) {
            $mid = ($low + $high) / 2;
            $computed = $this->fromGross($mid, $rules);
            if ($computed['net'] < $targetNet) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        return $this->fromGross($high, $rules);
    }

    /**
     * @param  list<array{up_to: float|int|null, rate: float|int, deduction?: float|int}>  $brackets
     */
    public function computeIr(float $taxable, array $brackets): float
    {
        if ($brackets === []) {
            return 0.0;
        }

        foreach ($brackets as $bracket) {
            $upTo = $bracket['up_to'] ?? null;
            if ($upTo === null || $taxable <= (float) $upTo) {
                $rate = (float) ($bracket['rate'] ?? 0);
                $deduction = (float) ($bracket['deduction'] ?? 0);

                return max(0, round(($taxable * $rate / 100) - $deduction, 2));
            }
        }

        $last = $brackets[array_key_last($brackets)];

        return max(0, round(($taxable * ((float) ($last['rate'] ?? 0)) / 100) - (float) ($last['deduction'] ?? 0), 2));
    }
}
