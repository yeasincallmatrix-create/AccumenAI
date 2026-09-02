<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One student's academic placement for one academic year: which class/grade
 * they attended (and, optionally, which academic group/stream) and which
 * subjects they selected.
 *
 * Institute-scoped (TenantScoped via institute_id); branch context is
 * inherited through the owning Student (BranchScoped), so a branch-restricted
 * user can only reach placements of students in their branch.
 *
 * A student has at most one placement per academic year (unique constraint),
 * which is what preserves promotion history: 2026 → Class 8 and
 * 2027 → Class 9 exist side by side as separate rows.
 */
class StudentAcademicPlacement extends Model
{
    use Concerns\TenantScoped;
    use SoftDeletes;

    protected $table = 'student_academic_placements';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_TRANSFERRED = 'transferred';

    public const STATUS_DROPPED = 'dropped';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_COMPLETED,
        self::STATUS_TRANSFERRED,
        self::STATUS_DROPPED,
        self::STATUS_ARCHIVED,
    ];

    /**
     * Placement statuses that mark the student as officially exited from that
     * academic context. Exited placements are ineligible for NEW academic
     * operations (marks entry, final-result generation, batch enrollment)
     * while their historical rows and snapshots stay untouched.
     */
    public const EXITED_STATUSES = [
        self::STATUS_DROPPED,
        self::STATUS_TRANSFERRED,
        self::STATUS_ARCHIVED,
    ];

    public $timestamps = true;

    protected $guarded = [];

    protected $dates = ['deleted_at'];

    protected function casts(): array
    {
        return [
            'structure_snapshot' => 'array',
            'structure_version' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function classGrade(): BelongsTo
    {
        return $this->belongsTo(ClassGrade::class);
    }

    public function academicGroup(): BelongsTo
    {
        return $this->belongsTo(AcademicGroup::class);
    }

    public function selections(): HasMany
    {
        return $this->hasMany(StudentSubjectSelection::class, 'academic_placement_id');
    }

    /**
     * Restrict to placements whose student is visible in the current tenant +
     * branch context (Student is both TenantScoped and BranchScoped, so the
     * nested query applies both scopes automatically).
     */
    public function scopeInScope(Builder $query): Builder
    {
        return $query->whereHas('student');
    }
}
