<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalanceEntry extends Model
{
    public const TYPE_INITIAL = 'initial';

    public const TYPE_ACCRUAL = 'accrual';

    public const TYPE_TAKEN = 'taken';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_CARRYOVER = 'carryover';

    public const TYPES = [
        self::TYPE_INITIAL => 'Solde repris',
        self::TYPE_ACCRUAL => 'Droits acquis',
        self::TYPE_TAKEN => 'Congé pris',
        self::TYPE_ADJUSTMENT => 'Ajustement',
        self::TYPE_CARRYOVER => 'Solde reporté',
    ];

    protected $fillable = [
        'employee_id',
        'entry_date',
        'type',
        'days',
        'balance_after',
        'leave_request_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'days' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
