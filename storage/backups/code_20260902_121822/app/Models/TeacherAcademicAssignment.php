<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Teacher teaching workload / academic assignment (Step 36).
 *
 * Links a teacher (institute_users) to an academic year + branch and any of
 * course / subject / batch / class / group with a responsibility. History is
 * preserved: deactivating a teacher never deletes these rows; deleting an
 * academic reference nulls the FK instead of destroying the assignment.
 */
class TeacherAcademicAssignment extends Model
{
    use Concerns\BranchScoped;
    use Concerns\TenantScoped;

    protected $table = 'teacher_academic_assignments';

    protected $guarded = [];

    public const RESPONSIBILITIES = [
        'course_instructor',
        'subject_teacher',
        'class_teacher',
        'batch_coordinator',
        'practical_instructor',
        'examiner',
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    protected function casts(): array
    {
        return [
            'assigned_at' => 'date',
            'completed_at' => 'date',
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

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'institute_user_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function classGrade(): BelongsTo
    {
        return $this->belongsTo(ClassGrade::class, 'class_grade_id');
    }

    public function academicGroup(): BelongsTo
    {
        return $this->belongsTo(AcademicGroup::class, 'academic_group_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'updated_by');
    }
}
