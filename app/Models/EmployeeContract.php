<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeContract extends Model
{
    public const TYPE_CDI = 'cdi';

    public const TYPE_CDD = 'cdd';

    public const TYPE_STAGE = 'stage';

    public const TYPE_AUTRE = 'autre';

    public const TYPES = [
        self::TYPE_CDI => 'CDI',
        self::TYPE_CDD => 'CDD',
        self::TYPE_STAGE => 'Stage',
        self::TYPE_AUTRE => 'Autre',
    ];

    public const STATUS_EN_COURS = 'en_cours';

    public const STATUS_TERMINE = 'termine';

    public const STATUS_RENOUVELE = 'renouvele';

    public const STATUSES = [
        self::STATUS_EN_COURS => 'En cours',
        self::STATUS_TERMINE => 'Terminé',
        self::STATUS_RENOUVELE => 'Renouvelé',
    ];

    protected $fillable = [
        'employee_id',
        'type',
        'start_date',
        'end_date',
        'job_title',
        'workplace',
        'salary',
        'trial_start_date',
        'trial_end_date',
        'status',
        'previous_contract_id',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'trial_start_date' => 'date',
        'trial_end_date' => 'date',
        'salary' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function previousContract(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_contract_id');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(self::class, 'previous_contract_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
