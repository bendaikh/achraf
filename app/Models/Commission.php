<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Commission extends Model
{
    public const STATUS_A_VENIR = 'a_venir';

    public const STATUS_ACQUISE = 'acquise';

    public const STATUS_VALIDEE = 'validee';

    public const STATUS_PAYEE = 'payee';

    public const STATUS_ANNULEE = 'annulee';

    public const STATUS_REGULARISEE = 'regularisee';

    public const STATUSES = [
        self::STATUS_A_VENIR => 'À venir',
        self::STATUS_ACQUISE => 'Acquise',
        self::STATUS_VALIDEE => 'Validée',
        self::STATUS_PAYEE => 'Payée',
        self::STATUS_ANNULEE => 'Annulée',
        self::STATUS_REGULARISEE => 'Régularisée',
    ];

    protected $fillable = [
        'collaborator_id',
        'commission_rule_id',
        'source_type',
        'source_id',
        'document_ref',
        'base_amount',
        'rate',
        'amount',
        'status',
        'earned_at',
        'validated_at',
        'paid_at',
        'payment_method',
        'payment_reference',
        'notes',
        'parent_id',
        'payroll_linked_at',
        'payroll_adjustment_id',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'rate' => 'decimal:4',
        'amount' => 'decimal:2',
        'earned_at' => 'date',
        'validated_at' => 'date',
        'paid_at' => 'date',
        'payroll_linked_at' => 'datetime',
    ];

    public function collaborator(): BelongsTo
    {
        return $this->belongsTo(Collaborator::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class, 'commission_rule_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
