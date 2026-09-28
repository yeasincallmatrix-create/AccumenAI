<?php

namespace App\Models\Dealership;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SrOrder extends Model
{
    protected $table = 'dealership_sr_orders';

    protected $guarded = [];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SrOrderItem::class, 'sr_order_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(OrderApproval::class, 'sr_order_id');
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
