<?php

namespace App\Services;

use App\Models\HrAttendance;
use App\Models\HrEmployee;
use App\Models\HrLeaveApplication;
use App\Models\HrLeaveBalance;
use App\Models\HrLeaveType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HrLeaveService
{
    public function __construct(private readonly HrAuditService $audit) {}

    public function createType(array $data, int $instituteId, ?int $actorId): HrLeaveType
    {
        return DB::transaction(function () use ($data, $instituteId, $actorId) {
            $type = HrLeaveType::create([
                'institute_id' => $instituteId,
                'name' => trim($data['name']),
                'code' => strtolower(trim($data['code'])),
                'yearly_allowance' => (int) ($data['yearly_allowance'] ?? 0),
                'carry_forward' => (bool) ($data['carry_forward'] ?? false),
                'requires_approval' => (bool) ($data['requires_approval'] ?? true),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'display_order' => (int) ($data['display_order'] ?? 0),
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
            $this->audit->record($instituteId, $actorId, 'hr_leave_type_created', $type->id, null, $type->getAttributes());

            return $type;
        });
    }

    public function updateType(HrLeaveType $type, array $data, int $instituteId, ?int $actorId): HrLeaveType
    {
        abort_if((int) $type->institute_id !== (int) $instituteId, 404);
        $old = $type->getAttributes();

        return DB::transaction(function () use ($type, $data, $actorId, $instituteId, $old) {
            $type->fill([
                'name' => isset($data['name']) ? trim($data['name']) : $type->name,
                'code' => isset($data['code']) ? strtolower(trim($data['code'])) : $type->code,
                'yearly_allowance' => isset($data['yearly_allowance']) ? (int) $data['yearly_allowance'] : $type->yearly_allowance,
                'carry_forward' => array_key_exists('carry_forward', $data) ? (bool) $data['carry_forward'] : $type->carry_forward,
                'requires_approval' => array_key_exists('requires_approval', $data) ? (bool) $data['requires_approval'] : $type->requires_approval,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $type->is_active,
                'display_order' => isset($data['display_order']) ? (int) $data['display_order'] : $type->display_order,
                'updated_by' => $actorId,
            ])->save();
            $this->audit->record($instituteId, $actorId, 'hr_leave_type_updated', $type->id, $old, $type->fresh()->getAttributes());

            return $type->fresh();
        });
    }

    public function ensureBalance(int $employeeId, int $leaveTypeId, int $year, int $instituteId): HrLeaveBalance
    {
        return DB::transaction(function () use ($employeeId, $leaveTypeId, $year, $instituteId) {
            $balance = HrLeaveBalance::query()
                ->where('institute_id', $instituteId)
                ->where('employee_id', $employeeId)->where('leave_type_id', $leaveTypeId)->where('year', $year)
                ->lockForUpdate()->first();
            if ($balance) {
                return $balance;
            }

            $type = HrLeaveType::withoutGlobalScopes()->whereKey($leaveTypeId)->firstOrFail();
            $allocated = (float) $type->yearly_allowance;

            return HrLeaveBalance::create([
                'institute_id' => $instituteId,
                'employee_id' => $employeeId,
                'leave_type_id' => $leaveTypeId,
                'year' => $year,
                'allocated' => $allocated,
                'carry_forward' => 0,
                'used' => 0,
                'pending' => 0,
            ]);
        });
    }

    public function apply(array $data, int $instituteId, ?int $actorId): HrLeaveApplication
    {
        $employee = HrEmployee::withoutGlobalScopes()->whereKey($data['employee_id'])->firstOrFail();
        abort_if((int) $employee->institute_id !== (int) $instituteId, 404);
        $type = HrLeaveType::withoutGlobalScopes()->whereKey($data['leave_type_id'])->firstOrFail();
        abort_if((int) $type->institute_id !== (int) $instituteId, 404);
        abort_if(! $type->is_active, 422, 'Leave type is inactive.');

        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        abort_if($end->lt($start), 422, 'End date must be after start date.');
        $days = $this->daysCount($start, $end);
        $status = $type->requires_approval ? 'pending' : 'approved';

        // Overlap check: no overlapping pending/approved leave for same employee (tenant-scoped, locked)
        $overlap = HrLeaveApplication::query()
            ->where('institute_id', $instituteId)
            ->where('employee_id', $employee->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('end_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhere(function ($qq) use ($start, $end) {
                        $qq->where('start_date', '<=', $start->toDateString())->where('end_date', '>=', $end->toDateString());
                    });
            })->exists();
        abort_if($overlap, 422, 'Overlapping leave already exists for this period.');

        $attachment = null;
        if (! empty($data['attachment_file'])) {
            $attachment = $data['attachment_file']->store('hr-leaves/'.$instituteId, 'public');
        }

        return DB::transaction(function () use ($data, $instituteId, $actorId, $employee, $type, $start, $end, $days, $status, $attachment) {
            // Balance enforcement with row lock — prevents negative remaining via race
            $years = $this->splitDaysByYear($start, $end);
            foreach ($years as $year => $yearDays) {
                $balance = $this->ensureBalance($employee->id, $type->id, (int) $year, $instituteId);
                $balance->refresh();
                // Lock the balance row for update
                $locked = HrLeaveBalance::query()
                    ->where('institute_id', $instituteId)
                    ->where('employee_id', $employee->id)->where('leave_type_id', $type->id)->where('year', (int) $year)
                    ->lockForUpdate()->first();
                $available = (float) ($locked?->remaining() ?? $balance->remaining());
                if ($yearDays - $available > 0.0005) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'days_count' => "Insufficient leave balance for {$year}: available {$available}, requested {$yearDays}.",
                    ]);
                }
            }

            $app = HrLeaveApplication::create([
                'institute_id' => $instituteId,
                'branch_id' => $employee->branch_id,
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'days_count' => $days,
                'reason' => $data['reason'] ?? null,
                'attachment' => $attachment,
                'status' => $status,
                'applied_by' => $actorId,
            ]);

            // Update balance pending/used per year
            foreach ($years as $year => $yearDays) {
                $balance = $this->ensureBalance($employee->id, $type->id, (int) $year, $instituteId);
                if ($status === 'pending') {
                    $balance->increment('pending', $yearDays);
                } else {
                    $balance->increment('used', $yearDays);
                }
            }
            if ($status === 'approved') {
                $this->createAttendanceForLeave($app);
            }

            $this->audit->record($instituteId, $actorId, 'hr_leave_applied', $app->id, null, $app->getAttributes());

            return $app;
        });
    }

    public function decide(HrLeaveApplication $app, string $decision, ?string $reason, int $instituteId, ?int $actorId): HrLeaveApplication
    {
        abort_if((int) $app->institute_id !== (int) $instituteId, 404);
        abort_if($app->status !== 'pending', 422, 'Leave already decided.');

        return DB::transaction(function () use ($app, $decision, $reason, $instituteId, $actorId) {
            $oldStatus = $app->status;
            $start = Carbon::parse($app->start_date);
            $end = Carbon::parse($app->end_date);
            $years = $this->splitDaysByYear($start, $end);

            if ($decision === 'approved') {
                foreach ($years as $year => $yearDays) {
                    $balance = $this->ensureBalance($app->employee_id, $app->leave_type_id, (int) $year, $instituteId);
                    $locked = HrLeaveBalance::query()->where('institute_id', $instituteId)->where('employee_id', $app->employee_id)->where('leave_type_id', $app->leave_type_id)->where('year', (int) $year)->lockForUpdate()->first();
                    $available = (float) ($locked?->remaining() ?? $balance->remaining());
                    // When moving pending->used, pending is already reserved, so available check is not needed here (pending already deducted). But ensure not over-allocating used.
                }
                foreach ($years as $year => $yearDays) {
                    $balance = $this->ensureBalance($app->employee_id, $app->leave_type_id, (int) $year, $instituteId);
                    $balance->decrement('pending', $yearDays);
                    $balance->increment('used', $yearDays);
                }
                $app->fill(['status' => 'approved', 'approved_by' => $actorId, 'approved_at' => now()])->save();
                $this->createAttendanceForLeave($app);
            } elseif ($decision === 'rejected') {
                foreach ($years as $year => $yearDays) {
                    $balance = $this->ensureBalance($app->employee_id, $app->leave_type_id, (int) $year, $instituteId);
                    $balance->decrement('pending', $yearDays);
                }
                $app->fill(['status' => 'rejected', 'approved_by' => $actorId, 'approved_at' => now(), 'rejection_reason' => $reason])->save();
                // No attendance created
            } elseif ($decision === 'cancelled') {
                foreach ($years as $year => $yearDays) {
                    $balance = $this->ensureBalance($app->employee_id, $app->leave_type_id, (int) $year, $instituteId);
                    if ($oldStatus === 'pending') {
                        $balance->decrement('pending', $yearDays);
                    } elseif ($oldStatus === 'approved') {
                        $balance->decrement('used', $yearDays);
                    }
                }
                if ($oldStatus === 'approved') {
                    // Remove attendance leave entries created earlier
                    HrAttendance::where('employee_id', $app->employee_id)->where('institute_id', $instituteId)->whereBetween('attendance_date', [$app->start_date->toDateString(), $app->end_date->toDateString()])->where('status', 'leave')->delete();
                }
                $app->fill(['status' => 'cancelled', 'rejection_reason' => $reason])->save();
            }

            $this->audit->record($instituteId, $actorId, 'hr_leave_'.$decision, $app->id, ['status' => $oldStatus], $app->fresh()->getAttributes());

            return $app->fresh();
        });
    }

    private function daysCount(Carbon $start, Carbon $end): float
    {
        return (float) ($start->diffInDays($end) + 1);
    }

    /**
     * Split a leave period into days per calendar year.
     * Handles year-boundary leaves correctly (e.g. Dec 30 → Jan 2 => [2024=>2, 2025=>2]).
     * @return array<int, float> year => days
     */
    private function splitDaysByYear(Carbon $start, Carbon $end): array
    {
        $result = [];
        $cursor = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();
        while ($cursor->lte($endDay)) {
            $year = (int) $cursor->year;
            $yearEnd = $cursor->copy()->endOfYear()->startOfDay();
            $segmentEnd = $yearEnd->lt($endDay) ? $yearEnd : $endDay;
            $days = (float) ($cursor->diffInDays($segmentEnd) + 1);
            $result[$year] = ($result[$year] ?? 0) + $days;
            $cursor = $segmentEnd->copy()->addDay();
        }
        return $result;
    }

    private function createAttendanceForLeave(HrLeaveApplication $app): void
    {
        $start = Carbon::parse($app->start_date);
        $end = Carbon::parse($app->end_date);
        $employee = HrEmployee::withoutGlobalScopes()->whereKey($app->employee_id)->first();
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            HrAttendance::updateOrCreate(
                ['institute_id' => $app->institute_id, 'employee_id' => $app->employee_id, 'attendance_date' => $d->toDateString()],
                [
                    'branch_id' => $employee?->branch_id,
                    'status' => 'leave',
                    'source' => 'system',
                    'created_by' => $app->applied_by,
                ]
            );
        }
    }
}
