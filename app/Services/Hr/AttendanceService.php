<?php

namespace App\Services\Hr;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\EmployeeSchedule;
use App\Models\LeaveRequest;
use App\Models\Setting;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

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

        $clockIn = array_key_exists('clock_in', $attributes)
            ? ($attributes['clock_in'] ?: null)
            : $record->clock_in;
        $clockOut = array_key_exists('clock_out', $attributes)
            ? ($attributes['clock_out'] ?: null)
            : $record->clock_out;

        $record->fill([
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'status' => $attributes['status'] ?? $record->status ?? AttendanceRecord::STATUS_PRESENT,
            'notes' => array_key_exists('notes', $attributes) ? ($attributes['notes'] ?: null) : $record->notes,
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

        if (in_array($record->status, [
            AttendanceRecord::STATUS_LEAVE,
            AttendanceRecord::STATUS_SICK,
            AttendanceRecord::STATUS_REST,
            AttendanceRecord::STATUS_HOLIDAY,
            AttendanceRecord::STATUS_ABSENT,
        ], true) && ! $record->clock_in && ! $record->clock_out) {
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

    /**
     * Build the editable month grid for one employee (records + leave/absence + schedule defaults).
     *
     * @return array{days: list<array<string, mixed>>, summary: array<string, int|string>}
     */
    public function buildMonthGrid(Employee $employee, int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        /** @var Collection<string, AttendanceRecord> $records */
        $records = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (AttendanceRecord $r) => $r->work_date->toDateString());

        $leaves = LeaveRequest::query()
            ->with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->get();

        $absences = EmployeeAbsence::query()
            ->where('employee_id', $employee->id)
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->get();

        $days = [];
        foreach (CarbonPeriod::create($start, $end) as $date) {
            /** @var Carbon $date */
            $key = $date->toDateString();
            $weekday = $date->isoWeekday();
            $schedule = EmployeeSchedule::forEmployeeOnDate($employee->id, $weekday, $key);
            $record = $records->get($key);

            $locked = false;
            $lockSource = null;
            $status = AttendanceRecord::STATUS_PRESENT;
            $clockIn = null;
            $clockOut = null;
            $notes = null;
            $worked = 0;
            $late = 0;
            $overtime = 0;
            $recordId = null;

            $leave = $leaves->first(fn (LeaveRequest $l) => $date->betweenIncluded($l->start_date, $l->end_date));
            $absence = $absences->first(fn (EmployeeAbsence $a) => $date->betweenIncluded($a->start_date, $a->end_date));

            if ($record) {
                $recordId = $record->id;
                $status = $record->status;
                $clockIn = $record->clock_in ? substr((string) $record->clock_in, 0, 5) : null;
                $clockOut = $record->clock_out ? substr((string) $record->clock_out, 0, 5) : null;
                $notes = $record->notes;
                $worked = (int) $record->worked_minutes;
                $late = (int) $record->late_minutes;
                $overtime = (int) $record->overtime_minutes;
            } elseif ($leave) {
                $locked = true;
                $lockSource = 'leave';
                $typeName = mb_strtolower((string) ($leave->leaveType?->name ?? ''));
                $isSick = str_contains($typeName, 'maladie') || str_contains($typeName, 'sick');
                $paid = (bool) ($leave->leaveType?->paid ?? true);
                $status = $isSick
                    ? AttendanceRecord::STATUS_SICK
                    : ($paid ? AttendanceRecord::STATUS_LEAVE : AttendanceRecord::STATUS_ABSENT);
                $notes = 'Congé (depuis module congés)'.($leave->leaveType ? ' — '.$leave->leaveType->name : '');
            } elseif ($absence) {
                $locked = true;
                $lockSource = 'absence';
                $status = $absence->type === EmployeeAbsence::TYPE_SICK
                    ? AttendanceRecord::STATUS_SICK
                    : AttendanceRecord::STATUS_ABSENT;
                $notes = $absence->typeLabel().($absence->comment ? ' — '.$absence->comment : '');
            } elseif ($schedule?->is_off || (! $schedule && $weekday >= 6)) {
                $status = AttendanceRecord::STATUS_REST;
            } else {
                $status = AttendanceRecord::STATUS_PRESENT;
                $clockIn = $schedule?->start_time ? substr((string) $schedule->start_time, 0, 5) : null;
                $clockOut = $schedule?->end_time ? substr((string) $schedule->end_time, 0, 5) : null;
            }

            $scheduleIn = $schedule && ! $schedule->is_off && $schedule->start_time
                ? substr((string) $schedule->start_time, 0, 5)
                : null;
            $scheduleOut = $schedule && ! $schedule->is_off && $schedule->end_time
                ? substr((string) $schedule->end_time, 0, 5)
                : null;
            $breakMinutes = (int) ($schedule?->break_minutes ?? 0);
            $scheduleIsOff = (bool) ($schedule?->is_off || (! $schedule && $weekday >= 6));

            $days[] = [
                'date' => $key,
                'date_label' => $date->format('d/m'),
                'weekday' => $weekday,
                'day_label' => ucfirst($date->locale('fr')->isoFormat('dddd')),
                'is_weekend' => $weekday >= 6,
                'status' => $status,
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
                'break_minutes' => $breakMinutes,
                'worked_minutes' => $worked,
                'late_minutes' => $late,
                'overtime_minutes' => $overtime,
                'notes' => $notes,
                'locked' => $locked,
                'lock_source' => $lockSource,
                'record_id' => $recordId,
                'schedule_in' => $scheduleIn,
                'schedule_out' => $scheduleOut,
                'schedule_is_off' => $scheduleIsOff,
                'has_record' => (bool) $record,
            ];
        }

        return [
            'days' => $days,
            'summary' => $this->summarizeMonthDays($days),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $days
     * @return array<string, int|string>
     */
    public function summarizeMonthDays(array $days): array
    {
        $theoretical = 0;
        $workedDays = 0;
        $presentDays = 0;
        $workedMinutes = 0;
        $leaveDays = 0;
        $sickDays = 0;
        $absentDays = 0;
        $restDays = 0;
        $lateDays = 0;
        $lateMinutes = 0;
        $overtimeMinutes = 0;

        foreach ($days as $day) {
            $status = $day['status'] ?? '';
            $isOff = ! empty($day['schedule_is_off'])
                || in_array($status, [AttendanceRecord::STATUS_REST, AttendanceRecord::STATUS_HOLIDAY], true);

            if (! $isOff || in_array($status, [AttendanceRecord::STATUS_PRESENT, AttendanceRecord::STATUS_LATE, AttendanceRecord::STATUS_ABSENT, AttendanceRecord::STATUS_LEAVE, AttendanceRecord::STATUS_SICK], true)) {
                if (empty($day['schedule_is_off'])) {
                    $theoretical++;
                }
            }

            if (in_array($status, [AttendanceRecord::STATUS_PRESENT, AttendanceRecord::STATUS_LATE], true)) {
                $workedDays++;
                $presentDays++;
                $workedMinutes += (int) ($day['worked_minutes'] ?? 0);
            }
            if ($status === AttendanceRecord::STATUS_LEAVE) {
                $leaveDays++;
            }
            if ($status === AttendanceRecord::STATUS_SICK) {
                $sickDays++;
            }
            if ($status === AttendanceRecord::STATUS_ABSENT) {
                $absentDays++;
            }
            if (in_array($status, [AttendanceRecord::STATUS_REST, AttendanceRecord::STATUS_HOLIDAY], true)) {
                $restDays++;
            }
            if ($status === AttendanceRecord::STATUS_LATE || (int) ($day['late_minutes'] ?? 0) > 0) {
                $lateDays++;
            }
            $lateMinutes += (int) ($day['late_minutes'] ?? 0);
            $overtimeMinutes += (int) ($day['overtime_minutes'] ?? 0);
        }

        return [
            'theoretical_work_days' => $theoretical,
            'worked_days' => $workedDays,
            'present_days' => $presentDays,
            'worked_hours' => AttendanceRecord::minutesLabel($workedMinutes),
            'worked_minutes' => $workedMinutes,
            'leave_days' => $leaveDays + $sickDays,
            'absent_days' => $absentDays,
            'rest_days' => $restDays,
            'late_days' => $lateDays,
            'late_minutes' => $lateMinutes,
            'overtime_hours' => AttendanceRecord::minutesLabel($overtimeMinutes),
            'overtime_minutes' => $overtimeMinutes,
        ];
    }

    public function syncLeaveToAttendance(LeaveRequest $leave, ?int $userId = null): void
    {
        $leave->loadMissing(['leaveType', 'employee']);
        $employee = $leave->employee;
        if (! $employee) {
            return;
        }

        $typeName = mb_strtolower((string) ($leave->leaveType?->name ?? ''));
        $isSick = str_contains($typeName, 'maladie') || str_contains($typeName, 'sick');
        $paid = (bool) ($leave->leaveType?->paid ?? true);
        $status = $isSick
            ? AttendanceRecord::STATUS_SICK
            : ($paid ? AttendanceRecord::STATUS_LEAVE : AttendanceRecord::STATUS_ABSENT);

        foreach (CarbonPeriod::create($leave->start_date, $leave->end_date) as $date) {
            $this->upsertManual($employee, [
                'work_date' => $date->toDateString(),
                'status' => $status,
                'clock_in' => null,
                'clock_out' => null,
                'notes' => 'Congé (depuis module congés)'.($leave->leaveType ? ' — '.$leave->leaveType->name : ''),
                'source' => AttendanceRecord::SOURCE_SYSTEM,
            ], 'Synchronisation congé validé', $userId);
        }
    }

    public function clearLeaveFromAttendance(LeaveRequest $leave, ?int $userId = null): void
    {
        $leave->loadMissing('employee');
        $employee = $leave->employee;
        if (! $employee) {
            return;
        }

        foreach (CarbonPeriod::create($leave->start_date, $leave->end_date) as $date) {
            $record = AttendanceRecord::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $date->toDateString())
                ->where('source', AttendanceRecord::SOURCE_SYSTEM)
                ->whereIn('status', [
                    AttendanceRecord::STATUS_LEAVE,
                    AttendanceRecord::STATUS_ABSENT,
                    AttendanceRecord::STATUS_SICK,
                ])
                ->first();

            if (! $record) {
                continue;
            }

            $schedule = EmployeeSchedule::forEmployeeOnDate($employee->id, $date->isoWeekday(), $date);
            $this->upsertManual($employee, [
                'work_date' => $date->toDateString(),
                'status' => $schedule?->is_off ? AttendanceRecord::STATUS_REST : AttendanceRecord::STATUS_ABSENT,
                'clock_in' => null,
                'clock_out' => null,
                'notes' => 'Congé annulé/refusé — statut rétabli selon planning',
                'source' => AttendanceRecord::SOURCE_SYSTEM,
            ], 'Annulation synchronisation congé', $userId);
        }
    }

    public function syncAbsenceToAttendance(EmployeeAbsence $absence, ?int $userId = null): void
    {
        $absence->loadMissing('employee');
        $employee = $absence->employee;
        if (! $employee) {
            return;
        }

        $status = $absence->type === EmployeeAbsence::TYPE_SICK
            ? AttendanceRecord::STATUS_SICK
            : AttendanceRecord::STATUS_ABSENT;

        foreach (CarbonPeriod::create($absence->start_date, $absence->end_date) as $date) {
            $hasLeave = LeaveRequest::query()
                ->where('employee_id', $employee->id)
                ->where('status', LeaveRequest::STATUS_APPROVED)
                ->whereDate('start_date', '<=', $date->toDateString())
                ->whereDate('end_date', '>=', $date->toDateString())
                ->exists();

            if ($hasLeave) {
                continue;
            }

            $this->upsertManual($employee, [
                'work_date' => $date->toDateString(),
                'status' => $status,
                'clock_in' => null,
                'clock_out' => null,
                'notes' => 'Absence: '.$absence->typeLabel(),
                'source' => AttendanceRecord::SOURCE_SYSTEM,
            ], 'Synchronisation absence', $userId);
        }
    }

    /**
     * Persist a full month of attendance rows.
     *
     * @param  list<array<string, mixed>>  $days
     */
    public function saveMonth(Employee $employee, int $year, int $month, array $days, ?string $correctionReason = null, ?int $userId = null): int
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();
        $saved = 0;

        $grid = $this->buildMonthGrid($employee, $year, $month);
        $lockedDates = collect($grid['days'])
            ->filter(fn (array $d) => $d['locked'])
            ->keyBy('date');

        foreach ($days as $day) {
            $workDate = Carbon::parse($day['work_date'] ?? $day['date'] ?? null);
            if ($workDate->lt($start) || $workDate->gt($end)) {
                continue;
            }

            $key = $workDate->toDateString();
            if ($lockedDates->has($key) && empty($day['force'])) {
                // Still mirror leave/absence into attendance so payroll sees them.
                $locked = $lockedDates->get($key);
                $this->upsertManual($employee, [
                    'work_date' => $key,
                    'clock_in' => null,
                    'clock_out' => null,
                    'status' => $locked['status'],
                    'notes' => $locked['notes'],
                    'source' => AttendanceRecord::SOURCE_SYSTEM,
                ], $correctionReason ?: 'Synchronisation congé/absence', $userId);
                $saved++;

                continue;
            }

            $status = $day['status'] ?? AttendanceRecord::STATUS_PRESENT;
            if (! array_key_exists($status, AttendanceRecord::STATUSES)) {
                continue;
            }

            $noTimes = in_array($status, [
                AttendanceRecord::STATUS_REST,
                AttendanceRecord::STATUS_LEAVE,
                AttendanceRecord::STATUS_SICK,
                AttendanceRecord::STATUS_ABSENT,
                AttendanceRecord::STATUS_HOLIDAY,
            ], true);

            $this->upsertManual($employee, [
                'work_date' => $key,
                'clock_in' => $noTimes ? null : ($day['clock_in'] ?? null),
                'clock_out' => $noTimes ? null : ($day['clock_out'] ?? null),
                'status' => $status,
                'notes' => $day['notes'] ?? $day['commentaire'] ?? null,
                'source' => AttendanceRecord::SOURCE_MANUAL,
            ], $correctionReason ?: 'Saisie mensuelle', $userId);
            $saved++;
        }

        return $saved;
    }
}
