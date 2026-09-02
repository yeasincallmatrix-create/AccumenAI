<?php

namespace Tests\Feature;

use App\Models\AssetQrCode;
use App\Models\Country;
use App\Models\Institute;
use App\Services\Accounting\AccountingSetupService;
use App\Services\FixedAsset\FixedAssetQrService;
use App\Services\FixedAsset\FixedAssetService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * STEP 17 — Asset QR identification. Tokens are opaque and tenant-safe; a QR
 * from one tenant can never resolve in another tenant's context.
 */
class FixedAssetQrTest extends TestCase
{
    use DatabaseTransactions;

    private function country(): Country
    {
        return Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BDR', 'phone_code' => '880', 'status' => true]
        );
    }

    private function institute(): Institute
    {
        $country = $this->country();

        return Institute::create([
            'name' => 'QR Inst',
            'slug' => str()->slug('QR Inst-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'industry' => 'education',
            'status' => 'active',
        ]);
    }

    public function test_generate_creates_opaque_token_and_svg(): void
    {
        $institute = $this->institute();
        app(AccountingSetupService::class)->setupForInstitute($institute->id);

        $asset = app(FixedAssetService::class)->create($institute->id, null, [
            'name' => 'MRI Machine',
            'serial_number' => 'MRI-001',
        ]);

        $qr = app(FixedAssetQrService::class)->generate($asset);

        $this->assertNotEmpty($qr->token);
        $this->assertTrue($qr->isActive());

        $svg = app(FixedAssetQrService::class)->svg($qr->token);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);

        $dataUri = app(FixedAssetQrService::class)->dataUri($qr->token);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $dataUri);
    }

    public function test_scan_url_contains_only_token(): void
    {
        $institute = $this->institute();
        $asset = app(FixedAssetService::class)->create($institute->id, null, ['name' => 'Asset']);
        $qr = app(FixedAssetQrService::class)->generate($asset);

        $url = app(FixedAssetQrService::class)->scanUrl($qr->token);

        $this->assertStringContainsString($qr->token, $url);
        $this->assertStringNotContainsString((string) $asset->id, $url);
    }

    public function test_regenerate_revokes_previous_token(): void
    {
        $institute = $this->institute();
        $asset = app(FixedAssetService::class)->create($institute->id, null, ['name' => 'Asset']);
        $service = app(FixedAssetQrService::class);

        $first = $service->generate($asset);
        $second = $service->generate($asset);

        $this->assertNotSame($first->token, $second->token);
        $this->assertNull($service->resolve($first->token, $institute->id));
        $this->assertNotNull($service->resolve($second->token, $institute->id));
    }

    public function test_resolve_is_tenant_isolated(): void
    {
        $instituteA = $this->institute();
        $instituteB = $this->institute();

        $assetA = app(FixedAssetService::class)->create($instituteA->id, null, ['name' => 'Asset A']);
        $qr = app(FixedAssetQrService::class)->generate($assetA);

        $this->assertNotNull(app(FixedAssetQrService::class)->resolve($qr->token, $instituteA->id));
        $this->assertNull(app(FixedAssetQrService::class)->resolve($qr->token, $instituteB->id));
    }

    public function test_revoke_disables_token(): void
    {
        $institute = $this->institute();
        $asset = app(FixedAssetService::class)->create($institute->id, null, ['name' => 'Asset']);
        $service = app(FixedAssetQrService::class);

        $qr = $service->generate($asset);
        $service->revoke($qr);

        $this->assertNull($service->resolve($qr->token, $institute->id));
    }
}
