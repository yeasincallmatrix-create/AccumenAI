<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrPayrollAdjustment extends Model
{
    use TenantScoped;

    protected $table = 'hr_payroll_adjustments';
    protected $guarded = [];

    public const TYPES = ['bonus', 'deduction', 'allowance', 'correction', 'overtime', 'commission', 'tax'];
    public const STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'approved_at' => 'datetime',
        ];
    }

    public function institute(): BelongsTo { return $this->belongsTo(Institute::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function payroll(): BelongsTo { return $this->belongsTo(HrPayroll::class, 'payroll_id'); }
    public function period(): BelongsTo { return $this->belongsTo(HrPayrollPeriod::class, 'payroll_period_id'); }
    public function employee(): BelongsTo { return $this->belongsTo(HrEmployee::class, 'employee_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(InstituteUser::class, 'created_by'); }
}
