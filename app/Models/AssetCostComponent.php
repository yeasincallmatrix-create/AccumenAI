<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A capitalizable cost component (purchase, freight, installation, customs,
 * setup, other). Acquisition cost must be traceable, never a single opaque sum.
 */
class AssetCostComponent extends Model
{
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'asset_cost_components';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
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
}
