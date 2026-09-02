<?php

namespace App\Models;

use App\Models\Concerns\BranchScoped;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * HR Employee — industry-neutral master profile.
 *
 * Tenant + branch isolation via TenantScoped + BranchScoped.
 * Soft-deletable to preserve historical identity (never hard destroyed).
 *
 * Code generation is per-institute via hr_employee_code_sequences (EMP-{inst padded 3}-{seq padded 5}).
 *
 * Future extension points (NOT implemented in HR-1): documents, attendance, leave, payroll, recruitment, performance, training, notifications, AI analytics — via separate tables referencing this model's id.
 *
 * Education compatibility: optionally links to an institute_users row (institute_user_id). Teacher/instructor accounts
 * remain the source of truth for login/teaching assignments (InstituteUser + TeacherProfile); an Employee row with
 * institute_user_id = that teacher's id represents the HR master side. No duplication of teacher identity fields;
 * the link is documented and may be used in HR-2 to reconcile records. Existing teacher functionality is untouched.
 */
class HrEmployee extends Model
{
    use BranchScoped;
    use Concerns\DeletesFiles;
    use SoftDeletes;
    use TenantScoped;

    protected $fileColumns = ['profile_photo'];

    protected $table = 'hr_employees';

    protected $guarded = [];

    public const EMPLOYMENT_STATUSES = ['active', 'inactive', 'suspended', 'resigned', 'terminated'];

    public const EMPLOYMENT_TYPES = ['full_time', 'part_time', 'contractual', 'permanent', 'temporary', 'intern', 'probation'];

    public const GENDERS = ['male', 'female', 'other'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'joining_date' => 'date',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(HrDepartment::class, 'department_id');
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(HrDesignation::class, 'designation_id');
    }

    public function reportingManager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reporting_manager_id');
    }

    public function instituteUser(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'institute_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'updated_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(HrEmploymentHistory::class, 'employee_id')->orderBy('effective_date')->orderBy('id');
    }

    public function periods(): HasMany
    {
        return $this->hasMany(HrEmploymentPeriod::class, 'employee_id')->orderBy('start_date')->orderBy('id');
    }

    public function currentPeriod(): ?HrEmploymentPeriod
    {
        return $this->periods()->where('status', 'active')->whereNull('end_date')->latest('id')->first();
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Search across code / name / phone / email.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }
        $like = '%'.trim($term).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('employee_code', 'like', $like)
                ->orWhere('first_name', 'like', $like)
                ->orWhere('middle_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('display_name', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('national_id', 'like', $like)
                ->orWhere('passport_no', 'like', $like);
        });
    }

    public function scopeOfDepartment(Builder $query, ?int $departmentId): Builder
    {
        return $departmentId ? $query->where('department_id', $departmentId) : $query;
    }

    public function scopeOfDesignation(Builder $query, ?int $designationId): Builder
    {
        return $designationId ? $query->where('designation_id', $designationId) : $query;
    }

    public function scopeOfBranch(Builder $query, ?int $branchId): Builder
    {
        return $branchId ? $query->where('branch_id', $branchId) : $query;
    }

    public function scopeOfStatus(Builder $query, ?string $status): Builder
    {
        return $status ? $query->where('employment_status', $status) : $query;
    }

    public function scopeOfType(Builder $query, ?string $type): Builder
    {
        return $type ? $query->where('employment_type', $type) : $query;
    }
}
