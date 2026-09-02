<?php

namespace App\Services;

use App\Models\HrEmployee;
use App\Models\HrEmploymentHistory;
use App\Models\HrEmploymentPeriod;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * HR Employee master lifecycle (HR-1).
 *
 * - institute_id/branch_id never come from request input; callers pass resolved institute + acting branch.
 * - employee_code generated per-institute, tenant-safe, collision-safe via hr_employee_code_sequences (mirrors teacher_code_sequences).
 * - Branch + tenant isolation via global scopes on HrEmployee; service enforces cross-branch/tent checks + 404.
 * - Profile photo handling delegates to ProfileImageService (existing 350x450 JPEG).
 * - Every mutation audited via HrAuditService (module=hr, never secrets).
 */
class HrEmployeeService
{
    public function __construct(private readonly HrAuditService $audit) {}

    public function create(array $data, int $instituteId, ?int $branchId, ?int $actorId): HrEmployee
    {
        $this->assertBranchOfInstitute($branchId, $instituteId);
        $this->assertDepartmentOfInstitute($data['department_id'] ?? null, $instituteId, $branchId);
        $this->assertDesignationOfInstitute($data['designation_id'] ?? null, $instituteId);
        $this->assertManagerOfInstitute($data['reporting_manager_id'] ?? null, $instituteId);
        $this->assertInstituteUserOfInstitute($data['institute_user_id'] ?? null, $instituteId);
        $this->assertEmailPhoneUnique($data, $instituteId, null);

        return DB::transaction(function () use ($data, $instituteId, $branchId, $actorId) {
            $code = $this->nextEmployeeCode($instituteId);
            $displayName = $this->displayName($data);

            $employee = HrEmployee::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'department_id' => $data['department_id'] ?? null,
                'designation_id' => $data['designation_id'] ?? null,
                'reporting_manager_id' => $data['reporting_manager_id'] ?? null,
                'institute_user_id' => $data['institute_user_id'] ?? null,
                'employee_code' => $code,
                'first_name' => trim($data['first_name']),
                'middle_name' => isset($data['middle_name']) ? trim($data['middle_name']) : null,
                'last_name' => trim($data['last_name']),
                'display_name' => $displayName,
                'profile_photo' => $data['profile_photo'] ?? null,
                'gender' => $data['gender'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => isset($data['email']) ? strtolower(trim($data['email'])) : null,
                'address' => $data['address'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                'national_id' => $data['national_id'] ?? null,
                'passport_no' => $data['passport_no'] ?? null,
                'joining_date' => $data['joining_date'] ?? null,
                'employment_status' => $data['employment_status'] ?? 'active',
                'employment_type' => $data['employment_type'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            // HR-2: immutable joining history + initial employment period (preserve history even before lifecycle service existed)
            $effective = $employee->joining_date?->format('Y-m-d') ?? now()->toDateString();
            HrEmploymentHistory::create([
                'institute_id' => $instituteId,
                'employee_id' => $employee->id,
                'event_type' => 'joining',
                'effective_date' => $effective,
                'new_branch_id' => $employee->branch_id,
                'new_department_id' => $employee->department_id,
                'new_designation_id' => $employee->designation_id,
                'new_manager_id' => $employee->reporting_manager_id,
                'new_employment_type' => $employee->employment_type,
                'new_employment_status' => $employee->employment_status,
                'changed_by' => $actorId,
            ]);
            HrEmploymentPeriod::create([
                'institute_id' => $instituteId,
                'employee_id' => $employee->id,
                'start_date' => $employee->joining_date ?? now()->toDateString(),
                'status' => 'active',
                'started_by' => $actorId,
            ]);

            $this->audit->record($instituteId, $actorId, 'hr_employee_created', $employee->id, null, $this->snapshot($employee));

            return $employee;
        });
    }

    public function update(HrEmployee $employee, array $data, int $instituteId, ?int $branchId, ?int $actorId): HrEmployee
    {
        abort_if((int) $employee->institute_id !== (int) $instituteId, 404);
        if ($branchId !== null && $employee->branch_id !== null && (int) $employee->branch_id !== (int) $branchId) {
            abort(404);
        }

        $effectiveBranchId = $branchId ?? $employee->branch_id;

        $this->assertDepartmentOfInstitute($data['department_id'] ?? $employee->department_id, $instituteId, $effectiveBranchId);
        $this->assertDesignationOfInstitute($data['designation_id'] ?? $employee->designation_id, $instituteId);
        $managerId = array_key_exists('reporting_manager_id', $data) ? $data['reporting_manager_id'] : $employee->reporting_manager_id;
        $this->assertManagerOfInstitute($managerId, $instituteId, $employee->id);
        $this->assertInstituteUserOfInstitute($data['institute_user_id'] ?? $employee->institute_user_id, $instituteId);
        $this->assertEmailPhoneUnique($data, $instituteId, $employee->id);

        $old = $this->snapshot($employee);

        return DB::transaction(function () use ($employee, $data, $actorId, $instituteId, $old, $effectiveBranchId) {
            $displayName = $this->displayName(array_merge($employee->toArray(), $data));

            $fill = [
                'branch_id' => $effectiveBranchId,
                'department_id' => $data['department_id'] ?? $employee->department_id,
                'designation_id' => $data['designation_id'] ?? $employee->designation_id,
                'reporting_manager_id' => array_key_exists('reporting_manager_id', $data) ? $data['reporting_manager_id'] : $employee->reporting_manager_id,
                'institute_user_id' => array_key_exists('institute_user_id', $data) ? $data['institute_user_id'] : $employee->institute_user_id,
                'first_name' => isset($data['first_name']) ? trim($data['first_name']) : $employee->first_name,
                'middle_name' => array_key_exists('middle_name', $data) ? ($data['middle_name'] !== null ? trim($data['middle_name']) : null) : $employee->middle_name,
                'last_name' => isset($data['last_name']) ? trim($data['last_name']) : $employee->last_name,
                'display_name' => $displayName,
                'gender' => $data['gender'] ?? $employee->gender,
                'date_of_birth' => $data['date_of_birth'] ?? $employee->date_of_birth,
                'phone' => array_key_exists('phone', $data) ? $data['phone'] : $employee->phone,
                'email' => array_key_exists('email', $data) ? ($data['email'] !== null ? strtolower(trim($data['email'])) : null) : $employee->email,
                'address' => array_key_exists('address', $data) ? $data['address'] : $employee->address,
                'emergency_contact_name' => array_key_exists('emergency_contact_name', $data) ? $data['emergency_contact_name'] : $employee->emergency_contact_name,
                'emergency_contact_phone' => array_key_exists('emergency_contact_phone', $data) ? $data['emergency_contact_phone'] : $employee->emergency_contact_phone,
                'national_id' => array_key_exists('national_id', $data) ? $data['national_id'] : $employee->national_id,
                'passport_no' => array_key_exists('passport_no', $data) ? $data['passport_no'] : $employee->passport_no,
                'joining_date' => array_key_exists('joining_date', $data) ? $data['joining_date'] : $employee->joining_date,
                'employment_status' => $data['employment_status'] ?? $employee->employment_status,
                'employment_type' => array_key_exists('employment_type', $data) ? $data['employment_type'] : $employee->employment_type,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $employee->notes,
                'updated_by' => $actorId,
            ];

            if (array_key_exists('profile_photo', $data)) {
                $fill['profile_photo'] = $data['profile_photo'];
            }

            $employee->fill($fill)->save();
            $this->audit->record($instituteId, $actorId, 'hr_employee_updated', $employee->id, $old, $this->snapshot($employee->fresh()));

            return $employee->fresh();
        });
    }

    public function delete(HrEmployee $employee, int $instituteId, ?int $actorId, ?int $actingBranchId = null): void
    {
        abort_if((int) $employee->institute_id !== (int) $instituteId, 404);
        if ($actingBranchId !== null && $employee->branch_id !== null && (int) $employee->branch_id !== (int) $actingBranchId) {
            abort(404);
        }
        $old = $this->snapshot($employee);
        DB::transaction(function () use ($employee, $instituteId, $actorId, $old) {
            $employee->delete();
            $this->audit->record($instituteId, $actorId, 'hr_employee_deleted', $old['id'], $old, null);
        });
    }

    public function restore(int $employeeId, int $instituteId, ?int $actorId, ?int $actingBranchId = null): HrEmployee
    {
        $employee = HrEmployee::query()->withoutGlobalScopes()->withTrashed()->whereKey($employeeId)->first();
        abort_if($employee === null || (int) $employee->institute_id !== (int) $instituteId, 404);
        if ($actingBranchId !== null && $employee->branch_id !== null && (int) $employee->branch_id !== (int) $actingBranchId) {
            abort(404);
        }
        abort_if($employee->trashed() === false, 422, 'Employee is not deleted.');
        $employee->restore();
        $this->audit->record($instituteId, $actorId, 'hr_employee_restored', $employee->id, null, $this->snapshot($employee));

        return $employee;
    }

    private function displayName(array $data): string
    {
        $parts = array_filter([trim($data['first_name'] ?? ''), trim($data['middle_name'] ?? ''), trim($data['last_name'] ?? '')], fn ($p) => $p !== '');

        return implode(' ', $parts) ?: trim($data['first_name'] ?? 'Employee');
    }

    private function nextEmployeeCode(int $instituteId): string
    {
        return DB::transaction(function () use ($instituteId) {
            while (true) {
                $updated = DB::table('hr_employee_code_sequences')->where('institute_id', $instituteId)->increment('last_sequence');
                if ($updated > 0) {
                    $seq = (int) DB::table('hr_employee_code_sequences')->where('institute_id', $instituteId)->value('last_sequence');

                    return $this->formatCode($instituteId, $seq);
                }
                try {
                    DB::table('hr_employee_code_sequences')->insert([
                        'institute_id' => $instituteId,
                        'last_sequence' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    return $this->formatCode($instituteId, 1);
                } catch (QueryException $e) {
                    if ((int) $e->errorInfo[1] !== 1062) {
                        throw $e;
                    }
                }
            }
        });
    }

    private function formatCode(int $instituteId, int $seq): string
    {
        return 'EMP-'.str_pad((string) $instituteId, 3, '0', STR_PAD_LEFT).'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    private function snapshot(HrEmployee $employee): array
    {
        return $employee->getAttributes();
    }

    private function assertBranchOfInstitute(?int $branchId, int $instituteId): void
    {
        if ($branchId === null) {
            return;
        }
        $exists = DB::table('branches')->where('id', $branchId)->where('institute_id', $instituteId)->exists();
        abort_if(! $exists, 422, 'Branch does not belong to this institute.');
    }

    private function assertDepartmentOfInstitute(?int $departmentId, int $instituteId, ?int $branchId = null): void
    {
        if ($departmentId === null) {
            return;
        }
        $dept = DB::table('hr_departments')->where('id', $departmentId)->where('institute_id', $instituteId)->first();
        abort_if($dept === null, 422, 'Department does not belong to this institute.');
        // Branch-scoped department check
        if ($branchId !== null && $dept->branch_id !== null && (int) $dept->branch_id !== (int) $branchId) {
            abort(422, 'Department does not belong to your branch.');
        }
    }

    private function assertDesignationOfInstitute(?int $designationId, int $instituteId): void
    {
        if ($designationId === null) {
            return;
        }
        $exists = DB::table('hr_designations')->where('id', $designationId)->where('institute_id', $instituteId)->exists();
        abort_if(! $exists, 422, 'Designation does not belong to this institute.');
    }

    private function assertManagerOfInstitute(?int $managerId, int $instituteId, ?int $selfId = null): void
    {
        if ($managerId === null) {
            return;
        }
        abort_if($selfId !== null && (int) $managerId === (int) $selfId, 422, 'An employee cannot report to themselves.');
        $mgr = HrEmployee::query()->withoutGlobalScopes()->whereKey($managerId)->first();
        abort_if($mgr === null || (int) $mgr->institute_id !== (int) $instituteId, 422, 'Reporting manager does not belong to this institute.');
        // Cycle detection (walk up the chain)
        if ($selfId !== null) {
            $visited = [];
            $current = $managerId;
            while ($current !== null) {
                if ((int) $current === (int) $selfId) {
                    abort(422, 'Circular reporting hierarchy is not allowed.');
                }
                if (isset($visited[$current])) {
                    break;
                }
                $visited[$current] = true;
                $current = HrEmployee::query()->withoutGlobalScopes()->whereKey($current)->value('reporting_manager_id');
                $current = $current !== null ? (int) $current : null;
            }
        }
    }

    private function assertInstituteUserOfInstitute(?int $userId, int $instituteId): void
    {
        if ($userId === null) {
            return;
        }
        $exists = DB::table('institute_users')->where('id', $userId)->where('institute_id', $instituteId)->exists();
        abort_if(! $exists, 422, 'Linked user does not belong to this institute.');
    }

    private function assertEmailPhoneUnique(array $data, int $instituteId, ?int $ignoreId): void
    {
        // Email uniqueness is tenant-scoped case-insensitive when provided; phone likewise.
        // We treat uniqueness as per-institute to avoid cross-tenant false positives while keeping the index-free check.
        if (isset($data['email']) && filled($data['email'])) {
            $exists = HrEmployee::query()
                ->withoutGlobalScopes()
                ->where('institute_id', $instituteId)
                ->whereRaw('LOWER(email) = ?', [strtolower(trim($data['email']))])
                ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists();
            abort_if($exists, 422, 'Another employee with this email already exists.');
        }
        if (isset($data['phone']) && filled($data['phone'])) {
            $exists = HrEmployee::query()
                ->withoutGlobalScopes()
                ->where('institute_id', $instituteId)
                ->where('phone', trim($data['phone']))
                ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists();
            abort_if($exists, 422, 'Another employee with this phone already exists.');
        }
    }
}
