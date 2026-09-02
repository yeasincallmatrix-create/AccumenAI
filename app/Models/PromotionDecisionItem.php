<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-student promotion verdict within a decision cycle.
 *
 * `decision` is the promotion outcome (promoted / not_promoted / conditional /
 * repeat / completed / graduated / pending) and is deliberately separate from
 * final-result status and placement status.
 *
 * The item references the SOURCE placement (never updated or deleted by
 * promotion). target_class_grade_id / target_academic_group_id declare the
 * destination (same group, changed group, or none); next_placement_id is set
 * at approval time to the NEW placement row of the target academic year.
 */
class PromotionDecisionItem extends Model
{
    protected $table = 'promotion_decision_items';

    public $timestamps = true;

    protected $guarded = [];

    public const DECISION_PROMOTED = 'promoted';

    public const DECISION_NOT_PROMOTED = 'not_promoted';

    public const DECISION_CONDITIONAL = 'conditional';

    public const DECISION_REPEAT = 'repeat';

    public const DECISION_COMPLETED = 'completed';

    public const DECISION_GRADUATED = 'graduated';

    public const DECISION_PENDING = 'pending';

    public const DECISIONS = [
        self::DECISION_PROMOTED,
        self::DECISION_NOT_PROMOTED,
        self::DECISION_CONDITIONAL,
        self::DECISION_REPEAT,
        self::DECISION_COMPLETED,
        self::DECISION_GRADUATED,
        self::DECISION_PENDING,
    ];

    /** Outcomes that produce a next-year placement. */
    public const PLACEMENT_OUTCOMES = [
        self::DECISION_PROMOTED,
        self::DECISION_CONDITIONAL,
        self::DECISION_COMPLETED,
        self::DECISION_GRADUATED,
    ];

    protected function casts(): array
    {
        return [
            'reasons' => 'array',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(PromotionDecision::class, 'decision_id');
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(StudentAcademicPlacement::class, 'placement_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function targetClassGrade(): BelongsTo
    {
        return $this->belongsTo(ClassGrade::class, 'target_class_grade_id');
    }

    public function targetAcademicGroup(): BelongsTo
    {
        return $this->belongsTo(AcademicGroup::class, 'target_academic_group_id');
    }

    public function nextPlacement(): BelongsTo
    {
        return $this->belongsTo(StudentAcademicPlacement::class, 'next_placement_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'reviewed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'approved_by');
    }

    public function needsPlacement(): bool
    {
        return in_array($this->decision, self::PLACEMENT_OUTCOMES, true);
    }
}
