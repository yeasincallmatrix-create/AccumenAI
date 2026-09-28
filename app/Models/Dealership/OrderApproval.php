<?php

namespace App\Models\Dealership;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderApproval extends Model
{
    use TenantScoped;

    protected $table = 'dealership_order_approvals';

    protected $guarded = [];

    public function order(): BelongsTo
    {
        return $this->belongsTo(SrOrder::class, 'sr_order_id');
    }
}
