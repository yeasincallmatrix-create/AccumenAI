<?php

namespace App\Models;

use App\Models\Concerns\BranchScopedOrShared;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Warehouse (Main / Branch / Pharmacy / Construction Store / Finished Goods ...).
 * Tenant-scoped; branch_id NULL = institute-wide warehouse.
 */
class InventoryWarehouse extends Model
{
    use BranchScopedOrShared;
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'inventory_warehouses';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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

    public function stockLevels(): HasMany
    {
        return $this->hasMany(InventoryStockLevel::class, 'warehouse_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'warehouse_id');
    }
}
