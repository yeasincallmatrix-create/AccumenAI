<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A configurable grade scale at a specific hierarchy level:
 *
 *   GLOBAL DEFAULT
 *     → COUNTRY DEFAULT
 *     → EDUCATION SYSTEM DEFAULT
 *     → ACADEMIC LEVEL DEFAULT
 *     → INSTITUTE OVERRIDE
 *
 * Scope is encoded with nullable columns (mirrors the AssessmentType /
 * institute-analysis pattern): institute_id = NULL means a default any
 * institute inherits; institute_id set means an institute override. Together
 * with nullable country_id / education_system_id / academic_level_id the row
 * pins down exactly one ladder rung (see AcademicGradingService::resolveScale).
 *
 * Pure configuration: grade_scale_rows hold the actual bands. Never writes to
 * marks, aggregates or assessments.
 */
class GradeScale extends Model
{
    public const GPA_MODE_CREDIT_WEIGHTED = 'credit_weighted';

    public const GPA_MODE_EQUAL_WEIGHT = 'equal_weight';

    public const OPTIONAL_SUBJECT_GPA_INCLUDED = 'included';

    public const OPTIONAL_SUBJECT_GPA_EXCLUDED = 'excluded';

    public const GPA_MODES = [
        self::GPA_MODE_CREDIT_WEIGHTED,
        self::GPA_MODE_EQUAL_WEIGHT,
    ];

    public const OPTIONAL_SUBJECT_GPA_POLICIES = [
        self::OPTIONAL_SUBJECT_GPA_INCLUDED,
        self::OPTIONAL_SUBJECT_GPA_EXCLUDED,
    ];

    public const ROUNDING_HALF_UP = 'half_up';
    public const ROUNDING_HALF_DOWN = 'half_down';
    public const ROUNDING_FLOOR = 'floor';
    public const ROUNDING_CEIL = 'ceil';

    public const ROUNDING_MODES = [
        self::ROUNDING_HALF_UP,
        self::ROUNDING_HALF_DOWN,
        self::ROUNDING_FLOOR,
        self::ROUNDING_CEIL,
    ];

    public const MULTIPLE_OPTIONAL_SINGLE = 'single';
    public const MULTIPLE_OPTIONAL_BEST = 'best';
    public const MULTIPLE_OPTIONAL_SUM = 'sum';

    public const MULTIPLE_OPTIONAL_POLICIES = [
        self::MULTIPLE_OPTIONAL_SINGLE,
        self::MULTIPLE_OPTIONAL_BEST,
        self::MULTIPLE_OPTIONAL_SUM,
    ];

    protected $table = 'grade_scales';

    public $timestamps = true;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'status' => 'boolean',
            'marks_decimal_places' => 'integer',
            'percentage_decimal_places' => 'integer',
            'gpa_decimal_places' => 'integer',
            'cgpa_decimal_places' => 'integer',
            'optional_subject_bonus_threshold' => 'float',
            'optional_subject_bonus_enabled' => 'boolean',
            'max_gpa' => 'float',
            'multiple_optional_policy' => 'string',
        ];
    }

    /**
     * Rows visible for a given actor. Institute-scoped override rows are only
     * resolved by their own tenant's placement flow; but when an admin lists
     * or edits defaults we never want to expose institute rows through the
     * plain global query, so EVERY query respects the scope the caller asks
     * for via dedicated scopes (defaultScopes absent by design here).
     */
    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function educationSystem(): BelongsTo
    {
        return $this->belongsTo(EducationSystem::class);
    }

    public function academicLevel(): BelongsTo
    {
        return $this->belongsTo(AcademicLevel::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(GradeScaleRow::class)->orderBy('display_order')->orderBy('id');
    }

    public function activeRows(): HasMany
    {
        return $this->rows()->where('status', true);
    }

    /** True when this scale is a shared default (any institute inherits it). */
    public function isDefault(): bool
    {
        return $this->institute_id === null;
    }

    /** True when this scale is an institute override. */
    public function isInstituteOverride(): bool
    {
        return $this->institute_id !== null;
    }

    /** Human-readable ladder label, e.g. "Global Default" / "Country: Bangladesh". */
    public function scopeLabel(): string
    {
        if ($this->institute_id !== null) {
            $base = $this->institute?->name ?: "Institute #{$this->institute_id}";

            return $this->academic_level_id !== null
                ? $base.' · '.($this->academicLevel?->name ?: 'Level')
                : $base;
        }

        if ($this->academic_level_id !== null) {
            return 'Level: '.($this->academicLevel?->name ?: "#{$this->academic_level_id}");
        }
        if ($this->education_system_id !== null) {
            return 'System: '.($this->educationSystem?->name ?: "#{$this->education_system_id}");
        }
        if ($this->country_id !== null) {
            return 'Country: '.($this->country?->name ?: "#{$this->country_id}");
        }

        return 'Global Default';
    }

    /** Ladder precedence used by AcademicGradingService (lower = more specific). */
    public function ladderWeight(): int
    {
        if ($this->institute_id !== null) {
            return $this->academic_level_id !== null ? 1 : 2;
        }
        if ($this->academic_level_id !== null) {
            return 3;
        }
        if ($this->education_system_id !== null) {
            return 4;
        }
        if ($this->country_id !== null) {
            return 5;
        }

        return 6;
    }

    public function scopeDefaults(Builder $query): Builder
    {
        return $query->whereNull('institute_id');
    }

    public function scopeInstituteOverrides(Builder $query, int $instituteId): Builder
    {
        return $query->where('institute_id', $instituteId);
    }

    public function scopeStatus(Builder $query, bool $active = true): Builder
    {
        return $query->where('status', $active);
    }
}
