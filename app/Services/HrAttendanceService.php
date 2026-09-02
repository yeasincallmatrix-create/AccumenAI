<?php

namespace App\Services;

use App\Models\HrAttendance;
use App\Models\HrAttendanceCorrection;
use App\Models\HrEmployee;
use App\Models\HrHoliday;
use App\Models\HrWorkShift;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HrAttendanceService
{
    public function __construct(private readonly HrAuditService $audit) {}

    public function resolveShift(HrEmployee $employee): ?HrWorkShift
    {
        // Employee-specific active shift first, then branch shift, then institute-wide active shift
        $shift = HrWorkShift::query()->where('employee_id', $employee->id)->where('is_active', true)->first();
        if ($shift) {
            return $shift;
        }
        if ($employee->branch_id) {
            $shift = HrWorkShift::query()->where('branch_id', $employee->branch_id)->whereNull('employee_id')->where('is_active', true)->first();
            if ($shift) {
                return $shift;
            }
        }

        return HrWorkShift::query()->where('institute_id', $employee->institute_id)->whereNull('branch_id')->whereNull('employee_id')->where('is_active', true)->first();
    }

    public function isHoliday(HrEmployee $employee, string $date): bool
    {
        $holiday = HrHoliday::query()
            ->where('institute_id', $employee->institute_id)
            ->where('holiday_date', $date)
            ->where('is_active', true)
            ->where(function ($q) use ($employee) {
                $q->whereNull('branch_id')->orWhere('branch_id', $employee->branch_id);
            })
            ->exists();

        return $holiday;
    }

    public function isWeekend(HrEmployee $employee, string $date, ?HrWorkShift $shift = null): bool
    {
        $shift ??= $this->resolveShift($employee);
        if (! $shift || ! $shift->working_days) {
            // Default Mon-Fri if no shift
            $working = [1, 2, 3, 4, 5];
        } else {
            $working = is_array($shift->working_days) ? $shift->working_days : json_decode($shift->working_days, true);
        }
        $weekday = (int) Carbon::parse($date)->dayOfWeekIso; // 1 Mon .. 7 Sun

        return ! in_array($weekday, $working, true);
    }

    public function computeDurations(?string $checkIn, ?string $checkOut, ?HrWorkShift $shift): array
    {
        if (! $checkIn || ! $checkOut) {
            return ['working_minutes' => null, 'late_minutes' => null, 'overtime_minutes' => null];
        }
        $in = Carbon::parse($checkIn);
        $out = Carbon::parse($checkOut);
        if ($out->lt($in)) {
            return ['working_minutes' => null, 'late_minutes' => null, 'overtime_minutes' => null];
        }
        $working = (int) $in->diffInMinutes($out);
        $late = null;
        $overtime = null;
        if ($shift) {
            $shiftStart = Carbon::parse($shift->start_time);
            $shiftEnd = Carbon::parse($shift->end_time);
            $grace = (int) $shift->grace_minutes;
            $expectedStart = $shiftStart->copy()->addMinutes($grace);
            // Late if check_in > expected start
            if ($in->gt($expectedStart)) {
                $late = (int) $expectedStart->diffInMinutes($in);
            } else {
                $late = 0;
            }
            $shiftDuration = (int) $shiftStart->diffInMinutes($shiftEnd);
            if ($working > $shiftDuration) {
                $overtime = $working - $shiftDuration;
            } else {
                $overtime = 0;
            }
        }

        return ['working_minutes' => $working, 'late_minutes' => $late, 'overtime_minutes' => $overtime];
    }

    public function mark(array $data, int $instituteId, ?int $actorId): HrAttendance
    {
        $employee = HrEmployee::withoutGlobalScopes()->whereKey($data['employee_id'])->firstOrFail();
        abort_if((int) $employee->institute_id !== (int) $instituteId, 404);

        $date = $data['attendance_date'];
        $status = $data['status'] ?? 'present';

        // Holiday/weekend auto-detection if status not explicitly set to those, but we respect manual status
        // If manual status is present but date is holiday, we keep present (manager may override). No auto-change.

        $shift = $this->resolveShift($employee);
        $durations = $this->computeDurations($data['check_in'] ?? null, $data['check_out'] ?? null, $shift);

        return DB::transaction(function () use ($data, $instituteId, $actorId, $employee, $shift, $durations, $status, $date) {
            $attendance = HrAttendance::updateOrCreate(
                ['institute_id' => $instituteId, 'employee_id' => $employee->id, 'attendance_date' => $date],
                [
                    'branch_id' => $employee->branch_id,
                    'shift_id' => $shift?->id,
                    'status' => $status,
                    'check_in' => $data['check_in'] ?? null,
                    'check_out' => $data['check_out'] ?? null,
                    'working_minutes' => $durations['working_minutes'],
                    'late_minutes' => $durations['late_minutes'],
                    'overtime_minutes' => $durations['overtime_minutes'],
                    'source' => $data['source'] ?? 'manual',
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]
            );
            $this->audit->record($instituteId, $actorId, 'hr_attendance_marked', $attendance->id, null, $attendance->getAttributes());

            return $attendance;
        });
    }

    public function requestCorrection(array $data, int $instituteId, ?int $actorId): HrAttendanceCorrection
    {
        $employee = HrEmployee::withoutGlobalScopes()->whereKey($data['employee_id'])->firstOrFail();
        abort_if((int) $employee->institute_id !== (int) $instituteId, 404);

        $attendance = null;
        if (! empty($data['attendance_id'])) {
            $attendance = HrAttendance::query()->withoutGlobalScopes()->whereKey($data['attendance_id'])->first();
            if ($attendance) {
                abort_if((int) $attendance->institute_id !== (int) $instituteId || (int) $attendance->employee_id !== (int) $employee->id, 404);
            }
        } else {
            $attendance = HrAttendance::query()->where('employee_id', $employee->id)->where('attendance_date', $data['correction_date'])->first();
        }

        return DB::transaction(function () use ($data, $instituteId, $actorId, $employee, $attendance) {
            $correction = HrAttendanceCorrection::create([
                'institute_id' => $instituteId,
                'attendance_id' => $attendance?->id,
                'employee_id' => $employee->id,
                'correction_date' => $data['correction_date'],
                'requested_status' => $data['requested_status'],
                'requested_check_in' => $data['requested_check_in'] ?? null,
                'requested_check_out' => $data['requested_check_out'] ?? null,
                'reason' => $data['reason'],
                'status' => 'pending',
                'requested_by' => $actorId,
            ]);
            $this->audit->record($instituteId, $actorId, 'hr_attendance_correction_requested', $correction->id, null, $correction->getAttributes());

            return $correction;
        });
    }

    public function decideCorrection(HrAttendanceCorrection $correction, string $decision, ?string $notes, int $instituteId, ?int $actorId): HrAttendanceCorrection
    {
        abort_if((int) $correction->institute_id !== (int) $instituteId, 404);
        abort_if($correction->status !== 'pending', 422, 'Correction already decided.');

        return DB::transaction(function () use ($correction, $decision, $notes, $instituteId, $actorId) {
            $correction->fill([
                'status' => $decision,
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ])->save();

            if ($decision === 'approved') {
                $employee = HrEmployee::withoutGlobalScopes()->whereKey($correction->employee_id)->firstOrFail();
                $shift = $this->resolveShift($employee);
                $durations = $this->computeDurations($correction->requested_check_in, $correction->requested_check_out, $shift);
                $attendance = HrAttendance::updateOrCreate(
                    ['institute_id' => $instituteId, 'employee_id' => $employee->id, 'attendance_date' => $correction->correction_date],
                    [
                        'branch_id' => $employee->branch_id,
                        'shift_id' => $shift?->id,
                        'status' => $correction->requested_status,
                        'check_in' => $correction->requested_check_in,
                        'check_out' => $correction->requested_check_out,
                        'working_minutes' => $durations['working_minutes'],
                        'late_minutes' => $durations['late_minutes'],
                        'overtime_minutes' => $durations['overtime_minutes'],
                        'source' => 'system',
                        'updated_by' => $actorId,
                    ]
                );
                if (! $correction->attendance_id) {
                    $correction->fill(['attendance_id' => $attendance->id])->save();
                }
            }

            $this->audit->record($instituteId, $actorId, 'hr_attendance_correction_'.$decision, $correction->id, null, $correction->getAttributes());

            return $correction->fresh();
        });
    }
}
