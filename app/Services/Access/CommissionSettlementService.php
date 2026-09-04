<?php

namespace App\Services\Access;

use App\Models\Collaborator;
use App\Models\Commission;
use App\Models\FreelancePayout;
use App\Models\PayrollAdjustment;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CommissionSettlementService
{
    /**
     * Link validated commissions of a salarié to a payroll adjustment (manual control).
     *
     * @param  list<int>  $commissionIds
     */
    public function linkToPayroll(array $commissionIds, int $periodYear, int $periodMonth, ?string $label = null): PayrollAdjustment
    {
        return DB::transaction(function () use ($commissionIds, $periodYear, $periodMonth, $label) {
            $rows = Commission::query()
                ->with('collaborator.employee')
                ->whereIn('id', $commissionIds)
                ->where('status', Commission::STATUS_VALIDEE)
                ->whereNull('payroll_linked_at')
                ->get();

            if ($rows->isEmpty()) {
                throw new \RuntimeException('Aucune commission validée à lier.');
            }

            $employeeIds = $rows->map(fn (Commission $c) => $c->collaborator?->employee_id)->filter()->unique();
            if ($employeeIds->count() !== 1) {
                throw new \RuntimeException('Sélectionnez des commissions d\'un seul salarié RH.');
            }

            $employeeId = (int) $employeeIds->first();
            $amount = round((float) $rows->sum('amount'), 2);

            $adjustment = PayrollAdjustment::query()->create([
                'employee_id' => $employeeId,
                'period_year' => $periodYear,
                'period_month' => $periodMonth,
                'type' => PayrollAdjustment::TYPE_AUTRE,
                'amount' => $amount,
                'remaining_amount' => 0,
                'reason' => $label ?: 'Commissions commerciales (liaison contrôlée)',
                'reference' => 'COMM-'.now()->format('YmdHis'),
            ]);

            foreach ($rows as $row) {
                $row->update([
                    'payroll_linked_at' => now(),
                    'payroll_adjustment_id' => $adjustment->id,
                ]);
            }

            ActivityLogger::log(
                'modification_commission',
                "Liaison paie {$amount} DH — {$periodMonth}/{$periodYear}",
                $adjustment,
            );

            return $adjustment;
        });
    }

    /**
     * Pay freelances without entering salarié payroll.
     *
     * @param  list<int>  $commissionIds
     * @param  array{date?: mixed, payment_method?: mixed, payment_reference?: mixed, notes?: mixed}  $payment
     */
    public function createFreelancePayout(array $commissionIds, array $payment): FreelancePayout
    {
        return DB::transaction(function () use ($commissionIds, $payment) {
            $rows = Commission::query()
                ->with('collaborator')
                ->whereIn('id', $commissionIds)
                ->whereIn('status', [Commission::STATUS_VALIDEE, Commission::STATUS_ACQUISE])
                ->get();

            if ($rows->isEmpty()) {
                throw new \RuntimeException('Aucune commission à régler.');
            }

            $collabIds = $rows->pluck('collaborator_id')->unique();
            if ($collabIds->count() !== 1) {
                throw new \RuntimeException('Sélectionnez les commissions d\'un seul freelance.');
            }

            /** @var Collaborator $collaborator */
            $collaborator = $rows->first()->collaborator;
            if ($collaborator->isSalarie()) {
                throw new \RuntimeException('Utilisez la liaison paie pour un salarié, pas le règlement freelance.');
            }

            $amount = round((float) $rows->sum('amount'), 2);

            $payout = FreelancePayout::query()->create([
                'collaborator_id' => $collaborator->id,
                'amount_due' => $amount,
                'amount_validated' => $amount,
                'amount_paid' => $amount,
                'paid_at' => $payment['date'] ?? now()->toDateString(),
                'payment_method' => $payment['payment_method'] ?? null,
                'payment_reference' => $payment['payment_reference'] ?? null,
                'notes' => $payment['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $payout->commissions()->attach($rows->pluck('id')->all());

            app(CommissionService::class)->markPaid($rows->pluck('id')->all(), $payment);

            ActivityLogger::log(
                'paiement',
                "Règlement freelance {$collaborator->fullName()} — {$amount} DH",
                $payout,
            );

            return $payout;
        });
    }
}
