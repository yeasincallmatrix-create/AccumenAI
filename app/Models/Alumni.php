<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Step 48 — Alumni Management.
 *
 * One institute-scoped alumni profile per student. The academic provenance of
 * the profile (graduation date, completion academic year, completed
 * course/batch) is derived from the existing approved promotion decision
 * (completed / graduated on a published final result) and stored as foreign
 * keys to the existing academic records — it is not a new source of truth.
 *
 * Institute-scoped (TenantScoped via institute_id); branch context is
 * inherited through the owning Student (BranchScoped), so a branch-restricted
 * user can only reach alumni of students in their branch.
 */
class Alumni extends Model
{
    use Concerns\TenantScoped;

    protected $table = 'alumni';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
    ];

    public const PROFILE_VISIBILITY_PRIVATE = 'private';

    public const PROFILE_VISIBILITY_PUBLIC = 'public';

    public const CONTACT_PREFERENCE_PRIVATE = 'private';

    public const CONTACT_PREFERENCE_EMAIL = 'email';

    public const CONTACT_PREFERENCE_PHONE = 'phone';

    public const CONTACT_PREFERENCE_BOTH = 'both';

    public $timestamps = true;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'graduation_date' => 'date',
        ];
    }

    /**
     * Branch isolation: a branch-restricted user only sees alumni whose
     * student belongs to their branch. No-op while BranchContext is disabled.
     */
    public function scopeInScope(Builder $query): Builder
    {
        return $query->whereHas('student');
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return static::query()->inScope()->where($field ?? $this->getRouteKeyName(), $value)->firstOrFail();
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function completionAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'completion_academic_year_id');
    }

    public function completedCourse(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'completed_course_id');
    }

    public function completedBatch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'completed_batch_id');
    }

    public function crmContact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'crm_contact_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'updated_by');
    }
}
