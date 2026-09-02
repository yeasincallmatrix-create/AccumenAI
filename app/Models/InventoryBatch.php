<?php

namespace App\Models;

use App\Models\Concerns\BranchScopedOrShared;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Batch / lot of an item in a warehouse (batch + expiry + lot tracking,
 * capability-driven for hospital/pharmacy/food/manufacturing).
 */
class InventoryBatch extends Model
{
    use BranchScopedOrShared;
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'inventory_batches';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'manufacture_date' => 'date',
            'expiry_date' => 'date',
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
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

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(InventoryWarehouse::class, 'warehouse_id');
    }

    public function isExpired(?string $asOf = null): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->lt(($asOf !== null ? Carbon::parse($asOf) : now())->toDateString());
    }
}
