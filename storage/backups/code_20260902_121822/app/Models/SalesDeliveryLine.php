<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesDeliveryLine extends Model
{
    protected $table = 'sales_delivery_lines';

    protected $guarded = [];

    protected $casts = [
        'ordered_quantity' => 'decimal:4',
        'previously_delivered_quantity' => 'decimal:4',
        'delivery_quantity' => 'decimal:4',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(SalesDelivery::class, 'delivery_id');
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class, 'order_line_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
