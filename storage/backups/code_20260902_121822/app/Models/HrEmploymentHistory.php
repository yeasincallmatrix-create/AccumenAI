<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * HR Employment History — immutable ledger of every employment change.
 *
 * Tenant isolation via TenantScoped; no BranchScoped (history is queryable via employee's institute_id).
 * Historical safety: records are never updated/deleted; only inserted.
 */
class HrEmploymentHistory extends Model
{
    use TenantScoped;

    protected $table = 'hr_employment_histories';

    protected $guarded = [];

    public const EVENT_TYPES = [
        'joining',
        'branch_transfer',
        'department_transfer',
        'designation_change',
        'manager_change',
        'employment_type_change',
        'employment_status_change',
        'salary_reference',
        'promotion',
        'demotion',
        'resignation',
        'resignation_approved',
        'resignation_rejected',
        'termination',
        'reactivation',
        'rejoin',
    ];

    public const APPROVAL_STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function previousBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'previous_branch_id');
    }

    public function newBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'new_branch_id');
    }

    public function previousDepartment(): BelongsTo
    {
        return $this->belongsTo(HrDepartment::class, 'previous_department_id');
    }

    public function newDepartment(): BelongsTo
    {
        return $this->belongsTo(HrDepartment::class, 'new_department_id');
    }

    public function previousDesignation(): BelongsTo
    {
        return $this->belongsTo(HrDesignation::class, 'previous_designation_id');
    }

    public function newDesignation(): BelongsTo
    {
        return $this->belongsTo(HrDesignation::class, 'new_designation_id');
    }

    public function previousManager(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'previous_manager_id');
    }

    public function newManager(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'new_manager_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'changed_by');
    }
}
