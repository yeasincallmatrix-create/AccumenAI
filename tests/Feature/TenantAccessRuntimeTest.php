<?php

namespace Tests\Feature;

use App\Models\FeatureRegistry;
use App\Models\Institute;
use App\Models\InstituteFeatureOverride;
use App\Models\PackageFeature;
use App\Models\PlatformAdmin;
use App\Models\SubscriptionPackage;
use App\Models\TenantAccessDenial;
use App\Models\TenantAccessGrant;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * B74: tenant_access_grants + tenant_access_denials runtime wiring.
 *
 * Contract §9 resolution: (Base ∪ Overrides ∪ Grants) − Denials.
 * Grants are additive (Gate 4), denials are subtractive and win last (Gate 5).
 * Only rows with status='active' and expires_at NULL/future apply.
 */
class TenantAccessRuntimeTest extends TestCase
{
    use DatabaseTransactions;

    private ModuleAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('tenant_access_grants')
            || ! Schema::hasTable('tenant_access_denials')
            || ! Schema::hasTable('feature_registry')
            || ! Schema::hasTable('package_features')) {
            $this->markTestSkipped('Required tables do not exist.');
        }

        $this->service = app(ModuleAccessService::class);

        if (FeatureRegistry::count() === 0) {
            (new \Database\Seeders\FeatureRegistrySeeder)->run();
        }
        if (PackageFeature::count() === 0) {
            (new \Database\Seeders\PackageFeatureSeeder)->run();
        }
    }

    private function admin(): PlatformAdmin
    {
        return PlatformAdmin::firstOrReuseForTests([
            'first_name' => 'Runtime',
            'last_name' => 'Admin',
            'email' => 'runtime-admin-'.uniqid().'@example.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function makeInstitute(string $packageSlug = 'advanced'): Institute
    {
        $pkg = SubscriptionPackage::whereRaw('LOWER(slug) = ?', [strtolower($packageSlug)])->firstOrFail();

        $instId = DB::table('institutes')->insertGetId([
            'name' => 'Runtime Test '.uniqid(),
            'slug' => 'runtime-test-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
            'package_id' => $pkg->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('institute_subscriptions')->insert([
            'institute_id' => $instId,
            'package_id' => $pkg->id,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);

        return Institute::withoutGlobalScopes()->find($instId);
    }

    private function setPackageFeature(Institute $inst, string $featureKey, bool $enabled): void
    {
        $row = PackageFeature::where('package_id', $inst->package_id)
            ->where('feature_key', $featureKey)
            ->first();

        if ($row) {
            $row->update(['enabled' => $enabled]);
        } else {
            PackageFeature::create([
                'package_id' => $inst->package_id,
                'feature_key' => $featureKey,
                'enabled' => $enabled,
            ]);
        }

        $this->service->flushFeatureCache($inst->id);
    }

    private function grant(Institute $inst, string $type, string $key, array $extra = []): TenantAccessGrant
    {
        return TenantAccessGrant::create(array_merge([
            'institute_id' => $inst->id,
            'grant_type' => $type,
            'grant_key' => $key,
            'granted_by' => $this->admin()->id,
            'status' => 'active',
        ], $extra));
    }

    private function deny(Institute $inst, string $type, string $key, array $extra = []): TenantAccessDenial
    {
        return TenantAccessDenial::create(array_merge([
            'institute_id' => $inst->id,
            'deny_type' => $type,
            'deny_key' => $key,
            'denied_by' => $this->admin()->id,
            'reason' => 'Runtime test denial',
            'status' => 'active',
        ], $extra));
    }

    public function test_grant_feature_makes_it_enabled(): void
    {
        $inst = $this->makeInstitute();
        $this->setPackageFeature($inst, 'medical.pharmacy', false);
        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));

        $this->grant($inst, 'feature', 'medical.pharmacy');

        $this->assertTrue($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));
    }

    public function test_grant_module_enables_all_features_in_module(): void
    {
        $inst = $this->makeInstitute();
        $this->setPackageFeature($inst, 'medical.pharmacy', false);
        $this->setPackageFeature($inst, 'medical.laboratory', false);
        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));
        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.laboratory'));

        $this->grant($inst, 'module', 'medical');

        $this->assertTrue($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));
        $this->assertTrue($this->service->isFeatureEnabled($inst, 'medical.laboratory'));
    }

    public function test_grant_respects_expires_at(): void
    {
        $inst = $this->makeInstitute();
        $this->setPackageFeature($inst, 'medical.pharmacy', false);
        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));

        $this->grant($inst, 'feature', 'medical.pharmacy', [
            'expires_at' => now()->addDays(30),
        ]);

        $this->assertTrue($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));
    }

    public function test_denial_feature_disables_it(): void
    {
        $inst = $this->makeInstitute();
        $this->setPackageFeature($inst, 'medical.pharmacy', true);
        $this->assertTrue($this->service->isEnabled($inst, 'medical'));
        $this->assertTrue($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));

        $this->deny($inst, 'feature', 'medical.pharmacy');

        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));
    }

    public function test_denial_module_disables_all_features_in_module(): void
    {
        $inst = $this->makeInstitute();
        $this->setPackageFeature($inst, 'medical.pharmacy', true);
        $this->setPackageFeature($inst, 'medical.laboratory', true);
        $this->assertTrue($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));
        $this->assertTrue($this->service->isFeatureEnabled($inst, 'medical.laboratory'));

        $this->deny($inst, 'module', 'medical');

        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));
        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.laboratory'));
    }

    public function test_denial_wins_over_grant(): void
    {
        $inst = $this->makeInstitute();
        $this->setPackageFeature($inst, 'medical.pharmacy', false);

        $this->grant($inst, 'feature', 'medical.pharmacy');
        $this->assertTrue($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));

        $this->deny($inst, 'feature', 'medical.pharmacy');

        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));
    }

    public function test_denial_wins_over_package_default(): void
    {
        $inst = $this->makeInstitute();
        $this->setPackageFeature($inst, 'medical.pharmacy', true);
        $this->assertTrue($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));

        $this->deny($inst, 'feature', 'medical.pharmacy');

        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));
    }

    public function test_denial_wins_over_institute_override(): void
    {
        $inst = $this->makeInstitute();
        $this->setPackageFeature($inst, 'medical.pharmacy', false);

        InstituteFeatureOverride::create([
            'institute_id' => $inst->id,
            'feature_key' => 'medical.pharmacy',
            'enabled' => true,
        ]);
        $this->assertTrue($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));

        $this->deny($inst, 'feature', 'medical.pharmacy');

        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));
    }

    public function test_expired_grant_has_no_effect(): void
    {
        $inst = $this->makeInstitute();
        $this->setPackageFeature($inst, 'medical.pharmacy', false);
        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));

        $this->grant($inst, 'feature', 'medical.pharmacy', [
            'expires_at' => now()->subDay(),
        ]);

        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));
    }

    public function test_expired_denial_has_no_effect(): void
    {
        $inst = $this->makeInstitute();
        $this->setPackageFeature($inst, 'medical.pharmacy', true);
        $this->assertTrue($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));

        $this->deny($inst, 'feature', 'medical.pharmacy', [
            'expires_at' => now()->subDay(),
        ]);

        $this->assertTrue($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));
    }

    public function test_revoked_grant_has_no_effect(): void
    {
        $inst = $this->makeInstitute();
        $this->setPackageFeature($inst, 'medical.pharmacy', false);
        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));

        $this->grant($inst, 'feature', 'medical.pharmacy', [
            'status' => 'revoked',
            'revoked_at' => now(),
            'revoked_by' => $this->admin()->id,
        ]);

        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));
    }

    public function test_lifted_denial_has_no_effect(): void
    {
        $inst = $this->makeInstitute();
        $this->setPackageFeature($inst, 'medical.pharmacy', true);
        $this->assertTrue($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));

        $this->deny($inst, 'feature', 'medical.pharmacy', [
            'status' => 'lifted',
            'lifted_at' => now(),
            'lifted_by' => $this->admin()->id,
        ]);

        $this->assertTrue($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));
    }
}
