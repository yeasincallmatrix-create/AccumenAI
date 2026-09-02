<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One subject inside one academic assessment. Each row is independently
 * configured with its own components, so every subject can have a different
 * Written/Practical/Viva (etc.) split inside the same assessment.
 */
class AssessmentSubject extends Model
{
    protected $table = 'assessment_subjects';

    public $timestamps = true;

    protected $guarded = [];

    public const PASS_RULE_TOTAL_ONLY = 'total_only';

    public const PASS_RULE_MANDATORY_COMPONENTS = 'mandatory_components';

    public const PASS_RULE_BOTH = 'both';

    public const PASS_RULES = [
        self::PASS_RULE_TOTAL_ONLY,
        self::PASS_RULE_MANDATORY_COMPONENTS,
        self::PASS_RULE_BOTH,
    ];

    public const PASS_RULE_LABELS = [
        self::PASS_RULE_TOTAL_ONLY => 'Total Marks Only',
        self::PASS_RULE_MANDATORY_COMPONENTS => 'Mandatory Components',
        self::PASS_RULE_BOTH => 'Total + Mandatory Components',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
        ];
    }

    public function passRuleLabel(): string
    {
        return self::PASS_RULE_LABELS[$this->pass_rule] ?? $this->pass_rule;
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(AcademicAssessment::class, 'assessment_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class)->withTrashed();
    }

    public function components(): HasMany
    {
        return $this->hasMany(AssessmentSubjectComponent::class, 'assessment_subject_id')->orderBy('display_order')->orderBy('id');
    }

    /**
     * Total full mark derived from the component configuration (never stored).
     */
    public function totalFullMark(): float
    {
        return (float) $this->components()->sum('full_mark');
    }
}
