<?php

namespace App\Services;

use App\Models\FinancialDeclaration;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class FinancialDeclarationService
{
    public function __construct(
        private FinancialManagementService $financial
    ) {}

    public function findOrCreateForPeriod(Carbon $dateFrom, Carbon $dateTo): FinancialDeclaration
    {
        return FinancialDeclaration::query()->firstOrCreate(
            [
                'period_from' => $dateFrom->toDateString(),
                'period_to' => $dateTo->toDateString(),
            ],
            [
                'status' => FinancialDeclaration::STATUS_OUVERTE,
            ]
        );
    }

    /**
     * @return array{declaration: FinancialDeclaration, controls: list<array{code: string, label: string, severity: string, count: int}>}
     */
    public function runControls(Carbon $dateFrom, Carbon $dateTo): array
    {
        $overview = $this->financial->getOverview($dateFrom, $dateTo);
        $vat = $overview['vat_details'];
        $health = $this->financial->getPeriodHealth($dateFrom, $dateTo);
        $vatControls = $this->financial->getVatControls($dateFrom, $dateTo);

        $controls = $vatControls;
        foreach ($health['anomalies'] as $anomaly) {
            $controls[] = [
                'code' => 'period_anomaly',
                'label' => $anomaly,
                'severity' => 'warning',
                'count' => 1,
            ];
        }

        $declaration = $this->findOrCreateForPeriod($dateFrom, $dateTo);

        if ($declaration->isClosed()) {
            return compact('declaration', 'controls');
        }

        $declaration->update([
            'status' => FinancialDeclaration::STATUS_CONTROLEE,
            'vat_collected' => $vat['collected'],
            'vat_deductible' => $vat['deductible'],
            'vat_net' => $vat['net'],
            'revenue' => $overview['revenue'],
            'anomalies' => array_column($controls, 'label'),
            'control_report' => $controls,
            'controlled_at' => now(),
            'controlled_by' => Auth::id(),
        ]);

        return [
            'declaration' => $declaration->fresh(),
            'controls' => $controls,
        ];
    }

    public function validate(Carbon $dateFrom, Carbon $dateTo): FinancialDeclaration
    {
        $result = $this->runControls($dateFrom, $dateTo);
        $declaration = $result['declaration'];

        if ($declaration->isClosed()) {
            throw new \RuntimeException('La période est déjà clôturée.');
        }

        $declaration->update([
            'status' => FinancialDeclaration::STATUS_VALIDEE,
            'validated_at' => now(),
            'validated_by' => Auth::id(),
        ]);

        return $declaration->fresh();
    }

    public function close(Carbon $dateFrom, Carbon $dateTo): FinancialDeclaration
    {
        $declaration = $this->findOrCreateForPeriod($dateFrom, $dateTo);

        if ($declaration->status === FinancialDeclaration::STATUS_OUVERTE) {
            $this->runControls($dateFrom, $dateTo);
            $declaration->refresh();
        }

        $declaration->update([
            'status' => FinancialDeclaration::STATUS_CLOTUREE,
            'closed_at' => now(),
            'closed_by' => Auth::id(),
            'reopen_reason' => null,
        ]);

        return $declaration->fresh();
    }

    public function reopen(Carbon $dateFrom, Carbon $dateTo, string $reason): FinancialDeclaration
    {
        $declaration = $this->findOrCreateForPeriod($dateFrom, $dateTo);

        if (! $declaration->isClosed() && $declaration->status !== FinancialDeclaration::STATUS_VALIDEE) {
            throw new \RuntimeException('Seule une période validée ou clôturée peut être réouverte.');
        }

        $declaration->update([
            'status' => FinancialDeclaration::STATUS_OUVERTE,
            'reopen_reason' => $reason,
            'reopened_at' => now(),
            'reopened_by' => Auth::id(),
            'closed_at' => null,
            'closed_by' => null,
        ]);

        return $declaration->fresh();
    }

    /**
     * Prepare TVA declaration payload for export / display.
     *
     * @return array<string, mixed>
     */
    public function prepareVatDeclaration(Carbon $dateFrom, Carbon $dateTo): array
    {
        $overview = $this->financial->getOverview($dateFrom, $dateTo);
        $vat = $overview['vat_details'];
        $controls = $this->financial->getVatControls($dateFrom, $dateTo);

        return [
            'period_from' => $dateFrom->toDateString(),
            'period_to' => $dateTo->toDateString(),
            'base_ht' => $vat['base_ht'],
            'vat_collected' => $vat['collected'],
            'vat_deductible' => $vat['deductible'],
            'vat_net' => $vat['net'],
            'rates' => $vat['rates'],
            'collected_invoices' => $vat['collected_invoices'],
            'collected_pos' => $vat['collected_pos'],
            'collected_credit_notes' => $vat['collected_credit_notes'],
            'deductible_purchases' => $vat['deductible_purchases'],
            'deductible_expenses' => $vat['deductible_expenses'],
            'deductible_credit_notes' => $vat['deductible_credit_notes'],
            'revenue' => $overview['revenue'],
            'controls' => $controls,
            'missing_pieces' => count(array_filter($controls, fn ($c) => ($c['code'] ?? '') === 'missing_invoice')),
        ];
    }
}
