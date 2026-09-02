<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One promotion evaluation cycle for ONE published final result.
 *
 * result_id always references a PUBLISHED academic_final_results row — the
 * frozen snapshot is the only promotion source. In-flight results can never
 * start a decision (enforced by PromotionLifecycleService).
 *
 * Status chain: pending → review → approved.
 *   - pending:   items just materialized from the published snapshot.
 *   - review:    operator is reviewing / adjusting per-student targets.
 *   - approved:  terminal — next-year placements created, items approved.
 *
 * At most one in-flight (pending / review) decision may exist for the same
 * published result (service-enforced).
 */
class PromotionDecision extends Model
{
    use TenantScoped;

    protected $table = 'promotion_decisions';

    public $timestamps = true;

    protected $guarded = [];

    public const STATUS_PENDING = 'pending';

    public const STATUS_REVIEW = 'review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_CANCELLED,
    ];

    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_REVIEW,
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
        return $this->belongsTo(PromotionPolicy::class, 'policy_id');
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(AcademicFinalResult::class, 'result_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'reviewed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PromotionDecisionItem::class, 'decision_id');
    }

    public function canStartReview(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canSendBackToReview(): bool
    {
        return $this->status === self::STATUS_REVIEW;
    }

    public function canApprove(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_REVIEW], true);
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_REVIEW], true);
    }
}
