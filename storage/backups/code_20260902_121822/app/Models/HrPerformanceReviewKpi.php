<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrPerformanceReviewKpi extends Model
{
    protected $table = 'hr_performance_review_kpis';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['weight' => 'decimal:2', 'score' => 'decimal:2', 'max_score' => 'decimal:2'];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(HrPerformanceReview::class, 'review_id');
    }

    public function kpi(): BelongsTo
    {
        return $this->belongsTo(HrKpi::class, 'kpi_id');
    }
}
