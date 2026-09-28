<?php

namespace App\Models\Dealership;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditLimit extends Model
{
    protected $table = 'dealership_credit_limits';

    protected $guarded = [];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'is_blocked' => 'boolean',
        'last_reviewed_at' => 'datetime',
    ];

    /** Pending Phase 1 entity models. */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
