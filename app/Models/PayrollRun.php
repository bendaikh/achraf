<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    public const STATUS_BROUILLON = 'brouillon';

    public const STATUS_CALCULEE = 'calculee';

    public const STATUS_VERIFIEE = 'verifiee';

    public const STATUS_VALIDEE = 'validee';

    public const STATUS_PAYEE = 'payee';

    public const STATUSES = [
        self::STATUS_BROUILLON => 'Brouillon',
        self::STATUS_CALCULEE => 'Calculée',
        self::STATUS_VERIFIEE => 'Vérifiée',
        self::STATUS_VALIDEE => 'Validée',
        self::STATUS_PAYEE => 'Payée',
    ];

    protected $fillable = [
        'period_year',
        'period_month',
        'status',
        'calculated_at',
        'verified_at',
        'validated_at',
        'paid_at',
        'calculated_by',
        'verified_by',
        'validated_by',
        'paid_by',
        'notes',
    ];

    protected $casts = [
        'period_year' => 'integer',
        'period_month' => 'integer',
        'calculated_at' => 'datetime',
        'verified_at' => 'datetime',
        'validated_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function slips(): HasMany
    {
        return $this->hasMany(PayrollSlip::class);
    }

    public function calculatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    public function periodLabel(): string
    {
        return sprintf('%02d/%d', $this->period_month, $this->period_year);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isLocked(): bool
    {
        return in_array($this->status, [self::STATUS_VALIDEE, self::STATUS_PAYEE], true);
    }

    public function canRecalculate(): bool
    {
        return in_array($this->status, [self::STATUS_BROUILLON, self::STATUS_CALCULEE, self::STATUS_VERIFIEE], true);
    }
}
