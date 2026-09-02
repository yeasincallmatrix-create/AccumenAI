<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One final-result lifecycle record (a single publish cycle) for a policy.
 *
 * Status chain (Step 10):
 *   review → approved → locked → published
 *
 * - review:    the Step-9 derived preview is being verified; aside from this
 *              header nothing for the cycle is persisted yet.
 * - approved:  a reviewer signed off (approved_by / approved_at set).
 * - locked:    the snapshot (result_rows / result_students) was materialized
 *              from the backend-computed preview and the cycle is frozen. If
 *              the policy disables require_approval, lock is allowed straight
 *              from review.
 * - published: terminal — the snapshot is the official final result.
 *
 * At most one non-published (in-flight) result may exist per policy; the
 * lifecycle service enforces that rule (see AcademicFinalResultLifecycleService).
 */
class AcademicFinalResult extends Model
{
    use TenantScoped;

    protected $table = 'academic_final_results';

    public $timestamps = true;

    protected $guarded = [];

    public const STATUS_REVIEW = 'review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_LOCKED = 'locked';

    public const STATUS_PUBLISHED = 'published';

    public const STATUSES = [
        self::STATUS_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_LOCKED,
        self::STATUS_PUBLISHED,
    ];

    public const ACTIVE_STATUSES = [
        self::STATUS_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_LOCKED,
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'locked_at' => 'datetime',
            'published_at' => 'datetime',
            'computed_at' => 'datetime',
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

    public function policy(): BelongsTo
    {
        return $this->belongsTo(AcademicFinalResultPolicy::class, 'policy_id');
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(AcademicResultAggregationScheme::class, 'scheme_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'reviewed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'approved_by');
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'locked_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'published_by');
    }

    /**
     * Per-student GPA snapshot rows (written once at LOCK).
     */
    public function students(): HasMany
    {
        return $this->hasMany(AcademicFinalResultStudent::class, 'result_id');
    }

    /**
     * Per (placement, subject) snapshot rows (written once at LOCK).
     */
    public function rows(): HasMany
    {
        return $this->hasMany(AcademicFinalResultRow::class, 'result_id');
    }

    // ------------------------------------------------------------- Lifecycle helpers

    public function hasSnapshot(): bool
    {
        return $this->locked_at !== null;
    }

    public function canApprove(): bool
    {
        return $this->status === self::STATUS_REVIEW && (bool) $this->policy?->require_approval;
    }

    public function canSendBackToReview(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function canLock(): bool
    {
        if ($this->status === self::STATUS_APPROVED) {
            return true;
        }

        // Policies that skip the explicit approval step may lock straight from
        // review — but only if no approval was ever recorded.
        return $this->status === self::STATUS_REVIEW
            && ! (bool) $this->policy?->require_approval
            && $this->reviewed_at === null;
    }

    public function canPublish(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }
}
