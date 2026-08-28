<?php

namespace App\Services\Hr;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Setting;
use Carbon\Carbon;

class AttendanceService
{
    public function __construct(
        protected HrAuditService $audit,
        protected HrTimelineService $timeline,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertManual(Employee $employee, array $attributes, ?string $correctionReason = null, ?int $userId = null): AttendanceRecord
    {
        $workDate = Carbon::parse($attributes['work_date'])->toDateString();

        $record = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $workDate)
            ->first() ?? new AttendanceRecord([
                'employee_id' => $employee->id,
                'work_date' => $workDate,
            ]);

        $before = $record->exists ? $record->only(['clock_in', 'clock_out', 'status', 'notes', 'worked_minutes', 'late_minutes', 'overtime_minutes', 'early_minutes']) : [];

        $record->fill([
            'clock_in' => $attributes['clock_in'] ?? $record->clock_in,
            'clock_out' => $attributes['clock_out'] ?? $record->clock_out,
            'status' => $attributes['status'] ?? $record->status ?? AttendanceRecord::STATUS_PRESENT,
            'notes' => $attributes['notes'] ?? $record->notes,
            'source' => $attributes['source'] ?? ($record->exists && $record->source === AttendanceRecord::SOURCE_TIMECLOCK
                ? AttendanceRecord::SOURCE_TIMECLOCK
                : AttendanceRecord::SOURCE_MANUAL),
        ]);

        $this->recalculate($record, $employee);
        $record->save();

        if ($before !== []) {
            $this->recordCorrections($record, $before, $correctionReason ?: 'Correction manuelle du pointage', $userId);
        }

        return $record->fresh();
    }

    public function recalculate(AttendanceRecord $record, ?Employee $employee = null): void
    {
        $employee ??= $record->employee;
        $weekday = Carbon::parse($record->work_date)->isoWeekday();
        $schedule = EmployeeSchedule::forEmployeeOnDate($employee->id, $weekday, $record->work_date);
        $lateThreshold = (int) Setting::get('hr.late_threshold_minutes', 5);

        if (in_array($record->status, [AttendanceRecord::STATUS_LEAVE, AttendanceRecord::STATUS_REST, AttendanceRecord::STATUS_HOLIDAY, AttendanceRecord::STATUS_ABSENT], true)
            && ! $record->clock_in && ! $record->clock_out) {
            $record->worked_minutes = 0;
            $record->late_minutes = 0;
            $record->early_minutes = 0;
            $record->overtime_minutes = 0;
            $record->is_incomplete = false;

            return;
        }

        $worked = 0;
        if ($record->clock_in && $record->clock_out) {
            $in = Carbon::parse($record->work_date->format('Y-m-d').' '.$record->clock_in);
            $out = Carbon::parse($record->work_date->format('Y-m-d').' '.$record->clock_out);
            if ($out->lt($in)) {
                $out->addDay();
            }
            $worked = max(0, $in->diffInMinutes($out));
            if ($schedule) {
                $worked = max(0, $worked - (int) $schedule->break_minutes);
            }
        }

        $late = 0;
        $early = 0;
        $overtime = 0;
        if ($schedule && ! $schedule->is_off && $schedule->start_time && $record->clock_in) {
            $expectedIn = Carbon::parse($record->work_date->format('Y-m-d').' '.$schedule->start_time);
            $actualIn = Carbon::parse($record->work_date->format('Y-m-d').' '.$record->clock_in);
            if ($actualIn->gt($expectedIn)) {
                $late = $expectedIn->diffInMinutes($actualIn);
            }
        }

        if ($schedule && ! $schedule->is_off && $schedule->end_time && $record->clock_out) {
            $expectedOut = Carbon::parse($record->work_date->format('Y-m-d').' '.$schedule->end_time);
            $actualOut = Carbon::parse($record->work_date->format('Y-m-d').' '.$record->clock_out);
            if ($actualOut->gt($expectedOut)) {
                $overtime = $expectedOut->diffInMinutes($actualOut);
            } elseif ($actualOut->lt($expectedOut)) {
                $early = $actualOut->diffInMinutes($expectedOut);
            }
        }

        $record->worked_minutes = $worked;
        $record->late_minutes = $late;
        $record->early_minutes = $early;
        $record->overtime_minutes = $overtime;
        $record->is_incomplete = (bool) (($record->clock_in && ! $record->clock_out) || (! $record->clock_in && $record->clock_out) || $early > 0);

        if ($record->status === AttendanceRecord::STATUS_PRESENT && $late > $lateThreshold) {
            $record->status = AttendanceRecord::STATUS_LATE;
        }
    }

