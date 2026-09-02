<?php

namespace App\Models;

use App\Models\Concerns\BranchScopedOrShared;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Education fee structure (Step 37). Defines the billable composition for an
 * optional branch / course / batch / academic year target (NULL = any /
 * institute-wide) plus the installment plan. The most specific active
 * structure is resolved when an enrollment is billed.
 */
class FeeStructure extends Model
{
    use BranchScopedOrShared;
    use TenantScoped;

    protected $table = 'fee_structures';

    public $timestamps = true;

    protected $fillable = [
        'institute_id',
        'branch_id',
        'course_id',
        'batch_id',
        'academic_year_id',
        'name',
        'installments_count',
        'installments_interval_days',
        'status',
        'billing_frequency',
        'auto_generate_monthly',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'installments_count' => 'integer',
            'installments_interval_days' => 'integer',
            'auto_generate_monthly' => 'boolean',
        ];
    }

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const FREQ_MONTHLY = 'monthly';
    public const FREQ_QUARTERLY = 'quarterly';
    public const FREQ_ANNUALLY = 'annually';
    public const FREQ_ONE_TIME = 'one_time';

    public const BILLING_FREQUENCIES = [
        self::FREQ_MONTHLY,
        self::FREQ_QUARTERLY,
        self::FREQ_ANNUALLY,
        self::FREQ_ONE_TIME,
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FeeStructureItem::class);
    }

    public function monthlyFeePeriods(): HasMany
    {
        return $this->hasMany(MonthlyFeePeriod::class);
    }

    public function total(): float
    {
        return round($this->items->sum('amount'), 2);
    }

    public function billingFrequencyLabel(): string
    {
        return match ($this->billing_frequency) {
            self::FREQ_MONTHLY => 'Monthly',
            self::FREQ_QUARTERLY => 'Quarterly',
            self::FREQ_ANNUALLY => 'Annually',
            self::FREQ_ONE_TIME => 'One-time',
            default => ucfirst($this->billing_frequency ?? 'monthly'),
        };
    }
}
