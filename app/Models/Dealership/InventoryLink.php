<?php

namespace App\Models\Dealership;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLink extends Model
{
    protected $table = 'dealership_inventory_links';

    protected $guarded = [];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    /** Pending Phase 1 entity models. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
