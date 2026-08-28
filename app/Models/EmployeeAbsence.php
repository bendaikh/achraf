<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAbsence extends Model
{
    public const TYPE_JUSTIFIED = 'justified';

    public const TYPE_UNJUSTIFIED = 'unjustified';

    public const TYPE_SICK = 'sick';

    public const TYPE_AUTHORIZATION = 'authorization';

    public const TYPE_UNPAID = 'unpaid';

    public const TYPE_FAMILY = 'family';

    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_JUSTIFIED => 'Absence justifiée',
        self::TYPE_UNJUSTIFIED => 'Absence injustifiée',
        self::TYPE_SICK => 'Maladie',
        self::TYPE_UNPAID => 'Congé sans solde',
        self::TYPE_FAMILY => 'Événement familial',
        self::TYPE_AUTHORIZATION => 'Autorisation d\'absence',
        self::TYPE_OTHER => 'Autre',
    ];

    protected $fillable = [
        'employee_id',
        'type',
        'start_date',
        'end_date',
        'days',
        'comment',
        'impacts_payroll',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'days' => 'decimal:2',
        'impacts_payroll' => 'boolean',
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
