<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Final-result policy for one aggregation scheme (Step 10).
 *
 * A policy is PER-CONTEXT configuration for how the derived Step-9 result is
 * turned into an official final result: absent re-normalization on/off, an
 * optional grade-scale override, and whether locking requires an explicit
 * approval. It never touches marks, assessments, weights or the derivation —
 * those stay in the aggregation scheme + Step-9 services.
 *
 * Tenant + branch scoping mirrors AcademicResultAggregationScheme: branch_id
 * NULL = whole-institute policy; otherwise it belongs to one branch. Exactly
 * one policy exists per scheme (unique scheme_id).
 */
class AcademicFinalResultPolicy extends Model
{
    use TenantScoped;

    protected $table = 'academic_final_result_policies';

    public $timestamps = true;

    protected $guarded = [];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_ARCHIVED,
    ];

    protected function casts(): array
    {
        return [
            'absent_renormalization' => 'boolean',
            'require_approval' => 'boolean',
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

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(AcademicResultAggregationScheme::class, 'scheme_id');
    }

    /**
     * Optional per-context grade-scale override; NULL keeps the ladder.
     */
    public function gradeScale(): BelongsTo
    {
        return $this->belongsTo(GradeScale::class, 'grade_scale_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'created_by');
    }

    /**
     * Every lifecycle record of this policy (published + in-flight), newest first.
     */
    public function results(): HasMany
    {
        return $this->hasMany(AcademicFinalResult::class, 'policy_id')->orderByDesc('id');
    }
}
