<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceRecord extends Model
{
    public const STATUS_PRESENT = 'present';

    public const STATUS_ABSENT = 'absent';

    public const STATUS_LEAVE = 'leave';

    public const STATUS_SICK = 'sick';

    public const STATUS_REST = 'rest';

    public const STATUS_HOLIDAY = 'holiday';

    public const STATUS_LATE = 'late';

    public const STATUSES = [
        self::STATUS_PRESENT => 'Présent',
        self::STATUS_ABSENT => 'Absent',
        self::STATUS_LEAVE => 'Congé payé',
        self::STATUS_SICK => 'Congé maladie',
        self::STATUS_REST => 'Repos',
        self::STATUS_HOLIDAY => 'Jour férié',
        self::STATUS_LATE => 'Retard',
    ];

    /** Statuses available in the monthly entry grid. */
    public const ENTRY_STATUSES = [
        self::STATUS_PRESENT => 'Présent',
        self::STATUS_REST => 'Repos',
        self::STATUS_LEAVE => 'Congé payé',
        self::STATUS_SICK => 'Congé maladie',
        self::STATUS_ABSENT => 'Absent',
        self::STATUS_LATE => 'Retard',
        self::STATUS_HOLIDAY => 'Jour férié',
    ];

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_TIMECLOCK = 'timeclock';

    public const SOURCE_SYSTEM = 'system';

    public const SOURCE_IMPORT = 'import';

    public const SOURCES = [
        self::SOURCE_MANUAL => 'Manuel',
        self::SOURCE_TIMECLOCK => 'Pointeuse',
        self::SOURCE_SYSTEM => 'Système',
        self::SOURCE_IMPORT => 'Import',
    ];

    protected $fillable = [
        'employee_id',
        'work_date',
        'clock_in',
        'clock_out',
        'worked_minutes',
        'late_minutes',
        'early_minutes',
        'overtime_minutes',
        'is_incomplete',
        'status',
        'source',
        'notes',
    ];

    protected $casts = [
        'work_date' => 'date',
        'worked_minutes' => 'integer',
        'late_minutes' => 'integer',
        'early_minutes' => 'integer',
        'overtime_minutes' => 'integer',
        'is_incomplete' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class)->latest();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? $this->source;
    }

    public function workedHoursLabel(): string
    {
        return self::minutesLabel((int) $this->worked_minutes);
    }

    public static function minutesLabel(int $minutes): string
    {
        $sign = $minutes < 0 ? '-' : '';
        $minutes = abs($minutes);
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return sprintf('%s%dh%02d', $sign, $hours, $mins);
    }

    public static function statusBadgeClass(string $status): string
    {
        return match ($status) {
            self::STATUS_PRESENT => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            self::STATUS_REST => 'bg-slate-100 text-slate-600 border-slate-200',
            self::STATUS_LEAVE => 'bg-violet-100 text-violet-800 border-violet-200',
            self::STATUS_SICK => 'bg-purple-50 text-purple-700 border-purple-200',
            self::STATUS_ABSENT => 'bg-red-100 text-red-700 border-red-200',
            self::STATUS_LATE => 'bg-amber-100 text-amber-800 border-amber-200',
            self::STATUS_HOLIDAY => 'bg-sky-100 text-sky-800 border-sky-200',
            default => 'bg-gray-100 text-gray-700 border-gray-200',
        };
    }
}
