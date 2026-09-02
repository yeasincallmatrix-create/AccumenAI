<?php

namespace App\Services\FixedAsset;

use App\Models\AssetAuditLog;
use App\Models\AssetQrCode;
use App\Models\FixedAsset;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Str;

/**
 * Opaque-token QR identification for physical assets/machines (STEP 17).
 *
 * The QR encodes only a scan route (/asset/scan/{token}); the token is a random
 * UUID with no tenant/asset data. resolve() re-checks the tenant and returns the
 * asset so callers can apply authorization before exposing any details. A QR
 * from tenant A can never resolve to tenant B.
 */
class FixedAssetQrService
{
    public function generate(FixedAsset $asset, ?int $actorId = null): AssetQrCode
    {
        $this->revokeActive($asset, $actorId);

        $qr = AssetQrCode::create([
            'institute_id' => $asset->institute_id,
            'asset_id' => $asset->id,
            'token' => (string) Str::uuid(),
            'is_active' => true,
            'generated_at' => now(),
            'created_by' => $actorId,
        ]);

        AssetAuditLog::create([
            'institute_id' => $asset->institute_id,
            'asset_id' => $asset->id,
            'event' => 'qr_generated',
            'actor_id' => $actorId,
            'new_value' => ['token' => $qr->token],
            'created_at' => now(),
        ]);

        return $qr;
    }

    public function revokeActive(FixedAsset $asset, ?int $actorId = null): void
    {
        AssetQrCode::query()
            ->where('institute_id', $asset->institute_id)
            ->where('asset_id', $asset->id)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->update(['is_active' => false, 'revoked_at' => now()]);
    }

    public function revoke(AssetQrCode $qr, ?int $actorId = null): AssetQrCode
    {
        $qr->forceFill(['is_active' => false, 'revoked_at' => now()])->save();

        return $qr;
    }

    /**
     * Resolve an opaque token to its (asset, qr) pair. Returns null when the
     * token is unknown, inactive or revoked. Tenant isolation is enforced by
     * scoping the lookup to institute_id.
     */
    public function resolve(string $token, int $instituteId): ?array
    {
        $qr = AssetQrCode::query()
            ->where('institute_id', $instituteId)
            ->where('token', $token)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->first();

        if ($qr === null) {
            return null;
        }

        return ['qr' => $qr, 'asset' => $qr->asset];
    }

    /**
     * The URL encoded inside the QR — an opaque token, no sensitive data.
     */
    public function scanUrl(string $token): string
    {
        return rtrim((string) config('app.url'), '/').'/asset/scan/'.$token;
    }

    /**
     * Render the QR as SVG markup. Works without ext-gd (no image library required).
     */
    public function svg(string $token): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
        ]);

        $raw = (new QRCode($options))->render($this->scanUrl($token));

        if (str_starts_with($raw, 'data:image/svg+xml;base64,')) {
            return (string) base64_decode(substr($raw, strlen('data:image/svg+xml;base64,')));
        }

        return $raw;
    }

    /**
     * Render the QR as a data URI for direct embedding/labels.
     */
    public function dataUri(string $token): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->svg($token));
    }
}
