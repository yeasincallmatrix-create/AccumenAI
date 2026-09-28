<?php

namespace App\Models\Dealership;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SrCommission extends Model
{
    use TenantScoped;

    protected $table = 'dealership_sr_commission';

    protected $guarded = [];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'base_amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function salesForce(): BelongsTo
    {
        return $this->belongsTo(SalesForce::class, 'sales_force_id');
    }
}
