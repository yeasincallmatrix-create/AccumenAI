<?php

namespace App\Models\Dealership;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SrOrderItem extends Model
{
    use TenantScoped;

    protected $table = 'dealership_sr_order_items';

    protected $guarded = [];

    protected $casts = [
        'qty' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(SrOrder::class, 'sr_order_id');
    }

    /** Phase 1 entity. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
