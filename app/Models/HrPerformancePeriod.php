<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrPerformancePeriod extends Model
{
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'hr_performance_periods';

    protected $guarded = [];

    public const STATUSES = ['draft', 'active', 'closed'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(HrPerformanceReview::class, 'period_id');
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}
