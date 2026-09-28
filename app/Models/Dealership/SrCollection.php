<?php

namespace App\Models\Dealership;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SrCollection extends Model
{
    protected $table = 'dealership_sr_collections';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'collected_on' => 'date',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(SrOrder::class, 'sr_order_id');
    }

    /** Pending Phase 1 entity models. */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /** Pending Phase 1 entity models. */
    public function salesForce(): BelongsTo
    {
        return $this->belongsTo(SalesForce::class, 'sales_force_id');
    }
}
