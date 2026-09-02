<?php

namespace App\Services;

use App\Models\HrDesignation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Designation management — per institute, optionally tied to a department.
 */
class HrDesignationService
{
    public function __construct(private readonly HrAuditService $audit) {}

    public function list(int $instituteId): Collection
    {
        return HrDesignation::query()->orderBy('display_order')->orderBy('name')->get();
    }

    public function create(array $data, int $instituteId, ?int $actorId): HrDesignation
    {
        $this->assertDepartmentOfInstitute($data['department_id'] ?? null, $instituteId);
        $this->assertNoDuplicateName($data['name'], $instituteId, $data['department_id'] ?? null);

        return DB::transaction(function () use ($data, $instituteId, $actorId) {
            $des = HrDesignation::create([
                'institute_id' => $instituteId,
                'department_id' => $data['department_id'] ?? null,
                'name' => trim($data['name']),
                'code' => $data['code'] ?? null,
                'description' => $data['description'] ?? null,
                'display_order' => (int) ($data['display_order'] ?? 0),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
            $this->audit->record($instituteId, $actorId, 'hr_designation_created', $des->id, null, $des->getAttributes());

            return $des;
        });
    }

    public function update(HrDesignation $designation, array $data, int $instituteId, ?int $actorId): HrDesignation
    {
        abort_if((int) $designation->institute_id !== (int) $instituteId, 404);
        $this->assertDepartmentOfInstitute($data['department_id'] ?? null, $instituteId);

        $newDept = $data['department_id'] ?? $designation->department_id;
        if (trim($data['name']) !== $designation->name || (int) $newDept !== (int) $designation->department_id) {
            $this->assertNoDuplicateName($data['name'], $instituteId, $newDept, $designation->id);
        }

        $old = $designation->getAttributes();

        return DB::transaction(function () use ($designation, $data, $actorId, $instituteId, $old) {
            $designation->fill([
                'department_id' => $data['department_id'] ?? null,
                'name' => trim($data['name']),
                'code' => $data['code'] ?? null,
                'description' => $data['description'] ?? null,
                'display_order' => (int) ($data['display_order'] ?? $designation->display_order),
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $designation->is_active,
                'updated_by' => $actorId,
            ])->save();
            $this->audit->record($instituteId, $actorId, 'hr_designation_updated', $designation->id, $old, $designation->fresh()->getAttributes());

            return $designation->fresh();
        });
    }

    public function delete(HrDesignation $designation, int $instituteId, ?int $actorId): void
    {
        abort_if((int) $designation->institute_id !== (int) $instituteId, 404);
        $old = $designation->getAttributes();
        DB::transaction(function () use ($designation, $instituteId, $actorId, $old) {
            $designation->delete();
            $this->audit->record($instituteId, $actorId, 'hr_designation_deleted', $old['id'], $old, null);
        });
    }

    public function toggleActive(HrDesignation $designation, int $instituteId, ?int $actorId): HrDesignation
    {
        abort_if((int) $designation->institute_id !== (int) $instituteId, 404);
        $old = $designation->getAttributes();
        $designation->fill(['is_active' => ! $designation->is_active, 'updated_by' => $actorId])->save();
        $this->audit->record($instituteId, $actorId, 'hr_designation_status_toggled', $designation->id, $old, $designation->fresh()->getAttributes());

        return $designation->fresh();
    }

    private function assertDepartmentOfInstitute(?int $departmentId, int $instituteId): void
    {
        if ($departmentId === null) {
            return;
        }
        $exists = DB::table('hr_departments')->where('id', $departmentId)->where('institute_id', $instituteId)->exists();
        abort_if(! $exists, 422, 'Department does not belong to this institute.');
    }

    private function assertNoDuplicateName(string $name, int $instituteId, ?int $departmentId, ?int $ignoreId = null): void
    {
        $exists = HrDesignation::query()
            ->where('institute_id', $instituteId)
            ->where('name', trim($name))
            ->when($departmentId === null, fn ($q) => $q->whereNull('department_id'), fn ($q) => $q->where('department_id', $departmentId))
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
        abort_if($exists, 422, 'A designation with this name already exists in this scope.');
    }
}
