<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrPayrollItem extends Model
{
    use TenantScoped;

    protected $table = 'hr_payroll_items';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'meta' => 'array',
        ];
    }

    public function payroll(): BelongsTo { return $this->belongsTo(HrPayroll::class, 'payroll_id'); }
}
