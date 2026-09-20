<?php

namespace Tests\Feature;

use App\Models\FeatureRegistry;
use App\Models\Institute;
use App\Models\PackageFeature;
use App\Models\PackageScope;
use App\Models\PackageScopedFeature;
use App\Models\PlatformAdmin;
use App\Models\SubscriptionPackage;
use App\Models\TenantAccessGrant;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 7b (B75): tier grants runtime.
 *
 * Tier grant = unlock all features a tier package provides.
 * Resolution is scope-aware (scoped features win when a scope holds
 * rows) with legacy package_features fallback. Additive, Gate 2
 * preserved (unknown keys never invented), Gate 1 enforced (parent
 * module must be enabled).
 */
class TierGrantRuntimeTest extends TestCase
{
    use DatabaseTransactions;

    private ModuleAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('tenant_access_grants')
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
            'first_name' => 'Tier',
            'last_name' => 'Admin',
            'email' => 'tier-admin-'.uniqid().'@example.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function makeInstitute(string $packageSlug = 'advanced', string $industry = 'healthcare'): Institute
    {
        $pkg = SubscriptionPackage::whereRaw('LOWER(slug) = ?', [strtolower($packageSlug)])->firstOrFail();

        $instId = DB::table('institutes')->insertGetId([
            'name' => 'Tier Test '.uniqid(),
            'slug' => 'tier-test-'.uniqid(),
            'industry' => $industry,
            'sub_industry' => $industry === 'healthcare' ? 'hospital' : 'school',
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

    private function grantTier(Institute $inst, string $tier): TenantAccessGrant
    {
        return TenantAccessGrant::create([
            'institute_id' => $inst->id,
            'grant_type' => 'tier',
            'grant_key' => $tier,
            'granted_by' => $this->admin()->id,
            'status' => 'active',
        ]);
    }

    public function test_tier_grant_enables_all_advanced_features(): void
    {
        $inst = $this->makeInstitute('advanced');
        $this->setPackageFeature($inst, 'medical.pharmacy', false);
        $this->setPackageFeature($inst, 'medical.laboratory', false);
        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));
        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.laboratory'));

        $this->grantTier($inst, 'advanced');

        $this->assertTrue($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));
        $this->assertTrue($this->service->isFeatureEnabled($inst, 'medical.laboratory'));
    }

    public function test_tier_grant_respects_scope_fallback(): void
    {
        $inst = $this->makeInstitute('advanced');
        $tierPkg = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['advanced'])->firstOrFail();

        // Scoped subset for the tier package: only pharmacy.
        // Reuse the committed global scope when it exists (parallel-safe).
        $scope = PackageScope::firstOrCreate(
            [
                'package_id' => $tierPkg->id,
                'country_id' => null,
                'industry_id' => null,
                'sub_industry_id' => null,
            ],
            ['inherit_from_parent' => false, 'status' => 'active'],
        );
        $scope->update(['inherit_from_parent' => false, 'status' => 'active']);
        PackageScopedFeature::where('package_scope_id', $scope->id)->delete();
        PackageScopedFeature::create([
            'package_scope_id' => $scope->id,
            'feature_key' => 'medical.pharmacy',
            'enabled' => true,
        ]);

        $this->setPackageFeature($inst, 'medical.pharmacy', false);
        $this->setPackageFeature($inst, 'medical.laboratory', false);

        $this->grantTier($inst, 'advanced');

        // Scoped set wins: pharmacy granted, laboratory (legacy-only) not.
        $this->assertTrue($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));
        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.laboratory'));
    }

    public function test_tier_grant_skips_industry_incompatible_features(): void
    {
        // Education industry: medical module is industry-incompatible,
        // so Gate 1 blocks even a tier grant.
        $inst = $this->makeInstitute('advanced', 'education');

        $this->assertFalse($this->service->isEnabled($inst, 'medical'));

        $this->grantTier($inst, 'advanced');

        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));
        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.laboratory'));
    }

    public function test_tier_grant_additive_with_existing(): void
    {
        $inst = $this->makeInstitute('advanced');
        // Pharmacy already on via package; laboratory off.
        $this->setPackageFeature($inst, 'medical.pharmacy', true);
        $this->setPackageFeature($inst, 'medical.laboratory', false);
        $this->assertTrue($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));

        $this->grantTier($inst, 'advanced');

        // Existing stays on, tier adds the missing one.
        $this->assertTrue($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));
        $this->assertTrue($this->service->isFeatureEnabled($inst, 'medical.laboratory'));
    }
}
