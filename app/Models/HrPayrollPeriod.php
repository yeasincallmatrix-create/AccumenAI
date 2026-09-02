<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrPayrollPeriod extends Model
{
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'hr_payroll_periods';
    protected $guarded = [];

    public const STATUSES = ['draft', 'processing', 'approved', 'paid', 'cancelled', 'void'];
    public const PAY_FREQUENCIES = ['monthly', 'weekly', 'biweekly', 'fortnightly'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'total_gross' => 'decimal:4',
            'total_deductions' => 'decimal:4',
            'total_net' => 'decimal:4',
        ];
    }

    public function institute(): BelongsTo { return $this->belongsTo(Institute::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function currency(): BelongsTo { return $this->belongsTo(Currency::class, 'currency_id'); }
    public function payrolls(): HasMany { return $this->hasMany(HrPayroll::class, 'payroll_period_id'); }
    public function adjustments(): HasMany { return $this->hasMany(HrPayrollAdjustment::class, 'payroll_period_id'); }

    public function isDraft(): bool { return $this->status === 'draft'; }
    public function isProcessing(): bool { return $this->status === 'processing'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isPaid(): bool { return $this->status === 'paid'; }
    public function isCancelled(): bool { return in_array($this->status, ['cancelled', 'void'], true); }
    public function isFinalized(): bool { return in_array($this->status, ['approved', 'paid'], true); }
}
