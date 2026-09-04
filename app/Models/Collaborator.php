<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collaborator extends Model
{
    public const TYPE_SALARIE = 'salarie';

    public const TYPE_FREELANCE = 'freelance';

    public const TYPE_PRESTATAIRE = 'prestataire';

    public const TYPE_STAGIAIRE = 'stagiaire';

    public const TYPE_AUTRE = 'autre';

    public const TYPES = [
        self::TYPE_SALARIE => 'Salarié',
        self::TYPE_FREELANCE => 'Freelance',
        self::TYPE_PRESTATAIRE => 'Prestataire externe',
        self::TYPE_STAGIAIRE => 'Stagiaire',
        self::TYPE_AUTRE => 'Autre',
    ];

    public const STATUS_ACTIF = 'actif';

    public const STATUS_INACTIF = 'inactif';

    public const STATUSES = [
        self::STATUS_ACTIF => 'Actif',
        self::STATUS_INACTIF => 'Inactif',
    ];

    protected $fillable = [
        'type',
        'last_name',
        'first_name',
        'photo_path',
        'phone',
        'email',
        'job_title',
        'department',
        'team',
        'manager_id',
        'employee_id',
        'start_date',
        'end_date',
        'status',
        'is_commercial',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_commercial' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    public function fullName(): string
    {
        return trim($this->last_name.' '.$this->first_name);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIF => 'bg-emerald-100 text-emerald-800',
            self::STATUS_INACTIF => 'bg-gray-100 text-gray-700',
            default => 'bg-gray-100 text-gray-700',
        };
    }

    public function isSalarie(): bool
    {
        return $this->type === self::TYPE_SALARIE;
    }

    public function isFreelance(): bool
    {
        return $this->type === self::TYPE_FREELANCE;
    }

    public function hasUserAccount(): bool
    {
        return $this->user()->exists();
    }

    public function hasHrFile(): bool
    {
        return $this->employee_id !== null;
    }
}
