<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSchedule extends Model
{
    public const WEEKDAYS = [
        1 => 'Lundi',
        2 => 'Mardi',
        3 => 'Mercredi',
        4 => 'Jeudi',
        5 => 'Vendredi',
        6 => 'Samedi',
        7 => 'Dimanche',
    ];

    protected $fillable = [
        'employee_id',
        'effective_from',
        'weekday',
        'start_time',
        'end_time',
        'break_minutes',
        'is_off',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'weekday' => 'integer',
        'break_minutes' => 'integer',
        'is_off' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function weekdayLabel(): string
    {
        return self::WEEKDAYS[$this->weekday] ?? (string) $this->weekday;
    }

    public static function forEmployeeOnDate(int $employeeId, int $weekday, \DateTimeInterface|string $date): ?self
    {
        $on = \Carbon\Carbon::parse($date)->toDateString();

        return static::query()
            ->where('employee_id', $employeeId)
            ->where('weekday', $weekday)
            ->whereDate('effective_from', '<=', $on)
            ->orderByDesc('effective_from')
            ->first();
    }
}
