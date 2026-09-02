<?php

namespace App\Models;

use App\Models\Concerns\BranchScopedOrShared;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fixed asset master. Long-term asset owned/controlled by a tenant. The
 * depreciation ledger (asset_depreciation_entries) is the source of truth for
 * accumulated depreciation; this cached column must reconcile to it.
 */
class FixedAsset extends Model
{
    use BranchScopedOrShared;
    use SoftDeletes;
    use TenantScoped;

    public const STATUSES = [
        'draft', 'acquired', 'capitalized', 'active', 'under_maintenance',
        'fully_depreciated', 'disposed', 'sold', 'scrapped', 'impaired', 'retired',
    ];

    protected $table = 'fixed_assets';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'capitalization_date' => 'date',
            'depreciation_start_date' => 'date',
            'warranty_start' => 'date',
            'warranty_end' => 'date',
            'acquisition_cost' => 'decimal:4',
            'additional_capitalized_cost' => 'decimal:4',
            'residual_value' => 'decimal:4',
            'accumulated_depreciation' => 'decimal:4',
            'impairment_amount' => 'decimal:4',
            'depreciation_rate' => 'decimal:4',
            'useful_life_months' => 'integer',
            'total_units' => 'decimal:4',
            'is_depreciable' => 'boolean',
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
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(AssetLocation::class, 'location_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'vendor_party_id');
    }

    public function costComponents(): HasMany
    {
        return $this->hasMany(AssetCostComponent::class, 'asset_id');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(AssetTransfer::class, 'asset_id');
    }

    public function disposals(): HasMany
    {
        return $this->hasMany(AssetDisposal::class, 'asset_id');
    }

    public function impairments(): HasMany
    {
        return $this->hasMany(AssetImpairment::class, 'asset_id');
    }

    public function revaluations(): HasMany
    {
        return $this->hasMany(AssetRevaluation::class, 'asset_id');
    }

    public function depreciationEntries(): HasMany
    {
        return $this->hasMany(AssetDepreciationEntry::class, 'asset_id');
    }

    public function methodChanges(): HasMany
    {
        return $this->hasMany(AssetMethodChange::class, 'asset_id');
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(AssetQrCode::class, 'asset_id');
    }

    public function cost(): float
    {
        return round((float) $this->acquisition_cost + (float) $this->additional_capitalized_cost, 4);
    }

    public function depreciableBase(): float
    {
        return round(max(0.0, $this->cost() - (float) $this->residual_value), 4);
    }

    public function netBookValue(): float
    {
        return round($this->cost() - (float) $this->accumulated_depreciation - (float) $this->impairment_amount, 4);
    }
}
