<?php

namespace App\Models;

use App\Models\Concerns\BranchScopedOrShared;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyFeePeriod extends Model
{
    use BranchScopedOrShared;
    use TenantScoped;

    protected $table = 'monthly_fee_periods';

    public $timestamps = true;

    protected $fillable = [
        'institute_id',
        'branch_id',
        'fee_structure_id',
        'student_id',
        'enrollment_id',
        'period_month',
        'invoice_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'date',
        ];
    }

    public const STATUS_PENDING = 'pending';
    public const STATUS_GENERATED = 'generated';
    public const STATUS_PAID = 'paid';
    public const STATUS_OVERDUE = 'overdue';

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Training\Enrollment::class, 'enrollment_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeGenerated(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_GENERATED);
    }

    public function scopeForPeriod(Builder $query, string $month): Builder
    {
        return $query->where('period_month', $month);
    }

    public function isGenerated(): bool
    {
        return $this->status === self::STATUS_GENERATED;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}
