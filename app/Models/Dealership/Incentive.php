<?php

namespace App\Models\Dealership;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incentive extends Model
{
    use TenantScoped;

    protected $table = 'dealership_incentives';

    protected $guarded = [];

    protected $casts = [
        'threshold_amount' => 'decimal:2',
        'incentive_amount' => 'decimal:2',
        'earned_on' => 'date',
    ];

    public function salesForce(): BelongsTo
    {
        return $this->belongsTo(SalesForce::class, 'sales_force_id');
    }
}
