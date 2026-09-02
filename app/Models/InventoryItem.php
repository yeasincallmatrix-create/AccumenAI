<?php

namespace App\Models;

use App\Models\Concerns\BranchScopedOrShared;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Inventory item / product. Supports stock_item, consumable, medicine,
 * raw_material, finished_good, spare_part, service_consumable and other.
 * Service businesses are not forced to keep stock (inventory stays optional
 * via capabilities). Money uses the DECIMAL(19,4) accounting convention.
 */
class InventoryItem extends Model
{
    use BranchScopedOrShared;
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'inventory_items';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:4',
            'selling_price' => 'decimal:4',
            'reorder_level' => 'decimal:4',
            'min_stock' => 'decimal:4',
            'max_stock' => 'decimal:4',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function taxGroup(): BelongsTo
    {
        return $this->belongsTo(TaxGroup::class, 'tax_group_id');
    }

    public function inventoryAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'inventory_account_id');
    }

    public function cogsAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'cogs_account_id');
    }

    public function salesAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'sales_account_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(InventoryStockLevel::class, 'item_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'item_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class, 'item_id');
    }

    public function requiresBatch(): bool
    {
        return in_array($this->item_type, ['medicine', 'raw_material', 'finished_good'], true);
    }

    /**
     * Total on-hand across all warehouses for the current scope.
     */
    public function onHand(): float
    {
        return round((float) $this->stockLevels()->sum('quantity'), 4);
    }
}
