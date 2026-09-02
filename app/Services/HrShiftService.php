<?php

namespace App\Services;

use App\Models\HrWorkShift;
use Illuminate\Support\Facades\DB;

class HrShiftService
{
    public function __construct(private readonly HrAuditService $audit) {}

    public function create(array $data, int $instituteId, ?int $actorId): HrWorkShift
    {
        $this->assertBranchOfInstitute($data['branch_id'] ?? null, $instituteId);
        $this->assertEmployeeOfInstitute($data['employee_id'] ?? null, $instituteId);

        return DB::transaction(function () use ($data, $instituteId, $actorId) {
            $shift = HrWorkShift::create([
                'institute_id' => $instituteId,
                'branch_id' => $data['branch_id'] ?? null,
                'employee_id' => $data['employee_id'] ?? null,
                'name' => trim($data['name']),
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'grace_minutes' => (int) ($data['grace_minutes'] ?? 0),
                'working_days' => $data['working_days'] ?? [1, 2, 3, 4, 5],
                'is_active' => (bool) ($data['is_active'] ?? true),
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
            $this->audit->record($instituteId, $actorId, 'hr_shift_created', $shift->id, null, $shift->getAttributes());

            return $shift;
        });
    }

    public function update(HrWorkShift $shift, array $data, int $instituteId, ?int $actorId): HrWorkShift
    {
        abort_if((int) $shift->institute_id !== (int) $instituteId, 404);
        $old = $shift->getAttributes();

        return DB::transaction(function () use ($shift, $data, $actorId, $instituteId, $old) {
            $shift->fill([
                'branch_id' => $data['branch_id'] ?? $shift->branch_id,
                'employee_id' => $data['employee_id'] ?? $shift->employee_id,
                'name' => isset($data['name']) ? trim($data['name']) : $shift->name,
                'start_time' => $data['start_time'] ?? $shift->start_time,
                'end_time' => $data['end_time'] ?? $shift->end_time,
                'grace_minutes' => isset($data['grace_minutes']) ? (int) $data['grace_minutes'] : $shift->grace_minutes,
                'working_days' => $data['working_days'] ?? $shift->working_days,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $shift->is_active,
                'updated_by' => $actorId,
            ])->save();
            $this->audit->record($instituteId, $actorId, 'hr_shift_updated', $shift->id, $old, $shift->fresh()->getAttributes());

            return $shift->fresh();
        });
    }

    public function delete(HrWorkShift $shift, int $instituteId, ?int $actorId): void
    {
        abort_if((int) $shift->institute_id !== (int) $instituteId, 404);
        $old = $shift->getAttributes();
        DB::transaction(function () use ($shift, $instituteId, $actorId, $old) {
            $shift->delete();
            $this->audit->record($instituteId, $actorId, 'hr_shift_deleted', $old['id'], $old, null);
        });
    }

    private function assertBranchOfInstitute(?int $branchId, int $instituteId): void
    {
        if ($branchId === null) {
            return;
        }
        $exists = DB::table('branches')->where('id', $branchId)->where('institute_id', $instituteId)->exists();
        abort_if(! $exists, 422, 'Branch does not belong to this institute.');
    }

    private function assertEmployeeOfInstitute(?int $employeeId, int $instituteId): void
    {
        if ($employeeId === null) {
            return;
        }
        $exists = DB::table('hr_employees')->where('id', $employeeId)->where('institute_id', $instituteId)->exists();
        abort_if(! $exists, 422, 'Employee does not belong to this institute.');
    }
}
