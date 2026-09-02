<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrLeaveBalance extends Model
{
    use TenantScoped;

    protected $table = 'hr_leave_balances';

    protected $guarded = [];

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'allocated' => 'decimal:1',
            'carry_forward' => 'decimal:1',
            'used' => 'decimal:1',
            'pending' => 'decimal:1',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(HrLeaveType::class, 'leave_type_id');
    }

    public function remaining(): float
    {
        return (float) $this->allocated + (float) $this->carry_forward - (float) $this->used - (float) $this->pending;
    }
}
