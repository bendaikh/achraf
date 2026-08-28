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

    protected $fillable = [
        'employee_id',
        'type',
        'amount',
        'remaining_amount',
        'period_year',
        'period_month',
        'reason',
        'payment_method',
        'reference',
        'recovered_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'period_year' => 'integer',
        'period_month' => 'integer',
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
}
