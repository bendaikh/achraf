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

    public const STATUS_REST = 'rest';

    public const STATUS_HOLIDAY = 'holiday';

    public const STATUS_LATE = 'late';

    public const STATUSES = [
        self::STATUS_PRESENT => 'Présent',
        self::STATUS_ABSENT => 'Absent',
        self::STATUS_LEAVE => 'Congé',
        self::STATUS_REST => 'Repos',
        self::STATUS_HOLIDAY => 'Jour férié',
        self::STATUS_LATE => 'Retard',
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
        $hours = intdiv($this->worked_minutes, 60);
        $minutes = $this->worked_minutes % 60;

        return sprintf('%dh%02d', $hours, $minutes);
    }
}
