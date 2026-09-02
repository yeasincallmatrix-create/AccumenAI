<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One controlled rule of a promotion policy.
 *
 * Rule types are a closed enum; boolean rule types (overall_pass /
 * mandatory_pass) leave field/operator/value NULL. Numeric rule types compare
 * `field` against `value` using a controlled operator and yield pass_action /
 * fail_action.
 */
class PromotionPolicyRule extends Model
{
    protected $table = 'promotion_policy_rules';

    public $timestamps = true;

    protected $guarded = [];

    public const RULE_OVERALL_PASS = 'overall_pass';

    public const RULE_GPA_THRESHOLD = 'gpa_threshold';

    public const RULE_MAX_FAILED_SUBJECTS = 'max_failed_subjects';

    public const RULE_MANDATORY_PASS = 'mandatory_pass';

    public const RULE_CONDITIONAL = 'conditional';

    public const RULE_TYPES = [
        self::RULE_OVERALL_PASS,
        self::RULE_GPA_THRESHOLD,
        self::RULE_MAX_FAILED_SUBJECTS,
        self::RULE_MANDATORY_PASS,
        self::RULE_CONDITIONAL,
    ];

    /** Fields a numeric rule may compare. */
    public const FIELD_GPA = 'gpa';

    public const FIELD_FAILED_COUNT = 'failed_count';

    public const FIELD_INCOMPLETE_COUNT = 'incomplete_count';

    public const FIELDS = [
        self::FIELD_GPA,
        self::FIELD_FAILED_COUNT,
        self::FIELD_INCOMPLETE_COUNT,
    ];

    public const OPERATORS = ['>=', '>', '<=', '<', '==', '!='];

    public const ACTION_PROMOTED = 'promoted';

    public const ACTION_CONDITIONAL = 'conditional';

    public const ACTION_REPEAT = 'repeat';

    public const ACTION_NOT_PROMOTED = 'not_promoted';

    public const ACTION_COMPLETED = 'completed';

    public const ACTION_GRADUATED = 'graduated';

    public const ACTIONS = [
        self::ACTION_PROMOTED,
        self::ACTION_CONDITIONAL,
        self::ACTION_REPEAT,
        self::ACTION_NOT_PROMOTED,
        self::ACTION_COMPLETED,
        self::ACTION_GRADUATED,
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'status' => 'boolean',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(PromotionPolicy::class, 'policy_id');
    }

    public function isBooleanRule(): bool
    {
        return in_array($this->rule_type, [
            self::RULE_OVERALL_PASS,
            self::RULE_MANDATORY_PASS,
        ], true);
    }
}
