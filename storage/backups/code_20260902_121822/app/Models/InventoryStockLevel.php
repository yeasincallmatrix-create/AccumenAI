<?php

namespace App\Models;

use App\Models\Concerns\BranchScopedOrShared;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cached stock balance per item + warehouse (+ optional batch). Always
 * rebuildable from inventory_movements; the weighted-average cost is carried
 * here and updated by the movement engine (single valuation method).
 */
class InventoryStockLevel extends Model
{
    use BranchScopedOrShared;
    use TenantScoped;

    protected $table = 'inventory_stock_levels';

    public $timestamps = true;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'avg_cost' => 'decimal:4',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(InventoryWarehouse::class, 'warehouse_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }

    /**
     * Valuation of this stock balance (quantity x weighted-average cost).
     */
    public function value(): float
    {
        return round((float) $this->quantity * (float) $this->avg_cost, 4);
    }
}
