<?php

namespace App\Services;

use App\Models\HrDepartment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Department management — tenant/branch scoped, ordered, active/inactive, soft-deletable.
 *
 * institute_id/branch_id never come from request input; callers pass the resolved institute + acting branch (null = institute-wide).
 * Branch managers are pinned to their branch at the controller level via ResolvesInstitute::actingBranchId().
 */
class HrDepartmentService
{
    public function __construct(private readonly HrAuditService $audit) {}

    public function list(int $instituteId, ?int $actingBranchId = null): Collection
    {
        return HrDepartment::query()
            ->when($actingBranchId !== null, fn ($q) => $q->where('branch_id', $actingBranchId))
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }

    public function create(array $data, int $instituteId, ?int $actorId, ?int $actingBranchId = null): HrDepartment
    {
        $branchId = $actingBranchId ?? ($data['branch_id'] ?? null);
        $this->assertBranchOfInstitute($branchId, $instituteId);
        $this->assertNoDuplicateName($data['name'], $instituteId, $branchId);

        if (! empty($data['parent_department_id'])) {
            $this->assertParentBelongsToScope((int) $data['parent_department_id'], $instituteId, $branchId);
        }

        return DB::transaction(function () use ($data, $instituteId, $branchId, $actorId) {
            $dept = HrDepartment::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'parent_department_id' => $data['parent_department_id'] ?? null,
                'name' => trim($data['name']),
                'code' => $data['code'] ?? null,
                'description' => $data['description'] ?? null,
                'display_order' => (int) ($data['display_order'] ?? 0),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->audit->record($instituteId, $actorId, 'hr_department_created', $dept->id, null, $dept->getAttributes());

            return $dept;
        });
    }

    public function update(HrDepartment $department, array $data, int $instituteId, ?int $actorId, ?int $actingBranchId = null): HrDepartment
    {
        abort_if((int) $department->institute_id !== (int) $instituteId, 404);
        if ($actingBranchId !== null && $department->branch_id !== null && (int) $department->branch_id !== (int) $actingBranchId) {
            abort(404);
        }

        $branchId = $actingBranchId ?? ($data['branch_id'] ?? $department->branch_id);
        $this->assertBranchOfInstitute($branchId, $instituteId);

        if (trim($data['name']) !== $department->name || (int) $branchId !== (int) $department->branch_id) {
            $this->assertNoDuplicateName($data['name'], $instituteId, $branchId, $department->id);
        }

        if (! empty($data['parent_department_id'])) {
            abort_if((int) $data['parent_department_id'] === (int) $department->id, 422, 'A department cannot be its own parent.');
            $this->assertParentBelongsToScope((int) $data['parent_department_id'], $instituteId, $branchId);
            $this->assertNoCycle($department->id, (int) $data['parent_department_id']);
        }

        $old = $department->getAttributes();

        return DB::transaction(function () use ($department, $data, $branchId, $actorId, $instituteId, $old) {
            $department->fill([
                'branch_id' => $branchId,
                'parent_department_id' => $data['parent_department_id'] ?? null,
                'name' => trim($data['name']),
                'code' => $data['code'] ?? null,
                'description' => $data['description'] ?? null,
                'display_order' => (int) ($data['display_order'] ?? $department->display_order),
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $department->is_active,
                'updated_by' => $actorId,
            ])->save();

            $this->audit->record($instituteId, $actorId, 'hr_department_updated', $department->id, $old, $department->fresh()->getAttributes());

            return $department->fresh();
        });
    }

    public function delete(HrDepartment $department, int $instituteId, ?int $actorId, ?int $actingBranchId = null): void
    {
        abort_if((int) $department->institute_id !== (int) $instituteId, 404);
        if ($actingBranchId !== null && $department->branch_id !== null && (int) $department->branch_id !== (int) $actingBranchId) {
            abort(404);
        }

        $old = $department->getAttributes();

        DB::transaction(function () use ($department, $instituteId, $actorId, $old) {
            $department->delete();
            $this->audit->record($instituteId, $actorId, 'hr_department_deleted', $old['id'], $old, null);
        });
    }

    public function toggleActive(HrDepartment $department, int $instituteId, ?int $actorId, ?int $actingBranchId = null): HrDepartment
    {
        abort_if((int) $department->institute_id !== (int) $instituteId, 404);
        if ($actingBranchId !== null && $department->branch_id !== null && (int) $department->branch_id !== (int) $actingBranchId) {
            abort(404);
        }
        $old = $department->getAttributes();
        $department->fill(['is_active' => ! $department->is_active, 'updated_by' => $actorId])->save();
        $this->audit->record($instituteId, $actorId, 'hr_department_status_toggled', $department->id, $old, $department->fresh()->getAttributes());

        return $department->fresh();
    }

    private function assertBranchOfInstitute(?int $branchId, int $instituteId): void
    {
        if ($branchId === null) {
            return;
        }
        $exists = DB::table('branches')->where('id', $branchId)->where('institute_id', $instituteId)->exists();
        abort_if(! $exists, 422, 'Branch does not belong to this institute.');
    }

    private function assertNoDuplicateName(string $name, int $instituteId, ?int $branchId, ?int $ignoreId = null): void
    {
        $exists = HrDepartment::query()
            ->where('institute_id', $instituteId)
            ->where('name', trim($name))
            ->when($branchId === null, fn ($q) => $q->whereNull('branch_id'), fn ($q) => $q->where('branch_id', $branchId))
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
        abort_if($exists, 422, 'A department with this name already exists in this scope.');
    }

    private function assertParentBelongsToScope(int $parentId, int $instituteId, ?int $branchId): void
    {
        $parent = HrDepartment::query()->withoutGlobalScopes()->whereKey($parentId)->first();
        abort_if($parent === null, 422, 'Parent department not found.');
        abort_if((int) $parent->institute_id !== (int) $instituteId, 422, 'Parent department does not belong to this institute.');
        // Allow parent to be institute-wide when child is branch-scoped, but not vice-versa.
        if ($branchId !== null && $parent->branch_id !== null && (int) $parent->branch_id !== (int) $branchId) {
            abort(422, 'Parent department must belong to the same branch or be institute-wide.');
        }
    }

    private function assertNoCycle(int $deptId, int $newParentId): void
    {
        $visited = [];
        $current = $newParentId;
        while ($current !== null) {
            if ((int) $current === (int) $deptId) {
                abort(422, 'Circular department hierarchy is not allowed.');
            }
            if (isset($visited[$current])) {
                break;
            }
            $visited[$current] = true;
            $parent = HrDepartment::query()->withoutGlobalScopes()->whereKey($current)->value('parent_department_id');
            $current = $parent !== null ? (int) $parent : null;
        }
    }
}
