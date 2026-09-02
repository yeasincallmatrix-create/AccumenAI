<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrLeaveType extends Model
{
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'hr_leave_types';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'yearly_allowance' => 'integer',
            'carry_forward' => 'boolean',
            'requires_approval' => 'boolean',
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(HrLeaveBalance::class, 'leave_type_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(HrLeaveApplication::class, 'leave_type_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }
}
