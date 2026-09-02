<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Controlled impairment — reduces carrying amount while preserving historical
 * cost and prior depreciation records.
 */
class AssetImpairment extends Model
{
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'asset_impairments';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'impairment_date' => 'date',
            'impairment_amount' => 'decimal:4',
            'recoverable_amount' => 'decimal:4',
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

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'journal_id');
    }
}
