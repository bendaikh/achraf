<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    public const STATUS_ACTIF = 'actif';

    public const STATUS_SUSPENDU = 'suspendu';

    public const STATUS_SORTI = 'sorti';

    public const STATUSES = [
        self::STATUS_ACTIF => 'Actif',
        self::STATUS_SUSPENDU => 'Suspendu',
        self::STATUS_SORTI => 'Sorti',
    ];

    public const GENDERS = [
        'homme' => 'Homme',
        'femme' => 'Femme',
        'autre' => 'Autre',
    ];

    public const MARITAL_STATUSES = [
        'celibataire' => 'Célibataire',
        'marie' => 'Marié(e)',
        'divorce' => 'Divorcé(e)',
        'veuf' => 'Veuf/Veuve',
    ];

    protected $fillable = [
        'matricule',
        'last_name',
        'first_name',
        'birth_date',
        'cin',
        'nationality',
        'gender',
        'marital_status',
        'address',
        'city',
        'phone',
        'email',
        'photo_path',
        'cnss_number',
        'amo_number',
        'rib',
        'bank_name',
        'hire_date',
        'job_title',
        'department_id',
        'manager_id',
        'workplace',
        'status',
        'timeclock_external_id',
        'user_id',
        'commission_eligible',
        'initial_leave_balance',
        'notes',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'hire_date' => 'date',
        'commission_eligible' => 'boolean',
        'initial_leave_balance' => 'decimal:2',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(HrDepartment::class, 'department_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function collaborator(): HasOne
    {
        return $this->hasOne(Collaborator::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class)->orderByDesc('start_date');
    }

    public function currentContract(): HasOne
    {
        return $this->hasOne(EmployeeContract::class)
            ->where('status', EmployeeContract::STATUS_EN_COURS)
            ->latestOfMany('start_date');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(EmployeeSchedule::class)->orderBy('weekday');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveBalanceEntries(): HasMany
    {
        return $this->hasMany(LeaveBalanceEntry::class)->orderByDesc('entry_date')->orderByDesc('id');
    }

    public function absences(): HasMany
    {
        return $this->hasMany(EmployeeAbsence::class)->orderByDesc('start_date');
    }

    public function salaryRecords(): HasMany
    {
        return $this->hasMany(SalaryRecord::class)->orderByDesc('effective_date');
    }

    public function compensationItems(): HasMany
    {
        return $this->hasMany(CompensationItem::class)->orderByDesc('start_date');
    }

    public function payrollAdjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class)->orderByDesc('period_year')->orderByDesc('period_month');
    }

    public function payrollSlips(): HasMany
    {
        return $this->hasMany(PayrollSlip::class);
    }

    public function exitRecord(): HasOne
    {
        return $this->hasOne(EmployeeExit::class)->latestOfMany();
    }

    public function events(): HasMany
    {
        return $this->hasMany(HrEvent::class)->orderByDesc('event_date')->orderByDesc('id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(ManagedDocument::class, 'documentable');
    }

    public function fullName(): string
    {
        return trim($this->last_name.' '.$this->first_name);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIF => 'bg-emerald-100 text-emerald-800',
            self::STATUS_SUSPENDU => 'bg-amber-100 text-amber-800',
            self::STATUS_SORTI => 'bg-gray-100 text-gray-700',
            default => 'bg-gray-100 text-gray-700',
        };
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIF;
    }

    public function currentSalary(): ?SalaryRecord
    {
        return $this->salaryRecords()
            ->whereDate('effective_date', '<=', now()->toDateString())
            ->orderByDesc('effective_date')
            ->first();
    }

    public function salaryOn(\DateTimeInterface $date): ?SalaryRecord
    {
        return $this->salaryRecords()
            ->whereDate('effective_date', '<=', $date)
            ->orderByDesc('effective_date')
            ->first();
    }

    public function currentLeaveBalance(): float
    {
        $last = $this->leaveBalanceEntries()->reorder()->orderByDesc('id')->first();

        return $last ? (float) $last->balance_after : (float) $this->initial_leave_balance;
    }

    /**
     * @return array{acquired: float, taken: float, remaining: float}
     */
    public function leaveBalanceSummary(): array
    {
        $entries = $this->relationLoaded('leaveBalanceEntries')
            ? $this->leaveBalanceEntries
            : $this->leaveBalanceEntries()->get();

        $acquired = 0.0;
        $taken = 0.0;
        foreach ($entries as $entry) {
            if (in_array($entry->type, [
                LeaveBalanceEntry::TYPE_INITIAL,
                LeaveBalanceEntry::TYPE_ACCRUAL,
                LeaveBalanceEntry::TYPE_CARRYOVER,
            ], true) && (float) $entry->days > 0) {
                $acquired += (float) $entry->days;
            }
            if ($entry->type === LeaveBalanceEntry::TYPE_TAKEN) {
                $taken += abs((float) $entry->days);
            }
        }

        return [
            'acquired' => round($acquired, 2),
            'taken' => round($taken, 2),
            'remaining' => round($this->currentLeaveBalance(), 2),
        ];
    }

    public function currentSchedules()
    {
        $today = now()->toDateString();
        $rows = $this->schedules
            ->filter(fn (EmployeeSchedule $row) => $row->effective_from === null || $row->effective_from->toDateString() <= $today)
            ->groupBy('weekday')
            ->map(fn ($group) => $group->sortByDesc(fn (EmployeeSchedule $row) => $row->effective_from?->toDateString() ?? '1970-01-01')->first());

        return $rows->sortKeys();
    }
}
