<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrPerformanceReview extends Model
{
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'hr_performance_reviews';

    protected $guarded = [];

    public const STATUSES = ['draft', 'pending', 'submitted', 'manager_review', 'hr_review', 'approved', 'rejected'];

    protected function casts(): array
    {
        return ['review_date' => 'date', 'overall_score' => 'decimal:2', 'self_score' => 'decimal:2', 'manager_score' => 'decimal:2', 'hr_score' => 'decimal:2'];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'reviewer_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(HrPerformancePeriod::class, 'period_id');
    }

    public function kpis(): HasMany
    {
        return $this->hasMany(HrPerformanceReviewKpi::class, 'review_id');
    }

    public function canBeReviewedBy(HrEmployee $actor): bool
    {
        // reviewer or HR manager can review; simplified
        return (int) $this->reviewer_id === (int) $actor->id || $actor->institute_id === $this->institute_id;
    }
}
