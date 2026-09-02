<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrPayroll extends Model
{
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'hr_payrolls';
    protected $guarded = [];

    public const STATUSES = ['draft', 'approved', 'paid', 'cancelled', 'void'];

    protected function casts(): array
    {
        return [
            'present_days' => 'decimal:1',
            'leave_days' => 'decimal:1',
            'unpaid_leave_days' => 'decimal:1',
            'overtime_amount' => 'decimal:4',
            'gross_earnings' => 'decimal:4',
            'total_deductions' => 'decimal:4',
            'net_salary' => 'decimal:4',
            'earnings_snapshot' => 'array',
            'deductions_snapshot' => 'array',
            'calculation_snapshot' => 'array',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function institute(): BelongsTo { return $this->belongsTo(Institute::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function period(): BelongsTo { return $this->belongsTo(HrPayrollPeriod::class, 'payroll_period_id'); }
    public function employee(): BelongsTo { return $this->belongsTo(HrEmployee::class, 'employee_id'); }
    public function salaryAssignment(): BelongsTo { return $this->belongsTo(HrEmployeeSalaryAssignment::class, 'salary_assignment_id'); }
    public function currency(): BelongsTo { return $this->belongsTo(Currency::class, 'currency_id'); }
    public function journal(): BelongsTo { return $this->belongsTo(Journal::class, 'journal_id'); }
    public function paymentJournal(): BelongsTo { return $this->belongsTo(Journal::class, 'payment_journal_id'); }
    public function items(): HasMany { return $this->hasMany(HrPayrollItem::class, 'payroll_id'); }
    public function adjustments(): HasMany { return $this->hasMany(HrPayrollAdjustment::class, 'payroll_id'); }

    public function isFinalized(): bool { return in_array($this->status, ['approved', 'paid'], true); }
    public function isPaid(): bool { return $this->status === 'paid'; }
}
