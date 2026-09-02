<?php

namespace App\Models;

use App\Models\Concerns\BranchScopedOrShared;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Physical stock count workflow: draft -> counting -> review -> approved ->
 * posted. Once posted, the count lines are immutable (approval/posting are
 * permission-gated).
 */
class InventoryCount extends Model
{
    use BranchScopedOrShared;
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'inventory_counts';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'counted_at' => 'date',
            'posted_at' => 'datetime',
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

    public function items(): HasMany
    {
        return $this->hasMany(InventoryCountItem::class, 'count_id');
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }
}
