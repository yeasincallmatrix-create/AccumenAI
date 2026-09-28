<?php

namespace App\Models\Dealership;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLink extends Model
{
    use TenantScoped;

    protected $table = 'dealership_inventory_links';

    protected $guarded = [];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    /** Phase 1 entity. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
