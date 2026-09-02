<?php

namespace App\Models;

use App\Models\Concerns\BranchScopedOrShared;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Physical location an asset can occupy (branch-scoped reference data).
 */
class AssetLocation extends Model
{
    use BranchScopedOrShared;
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'asset_locations';

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

    public function assets(): HasMany
    {
        return $this->hasMany(FixedAsset::class, 'location_id');
    }
}
