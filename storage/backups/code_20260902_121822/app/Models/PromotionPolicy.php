<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A promotion policy: configurable rule set for one academic context
 * (source year + class + optional group).
 *
 * A policy is pure configuration and never touches final results or
 * placements. It only declares which PUBLISHED academic_final_result is
 * evaluated and how (through promotion_policy_rules).
 *
 * Institute + branch scoping mirrors AcademicResultAggregationScheme:
 * branch_id NULL = whole-institute policy; otherwise branch-owned.
 */
class PromotionPolicy extends Model
{
    use TenantScoped;

    protected $table = 'promotion_policies';

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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'created_by');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(PromotionPolicyRule::class, 'policy_id')->orderBy('display_order')->orderBy('id');
    }

    public function activeRules(): HasMany
    {
        return $this->rules()->where('status', true);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(PromotionDecision::class, 'policy_id')->orderByDesc('id');
    }
}
