<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Asset disposal (sale / scrap / retirement / write-off). The asset is never
 * deleted — it becomes disposed. gain_loss is the computed gain/loss posted.
 */
class AssetDisposal extends Model
{
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'asset_disposals';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'disposal_date' => 'date',
            'sale_proceeds' => 'decimal:4',
            'gain_loss' => 'decimal:4',
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
