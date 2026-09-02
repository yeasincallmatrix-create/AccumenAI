<?php

namespace App\Models;

use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Academic assessment / exam instance for one academic year + class/grade
 * (optionally an academic group/stream).
 *
 * Tenant-scoped. Branch rule: branch_id NULL = whole-institute assessment that
 * every branch can see; otherwise the assessment belongs to one branch and is
 * only visible to that branch's users (or institute-wide users).
 *
 * A single assessment covers many subjects (assessment_subjects), each with its
 * own component configuration (assessment_subject_components). The total full
 * mark is always derived from those component rows — never stored.
 */
class AcademicAssessment extends Model
{
    use Concerns\TenantScoped;

    protected $table = 'academic_assessments';

    public $timestamps = true;

    protected $guarded = [];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_OPEN = 'open';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SCHEDULED,
        self::STATUS_OPEN,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected function casts(): array
    {
        return [
            'exam_date' => 'datetime',
            'display_order' => 'integer',
            'locked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('branch', function (Builder $builder) {
            if (BranchContext::enabled()) {
                $builder->where(function (Builder $query) {
                    $query->whereNull('branch_id')->orWhere('branch_id', BranchContext::id());
                });
            }
        });
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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

    public function assessmentType(): BelongsTo
    {
        return $this->belongsTo(AssessmentType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'created_by');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'locked_by');
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(AssessmentSubject::class, 'assessment_id')->orderBy('display_order')->orderBy('id');
    }

    /**
     * A locked assessment is frozen: marks entry, configuration edits and
     * deletion are refused until an explicitly permission-gated unlock
     * (Step 43). The lock is a flag, not a lifecycle status.
     */
    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }
}
