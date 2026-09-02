<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnItem extends Model
{
    protected $table = 'sales_return_items';
    protected $guarded = [];
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'line_total' => 'decimal:4',
        ];
    }
    public function return(): BelongsTo { return $this->belongsTo(SalesReturn::class, 'return_id'); }
    public function invoiceItem(): BelongsTo { return $this->belongsTo(InvoiceItem::class, 'invoice_item_id'); }
    public function orderLine(): BelongsTo { return $this->belongsTo(SalesOrderLine::class, 'sales_order_line_id'); }
    public function inventoryItem(): BelongsTo { return $this->belongsTo(InventoryItem::class, 'inventory_item_id'); }
}
