<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Capability-gated revaluation event (off by default). Records previous vs new
 * carrying amount, difference, approval and accounting treatment.
 */
class AssetRevaluation extends Model
{
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'asset_revaluations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'revaluation_date' => 'date',
            'previous_carrying_amount' => 'decimal:4',
            'new_carrying_amount' => 'decimal:4',
            'difference' => 'decimal:4',
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

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'journal_id');
    }
}
