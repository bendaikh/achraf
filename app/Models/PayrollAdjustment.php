<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollAdjustment extends Model
{
    public const TYPE_AVANCE = 'avance';

    public const TYPE_RETENUE = 'retenue';

    public const TYPE_REGULARISATION = 'regularisation';

    public const TYPE_AUTRE = 'autre';

    public const TYPES = [
        self::TYPE_AVANCE => 'Avance sur salaire',
        self::TYPE_RETENUE => 'Retenue',
        self::TYPE_REGULARISATION => 'Régularisation',
        self::TYPE_AUTRE => 'Autre ajustement',
    ];

    public const STATUS_ACTIF = 'actif';

    public const STATUS_SUSPENDU = 'suspendu';

    public const STATUS_TERMINE = 'termine';

    public const STATUSES = [
        self::STATUS_ACTIF => 'Actif',
        self::STATUS_SUSPENDU => 'Suspendu',
        self::STATUS_TERMINE => 'Terminé',
    ];

    protected $fillable = [
        'employee_id',
        'type',
        'amount',
        'monthly_amount',
        'remaining_amount',
        'period_year',
        'period_month',
        'start_date',
        'end_date',
        'status',
        'reason',
        'payment_method',
        'reference',
        'recovered_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'monthly_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'period_year' => 'integer',
        'period_month' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'recovered_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status ?? self::STATUS_ACTIF] ?? ($this->status ?? 'Actif');
    }

    public function isInstallment(): bool
    {
        return $this->monthly_amount !== null && (float) $this->monthly_amount > 0;
    }

    public function recoveredTotal(): float
    {
        return max(0, round((float) $this->amount - (float) ($this->remaining_amount ?? $this->amount), 2));
    }

    public function installmentForPeriod(int $year, int $month): float
    {
        if (($this->status ?? self::STATUS_ACTIF) !== self::STATUS_ACTIF) {
            return 0.0;
        }

        $remaining = (float) ($this->remaining_amount ?? $this->amount);
        if ($remaining <= 0) {
            return 0.0;
        }

        $periodStart = \Carbon\Carbon::create($year, $month, 1)->startOfDay();
        $periodEnd = $periodStart->copy()->endOfMonth();

        $start = $this->start_date
            ?? \Carbon\Carbon::create($this->period_year, $this->period_month, 1)->startOfDay();

        if ($start->gt($periodEnd)) {
            return 0.0;
        }

        if ($this->end_date && $this->end_date->lt($periodStart)) {
            return 0.0;
        }

        if (! $this->isInstallment()) {
            // One-shot deduction in the start period only.
            if ((int) $start->year === $year && (int) $start->month === $month) {
                return round($remaining, 2);
            }

            return 0.0;
        }

        return round(min((float) $this->monthly_amount, $remaining), 2);
    }
}
