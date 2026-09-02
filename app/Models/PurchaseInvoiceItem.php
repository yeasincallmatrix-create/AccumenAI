<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseInvoiceItem extends Model
{
    protected $table = 'purchase_invoice_items';
    protected $guarded = [];
    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'tax_rate' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'line_total' => 'decimal:4',
    ];

    public function invoice(): BelongsTo { return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id'); }
    public function purchaseOrderLine(): BelongsTo { return $this->belongsTo(PurchaseOrderLine::class, 'purchase_order_line_id'); }
    public function goodsReceiptItem(): BelongsTo { return $this->belongsTo(GoodsReceiptItem::class, 'goods_receipt_item_id'); }
    public function inventoryItem(): BelongsTo { return $this->belongsTo(InventoryItem::class, 'inventory_item_id'); }
    public function taxGroup(): BelongsTo { return $this->belongsTo(TaxGroup::class, 'tax_group_id'); }
}
