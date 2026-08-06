<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialDeclaration extends Model
{
    public const STATUS_OUVERTE = 'ouverte';

    public const STATUS_CONTROLEE = 'controlee';

    public const STATUS_VALIDEE = 'validee';

    public const STATUS_CLOTUREE = 'cloturee';

    protected $fillable = [
        'period_from',
        'period_to',
        'status',
        'vat_collected',
        'vat_deductible',
        'vat_net',
        'revenue',
        'anomalies',
        'control_report',
        'controlled_at',
        'controlled_by',
        'validated_at',
        'validated_by',
        'closed_at',
        'closed_by',
        'reopen_reason',
        'reopened_at',
        'reopened_by',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'vat_collected' => 'decimal:2',
        'vat_deductible' => 'decimal:2',
        'vat_net' => 'decimal:2',
        'revenue' => 'decimal:2',
        'anomalies' => 'array',
        'control_report' => 'array',
        'controlled_at' => 'datetime',
        'validated_at' => 'datetime',
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
    ];

    public function pieces(): HasMany
    {
        return $this->hasMany(FinancialPiece::class);
    }

    public function controlledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'controlled_by');
    }

    public function validatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reopenedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOTUREE;
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_OUVERTE => 'Ouverte',
            self::STATUS_CONTROLEE => 'Contrôlée',
            self::STATUS_VALIDEE => 'Validée',
            self::STATUS_CLOTUREE => 'Clôturée',
        ];
    }
}
