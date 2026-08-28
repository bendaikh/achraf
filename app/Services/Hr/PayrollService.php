<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPayment;
use App\Models\PayrollRun;
use App\Models\PayrollSlip;
use App\Services\FinancialMovementService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    public function __construct(
        protected PayrollEngine $engine,
        protected FinancialMovementService $movements,
        protected HrTimelineService $timeline,
        protected HrAuditService $audit,
    ) {}

    public function prepare(int $year, int $month, ?int $userId = null): PayrollRun
    {
        $run = PayrollRun::query()->firstOrCreate(
            ['period_year' => $year, 'period_month' => $month],
            ['status' => PayrollRun::STATUS_BROUILLON]
        );

        if ($run->canRecalculate()) {
            $this->calculate($run, $userId);
        }

        return $run->fresh('slips');
    }

    public function calculate(PayrollRun $run, ?int $userId = null): PayrollRun
    {
        if (! $run->canRecalculate()) {
            throw new \RuntimeException('Cette paie est verrouillée. Toute correction après validation doit être tracée via un avenant.');
        }

        $periodStart = Carbon::create($run->period_year, $run->period_month, 1)->startOfDay();

        $employees = Employee::query()
            ->with(['compensationItems', 'salaryRecords'])
            ->where(function ($q) use ($periodStart) {
                $q->where('status', '!=', Employee::STATUS_SORTI)
                    ->orWhereHas('exitRecord', fn ($e) => $e->whereDate('exit_date', '>=', $periodStart));
            })
            ->get();

        DB::transaction(function () use ($run, $employees, $periodStart, $userId) {
            $run->slips()->delete();

            foreach ($employees as $employee) {
                $sim = $this->engine->simulate($employee, $periodStart);
                PayrollSlip::create([
                    'payroll_run_id' => $run->id,
                    'employee_id' => $employee->id,
                    'base_salary' => $sim['base_salary'],
                    'gross' => $sim['gross'],
                    'net' => $sim['net'],
                    'primes' => $sim['primes'],
                    'indemnites' => $sim['indemnites'],
                    'overtime_amount' => $sim['overtime_amount'],
                    'absence_deduction' => $sim['absence_deduction'],
                    'retenues' => $sim['retenues'],
                    'avances' => $sim['avances'],
                    'employee_contributions' => $sim['employee_contributions'],
                    'income_tax' => $sim['income_tax'],
                    'employer_contributions' => $sim['employer_contributions'],
                    'employer_cost' => $sim['employer_cost'],
                    'breakdown' => $sim,
                ]);
            }

            $run->update([
                'status' => PayrollRun::STATUS_CALCULEE,
                'calculated_at' => now(),
                'calculated_by' => $userId ?? auth()->id(),
            ]);
        });

        return $run->fresh('slips');
    }

    public function transition(PayrollRun $run, string $status, ?int $userId = null): PayrollRun
    {
        $allowed = [
            PayrollRun::STATUS_CALCULEE => [PayrollRun::STATUS_BROUILLON, PayrollRun::STATUS_CALCULEE],
            PayrollRun::STATUS_VERIFIEE => [PayrollRun::STATUS_CALCULEE],
            PayrollRun::STATUS_VALIDEE => [PayrollRun::STATUS_VERIFIEE],
            PayrollRun::STATUS_PAYEE => [PayrollRun::STATUS_VALIDEE],
        ];

        if (! isset($allowed[$status]) || ! in_array($run->status, $allowed[$status], true)) {
            throw new \RuntimeException('Transition de statut de paie non autorisée.');
        }

        $payload = ['status' => $status];
        $userId ??= auth()->id();

        if ($status === PayrollRun::STATUS_VERIFIEE) {
            $payload['verified_at'] = now();
            $payload['verified_by'] = $userId;
        }
        if ($status === PayrollRun::STATUS_VALIDEE) {
            $payload['validated_at'] = now();
            $payload['validated_by'] = $userId;
        }

        $this->audit->log($run, 'status', 'status', $run->status, $status, null, $userId);
        $run->update($payload);

        return $run->fresh();
    }

    public function paySlip(PayrollSlip $slip, array $payment, ?int $userId = null): PayrollPayment
    {
        $run = $slip->run;
        if ($run->status !== PayrollRun::STATUS_VALIDEE && $run->status !== PayrollRun::STATUS_PAYEE) {
            throw new \RuntimeException('La paie doit être validée avant le paiement.');
        }

        $record = PayrollPayment::create([
            'payroll_slip_id' => $slip->id,
            'paid_at' => $payment['paid_at'],
            'amount' => $payment['amount'] ?? $slip->net,
            'method' => $payment['method'] ?? 'virement',
            'account' => $payment['account'] ?? 'banque',
            'reference' => $payment['reference'] ?? null,
            'proof_path' => $payment['proof_path'] ?? null,
            'notes' => $payment['notes'] ?? null,
        ]);

        $this->movements->syncFromPayrollPayment($record);

        PayrollAdjustment::query()
            ->where('employee_id', $slip->employee_id)
            ->where('period_year', $run->period_year)
            ->where('period_month', $run->period_month)
            ->where('type', PayrollAdjustment::TYPE_AVANCE)
            ->whereNull('recovered_at')
            ->update([
                'remaining_amount' => 0,
                'recovered_at' => now(),
            ]);

        $this->timeline->record(
            $slip->employee,
            'payroll_paid',
            'Salaire payé — '.$run->periodLabel(),
            Carbon::parse($record->paid_at),
            sprintf('Net %s MAD — %s', number_format((float) $record->amount, 2, ',', ' '), $record->method),
            $record,
            $userId
        );

        $unpaid = $run->slips()->whereDoesntHave('payments')->exists();
        if (! $unpaid) {
            $run->update([
                'status' => PayrollRun::STATUS_PAYEE,
                'paid_at' => now(),
                'paid_by' => $userId ?? auth()->id(),
            ]);
        }

        return $record;
    }
}
