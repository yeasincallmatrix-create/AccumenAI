<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Opaque QR token for physical asset identification. The token carries no
 * tenant/asset data; resolution goes through FixedAssetQrService which verifies
 * tenant + authorization before exposing any details.
 */
class AssetQrCode extends Model
{
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'asset_qr_codes';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'generated_at' => 'datetime',
            'revoked_at' => 'datetime',
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

    public function isActive(): bool
    {
        return $this->is_active && $this->revoked_at === null;
    }
}
