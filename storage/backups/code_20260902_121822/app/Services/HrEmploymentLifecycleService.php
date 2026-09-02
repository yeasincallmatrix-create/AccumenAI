<?php

namespace App\Services;

use App\Models\HrEmployee;
use App\Models\HrEmploymentHistory;
use App\Models\HrEmploymentPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * HR-2 Employment Lifecycle.
 *
 * Handles transfers, promotions, resignations, terminations, reactivations
 * with immutable history (hr_employment_histories) and period tracking
 * (hr_employment_periods). Every change has effective_date, previous/new values,
 * reason/notes, changed_by, and is audited.
 *
 * Branch/tenant isolation enforced: institute_id / branch_id never from input;
 * branch-restricted actors cannot move employees outside their branch.
 */
class HrEmploymentLifecycleService
{
    public function __construct(private readonly HrAuditService $audit) {}

    public function recordJoining(HrEmployee $employee, ?int $actorId): void
    {
        $effective = $employee->joining_date?->format('Y-m-d') ?? now()->toDateString();

        HrEmploymentHistory::create([
            'institute_id' => $employee->institute_id,
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

        // Initial employment period
        $start = $employee->joining_date ?? now()->toDateString();
        HrEmploymentPeriod::create([
            'institute_id' => $employee->institute_id,
            'employee_id' => $employee->id,
            'start_date' => $start instanceof Carbon ? $start->format('Y-m-d') : $start,
            'status' => 'active',
            'started_by' => $actorId,
        ]);

        $this->audit->record($employee->institute_id, $actorId, 'hr_employment_joining', $employee->id, null, ['employee_id' => $employee->id, 'effective_date' => $effective]);
    }

    /**
     * Generic transfer: may change branch/department/designation/manager/type/status/salary_reference in one atomic operation.
     * Creates one history row capturing all previous/new values. Future reporting can filter by event_type if needed,
     * but a single row preserves the transactional nature of the transfer.
     */
    public function transfer(HrEmployee $employee, array $data, int $instituteId, ?int $actingBranchId, ?int $actorId): HrEmploymentHistory
    {
        abort_if((int) $employee->institute_id !== (int) $instituteId, 404);
        $this->assertBranchAccess($employee, $actingBranchId);

        $effectiveDate = $data['effective_date'] ?? now()->toDateString();

        // Resolve new values (fallback to current when not provided)
        $newBranchId = array_key_exists('branch_id', $data) ? $data['branch_id'] : $employee->branch_id;
        $newDepartmentId = array_key_exists('department_id', $data) ? $data['department_id'] : $employee->department_id;
        $newDesignationId = array_key_exists('designation_id', $data) ? $data['designation_id'] : $employee->designation_id;
        $newManagerId = array_key_exists('reporting_manager_id', $data) ? $data['reporting_manager_id'] : $employee->reporting_manager_id;
        $newEmploymentType = $data['employment_type'] ?? $employee->employment_type;
        $newEmploymentStatus = $data['employment_status'] ?? $employee->employment_status;
        $newSalaryReference = $data['salary_reference'] ?? null;

        // Branch-restricted actor cannot move employee outside their branch
        if ($actingBranchId !== null) {
            // Employee must currently belong to actor's branch (or be institute-wide)
            if ($employee->branch_id !== null && (int) $employee->branch_id !== (int) $actingBranchId) {
                abort(404);
            }
            // New branch must be actor's branch or null is not allowed for transfer outside scope
            if ($newBranchId !== null && (int) $newBranchId !== (int) $actingBranchId) {
                abort(403, 'You cannot transfer employees outside your branch.');
            }
        }

        $this->assertBranchOfInstitute($newBranchId, $instituteId);
        $this->assertDepartmentOfInstitute($newDepartmentId, $instituteId, $newBranchId);
        $this->assertDesignationOfInstitute($newDesignationId, $instituteId);
        $this->assertManagerOfInstitute($newManagerId, $instituteId, $employee->id);

        // Determine primary event_type for the history row
        $eventType = $this->resolveTransferEventType($employee, [
            'branch_id' => $newBranchId,
            'department_id' => $newDepartmentId,
            'designation_id' => $newDesignationId,
            'reporting_manager_id' => $newManagerId,
        ]);

        // No-op check
        $hasChange = $newBranchId !== $employee->branch_id
            || $newDepartmentId !== $employee->department_id
            || $newDesignationId !== $employee->designation_id
            || $newManagerId !== $employee->reporting_manager_id
            || $newEmploymentType !== $employee->employment_type
            || $newEmploymentStatus !== $employee->employment_status
            || filled($newSalaryReference);

        abort_if(! $hasChange, 422, 'No employment changes detected.');

        return DB::transaction(function () use ($employee, $instituteId, $actorId, $effectiveDate, $data, $eventType, $newBranchId, $newDepartmentId, $newDesignationId, $newManagerId, $newEmploymentType, $newEmploymentStatus, $newSalaryReference) {
            $history = HrEmploymentHistory::create([
                'institute_id' => $instituteId,
                'employee_id' => $employee->id,
                'event_type' => $eventType,
                'effective_date' => $effectiveDate,
                'previous_branch_id' => $employee->branch_id,
                'new_branch_id' => $newBranchId,
                'previous_department_id' => $employee->department_id,
                'new_department_id' => $newDepartmentId,
                'previous_designation_id' => $employee->designation_id,
                'new_designation_id' => $newDesignationId,
                'previous_manager_id' => $employee->reporting_manager_id,
                'new_manager_id' => $newManagerId,
                'previous_employment_type' => $employee->employment_type,
                'new_employment_type' => $newEmploymentType !== $employee->employment_type ? $newEmploymentType : null,
                'previous_employment_status' => $employee->employment_status,
                'new_employment_status' => $newEmploymentStatus !== $employee->employment_status ? $newEmploymentStatus : null,
                'previous_salary_reference' => null,
                'new_salary_reference' => $newSalaryReference,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'changed_by' => $actorId,
            ]);

            // Salary reference as separate history if provided
            if (filled($newSalaryReference)) {
                // Also log salary_reference event immutably if not already captured as transfer
                if ($eventType !== 'salary_reference') {
                    HrEmploymentHistory::create([
                        'institute_id' => $instituteId,
                        'employee_id' => $employee->id,
                        'event_type' => 'salary_reference',
                        'effective_date' => $effectiveDate,
                        'previous_salary_reference' => null,
                        'new_salary_reference' => $newSalaryReference,
                        'reason' => $data['reason'] ?? null,
                        'notes' => $data['notes'] ?? null,
                        'changed_by' => $actorId,
                    ]);
                }
            }

            // Bypass BranchScoped updating guard (which reverts branch_id) for legitimate transfers
            \Illuminate\Support\Facades\DB::table('hr_employees')->where('id', $employee->id)->update([
                'branch_id' => $newBranchId,
                'department_id' => $newDepartmentId,
                'designation_id' => $newDesignationId,
                'reporting_manager_id' => $newManagerId,
                'employment_type' => $newEmploymentType,
                'employment_status' => $newEmploymentStatus,
                'updated_by' => $actorId,
                'updated_at' => now(),
            ]);
            $employee->refresh();

            $this->audit->record($instituteId, $actorId, 'hr_employment_transfer', $employee->id, null, $history->getAttributes());

            return $history;
        });
    }

    public function promote(HrEmployee $employee, array $data, int $instituteId, ?int $actingBranchId, ?int $actorId): HrEmploymentHistory
    {
        abort_if((int) $employee->institute_id !== (int) $instituteId, 404);
        $this->assertBranchAccess($employee, $actingBranchId);

        $effectiveDate = $data['effective_date'] ?? now()->toDateString();
        $newDesignationId = $data['designation_id'] ?? $employee->designation_id;
        $newDepartmentId = $data['department_id'] ?? $employee->department_id;
        $title = $data['title'] ?? null;
        $salaryRef = $data['salary_reference'] ?? null;

        $this->assertDesignationOfInstitute($newDesignationId, $instituteId);
        $this->assertDepartmentOfInstitute($newDepartmentId, $instituteId, $employee->branch_id);

        abort_if($newDesignationId === $employee->designation_id && $newDepartmentId === $employee->department_id && blank($title) && blank($salaryRef), 422, 'No promotion changes detected.');

        return DB::transaction(function () use ($employee, $instituteId, $actorId, $effectiveDate, $data, $newDesignationId, $newDepartmentId, $title, $salaryRef) {
            $history = HrEmploymentHistory::create([
                'institute_id' => $instituteId,
                'employee_id' => $employee->id,
                'event_type' => $data['event_type'] ?? 'promotion',
                'effective_date' => $effectiveDate,
                'previous_department_id' => $employee->department_id,
                'new_department_id' => $newDepartmentId !== $employee->department_id ? $newDepartmentId : null,
                'previous_designation_id' => $employee->designation_id,
                'new_designation_id' => $newDesignationId !== $employee->designation_id ? $newDesignationId : $employee->designation_id,
                'new_salary_reference' => $salaryRef,
                'title' => $title,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'changed_by' => $actorId,
            ]);

            if (filled($salaryRef)) {
                HrEmploymentHistory::create([
                    'institute_id' => $instituteId,
                    'employee_id' => $employee->id,
                    'event_type' => 'salary_reference',
                    'effective_date' => $effectiveDate,
                    'new_salary_reference' => $salaryRef,
                    'changed_by' => $actorId,
                ]);
            }

            $employee->fill([
                'department_id' => $newDepartmentId,
                'designation_id' => $newDesignationId,
                'updated_by' => $actorId,
            ])->save();

            $this->audit->record($instituteId, $actorId, 'hr_employment_promotion', $employee->id, null, $history->getAttributes());

            return $history;
        });
    }

    public function resign(HrEmployee $employee, array $data, int $instituteId, ?int $actingBranchId, ?int $actorId): HrEmploymentHistory
    {
        abort_if((int) $employee->institute_id !== (int) $instituteId, 404);
        $this->assertBranchAccess($employee, $actingBranchId);
        abort_if(in_array($employee->employment_status, ['resigned', 'terminated'], true), 422, 'Employee already resigned or terminated.');

        $effectiveDate = $data['resignation_date'] ?? $data['effective_date'] ?? now()->toDateString();
        $lastWorking = $data['last_working_date'] ?? $effectiveDate;

        return DB::transaction(function () use ($employee, $instituteId, $actorId, $effectiveDate, $lastWorking, $data) {
            $history = HrEmploymentHistory::create([
                'institute_id' => $instituteId,
                'employee_id' => $employee->id,
                'event_type' => 'resignation',
                'effective_date' => $effectiveDate,
                'previous_employment_status' => $employee->employment_status,
                'new_employment_status' => 'resigned',
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'approval_status' => 'pending',
                'changed_by' => $actorId,
            ]);

            // For now, immediately reflect status change and close period; approval workflow can later approve/reject.
            // If strict pending workflow desired, comment out status change. We keep status as resigned immediately but track approval.
            $employee->fill(['employment_status' => 'resigned', 'updated_by' => $actorId])->save();

            // Close current active period
            $active = HrEmploymentPeriod::query()->where('employee_id', $employee->id)->where('institute_id', $instituteId)->where('status', 'active')->whereNull('end_date')->latest('id')->first();
            if ($active) {
                $active->fill([
                    'end_date' => $lastWorking,
                    'end_reason' => 'resigned',
                    'status' => 'closed',
                    'ended_by' => $actorId,
                ])->save();
            }

            $this->audit->record($instituteId, $actorId, 'hr_employment_resignation', $employee->id, null, $history->getAttributes());

            return $history;
        });
    }

    public function approveResignation(HrEmploymentHistory $history, int $instituteId, ?int $actorId, string $decision = 'approved'): HrEmploymentHistory
    {
        abort_if((int) $history->institute_id !== (int) $instituteId, 404);
        abort_if($history->event_type !== 'resignation', 422, 'Not a resignation record.');
        abort_if($history->approval_status !== 'pending', 422, 'Resignation already decided.');

        $newStatus = $decision === 'approved' ? 'approved' : 'rejected';

        return DB::transaction(function () use ($history, $instituteId, $actorId, $newStatus) {
            $follow = HrEmploymentHistory::create([
                'institute_id' => $instituteId,
                'employee_id' => $history->employee_id,
                'event_type' => $newStatus === 'approved' ? 'resignation_approved' : 'resignation_rejected',
                'effective_date' => now()->toDateString(),
                'approval_status' => $newStatus,
                'reason' => $history->reason,
                'notes' => $history->notes,
                'changed_by' => $actorId,
            ]);

            $history->fill(['approval_status' => $newStatus])->save();

            // If rejected, revert employment_status to active and reopen period (create new active period)
            if ($newStatus === 'rejected') {
                $emp = HrEmployee::withoutGlobalScopes()->whereKey($history->employee_id)->first();
                if ($emp && $emp->employment_status === 'resigned') {
                    $emp->fill(['employment_status' => 'active', 'updated_by' => $actorId])->save();
                    // Reopen period: create new active period starting now
                    HrEmploymentPeriod::create([
                        'institute_id' => $instituteId,
                        'employee_id' => $emp->id,
                        'start_date' => now()->toDateString(),
                        'status' => 'active',
                        'started_by' => $actorId,
                    ]);
                }
            }

            $this->audit->record($instituteId, $actorId, 'hr_employment_resignation_'.$newStatus, $history->employee_id, null, $follow->getAttributes());

            return $follow;
        });
    }

    public function terminate(HrEmployee $employee, array $data, int $instituteId, ?int $actingBranchId, ?int $actorId): HrEmploymentHistory
    {
        abort_if((int) $employee->institute_id !== (int) $instituteId, 404);
        $this->assertBranchAccess($employee, $actingBranchId);
        abort_if($employee->employment_status === 'terminated', 422, 'Employee already terminated.');

        $effectiveDate = $data['termination_date'] ?? $data['effective_date'] ?? now()->toDateString();

        return DB::transaction(function () use ($employee, $instituteId, $actorId, $effectiveDate, $data) {
            $history = HrEmploymentHistory::create([
                'institute_id' => $instituteId,
                'employee_id' => $employee->id,
                'event_type' => 'termination',
                'effective_date' => $effectiveDate,
                'previous_employment_status' => $employee->employment_status,
                'new_employment_status' => 'terminated',
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'changed_by' => $actorId,
            ]);

            $employee->fill(['employment_status' => 'terminated', 'updated_by' => $actorId])->save();

            $active = HrEmploymentPeriod::query()->where('employee_id', $employee->id)->where('institute_id', $instituteId)->where('status', 'active')->whereNull('end_date')->latest('id')->first();
            if ($active) {
                $active->fill([
                    'end_date' => $effectiveDate,
                    'end_reason' => 'terminated',
                    'status' => 'closed',
                    'ended_by' => $actorId,
                ])->save();
            }

            $this->audit->record($instituteId, $actorId, 'hr_employment_termination', $employee->id, null, $history->getAttributes());

            return $history;
        });
    }

    public function reactivate(HrEmployee $employee, array $data, int $instituteId, ?int $actingBranchId, ?int $actorId): HrEmploymentHistory
    {
        abort_if((int) $employee->institute_id !== (int) $instituteId, 404);
        $this->assertBranchAccess($employee, $actingBranchId);
        abort_if(! in_array($employee->employment_status, ['resigned', 'terminated', 'inactive', 'suspended'], true), 422, 'Only inactive/resigned/terminated/suspended employees can be reactivated.');

        $effectiveDate = $data['effective_date'] ?? now()->toDateString();

        return DB::transaction(function () use ($employee, $instituteId, $actorId, $effectiveDate, $data) {
            $history = HrEmploymentHistory::create([
                'institute_id' => $instituteId,
                'employee_id' => $employee->id,
                'event_type' => $data['event_type'] ?? 'reactivation',
                'effective_date' => $effectiveDate,
                'previous_employment_status' => $employee->employment_status,
                'new_employment_status' => 'active',
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'changed_by' => $actorId,
            ]);

            $employee->fill(['employment_status' => 'active', 'updated_by' => $actorId])->save();

            HrEmploymentPeriod::create([
                'institute_id' => $instituteId,
                'employee_id' => $employee->id,
                'start_date' => $effectiveDate,
                'status' => 'active',
                'started_by' => $actorId,
            ]);

            $this->audit->record($instituteId, $actorId, 'hr_employment_reactivation', $employee->id, null, $history->getAttributes());

            return $history;
        });
    }

    // ------------------- helpers

    private function assertBranchAccess(HrEmployee $employee, ?int $actingBranchId): void
    {
        if ($actingBranchId === null) {
            return;
        }
        if ($employee->branch_id !== null && (int) $employee->branch_id !== (int) $actingBranchId) {
            abort(404);
        }
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
        $mgr = HrEmployee::withoutGlobalScopes()->whereKey($managerId)->first();
        abort_if($mgr === null || (int) $mgr->institute_id !== (int) $instituteId, 422, 'Reporting manager does not belong to this institute.');
    }

    private function resolveTransferEventType(HrEmployee $employee, array $new): string
    {
        if (array_key_exists('branch_id', $new) && $new['branch_id'] !== $employee->branch_id) {
            return 'branch_transfer';
        }
        if (array_key_exists('department_id', $new) && $new['department_id'] !== $employee->department_id) {
            return 'department_transfer';
        }
        if (array_key_exists('designation_id', $new) && $new['designation_id'] !== $employee->designation_id) {
            return 'designation_change';
        }
        if (array_key_exists('reporting_manager_id', $new) && $new['reporting_manager_id'] !== $employee->reporting_manager_id) {
            return 'manager_change';
        }

        return 'branch_transfer';
    }
}
