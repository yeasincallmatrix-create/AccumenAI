<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    protected $table = 'goods_receipt_items';

    protected $guarded = [];

    protected $casts = [
        'ordered_quantity' => 'decimal:4',
        'previously_received_quantity' => 'decimal:4',
        'received_quantity' => 'decimal:4',
        'rejected_quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
    ];

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'purchase_order_line_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