    /**
     * @param  array{external_id: string, punched_at: string, type?: string}  $punch
     */
    public function ingestTimeclockPunch(array $punch): ?AttendanceRecord
    {
        $employee = Employee::query()
            ->where(function ($q) use ($punch) {
                $q->where('timeclock_external_id', $punch['external_id'])
                    ->orWhere('matricule', $punch['external_id']);
            })
            ->first();

        if (! $employee) {
            return null;
        }

        $punchedAt = Carbon::parse($punch['punched_at']);
        $record = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $punchedAt->toDateString())
            ->first() ?? new AttendanceRecord([
                'employee_id' => $employee->id,
                'work_date' => $punchedAt->toDateString(),
            ]);

        $type = $punch['type'] ?? ($record->clock_in ? 'out' : 'in');
        if ($type === 'out') {
            $record->clock_out = $punchedAt->format('H:i:s');
        } else {
            $record->clock_in = $punchedAt->format('H:i:s');
        }

        $record->source = AttendanceRecord::SOURCE_TIMECLOCK;
        if (! $record->status) {
            $record->status = AttendanceRecord::STATUS_PRESENT;
        }

        $this->recalculate($record, $employee);
        $record->save();

        return $record;
    }

    /**
     * @param  list<array<string, mixed>>  $days
     */
    public function applyScheduleVersion(Employee $employee, array $days, string $effectiveFrom, ?int $userId = null): void
    {
        foreach ($days as $day) {
            EmployeeSchedule::query()->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'weekday' => (int) $day['weekday'],
                    'effective_from' => $effectiveFrom,
                ],
                [
                    'start_time' => ($day['is_off'] ?? false) ? null : ($day['start_time'] ?? null),
                    'end_time' => ($day['is_off'] ?? false) ? null : ($day['end_time'] ?? null),
                    'break_minutes' => (int) ($day['break_minutes'] ?? 0),
                    'is_off' => (bool) ($day['is_off'] ?? false),
                ]
            );
        }

        $this->audit->log($employee, 'schedule', 'effective_from', null, $effectiveFrom, 'Nouveau planning', $userId);
        $this->timeline->record($employee, 'schedule', 'Planning modifié', $effectiveFrom, 'Date d’effet '.$effectiveFrom, $employee, $userId);
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function recordCorrections(AttendanceRecord $record, array $before, string $reason, ?int $userId): void
    {
        foreach (['clock_in', 'clock_out', 'status', 'notes'] as $field) {
            $old = $before[$field] ?? null;
            $new = $record->{$field};
            if ((string) $old === (string) $new) {
                continue;
            }

            AttendanceCorrection::create([
                'attendance_record_id' => $record->id,
                'field' => $field,
                'old_value' => $old,
                'new_value' => $new,
                'reason' => $reason,
                'user_id' => $userId ?? auth()->id(),
            ]);

            $this->audit->log($record, 'correct', $field, $old, $new, $reason, $userId);
        }
    }

    public function seedDefaultSchedule(Employee $employee): void
    {
        if ($employee->schedules()->exists()) {
            return;
        }

        $from = $employee->hire_date?->toDateString() ?? '1970-01-01';

        foreach (EmployeeSchedule::WEEKDAYS as $day => $label) {
            $isOff = $day >= 6;
            EmployeeSchedule::create([
                'employee_id' => $employee->id,
                'effective_from' => $from,
                'weekday' => $day,
                'start_time' => $isOff ? null : '09:00:00',
                'end_time' => $isOff ? null : '18:00:00',
                'break_minutes' => $isOff ? 0 : 60,
                'is_off' => $isOff,
            ]);
        }
    }
}
