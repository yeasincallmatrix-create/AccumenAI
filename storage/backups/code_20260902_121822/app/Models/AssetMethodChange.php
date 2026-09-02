<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Controlled depreciation-method / useful-life change. Historical posted
 * depreciation is never rewritten; future periods use the new assumptions from
 * the effective date.
 */
class AssetMethodChange extends Model
{
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'asset_method_changes';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'old_useful_life_months' => 'integer',
            'new_useful_life_months' => 'integer',
            'old_residual_value' => 'decimal:4',
            'new_residual_value' => 'decimal:4',
            'effective_date' => 'date',
            'approved_at' => 'datetime',
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
