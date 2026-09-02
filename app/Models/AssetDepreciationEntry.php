<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One asset's depreciation for one period. Immutable source of truth for the
 * accumulated-depreciation total. Unique (asset_id, period_start).
 */
class AssetDepreciationEntry extends Model
{
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'asset_depreciation_entries';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'opening_nbv' => 'decimal:4',
            'depreciation_amount' => 'decimal:4',
            'accumulated_depreciation' => 'decimal:4',
            'closing_nbv' => 'decimal:4',
            'rate' => 'decimal:4',
            'units' => 'decimal:4',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'asset_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AssetDepreciationRun::class, 'run_id');
    }
}
