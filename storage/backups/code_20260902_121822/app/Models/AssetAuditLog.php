<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only asset event audit (creation, capitalization, depreciation,
 * method change, transfer, impairment, revaluation, disposal, QR events).
 */
class AssetAuditLog extends Model
{
    use TenantScoped;

    public const UPDATED_AT = null;

    protected $table = 'asset_audit_logs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'old_value' => 'array',
            'new_value' => 'array',
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
