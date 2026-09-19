<?php

namespace Tests\Feature;

use App\Models\FeatureRegistry;
use App\Models\Institute;
use App\Models\InstituteFeatureOverride;
use App\Models\InstituteModuleOverride;
use App\Models\PackageFeature;
use App\Models\PlatformAdmin;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InstituteFeatureOverrideTest extends TestCase
{
    use DatabaseTransactions;

    private ModuleAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('feature_registry') || ! Schema::hasTable('package_features') || ! Schema::hasTable('institute_feature_overrides')) {
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

    private function institute(string $packageSlug, string $industry = 'healthcare'): Institute
    {
        $pkg = SubscriptionPackage::where('slug', $packageSlug)->first();
        return Institute::create([
            'name' => 'Override Test '.uniqid(),
            'slug' => 'override-test-'.uniqid(),
            'status' => 'active',
            'package_id' => $pkg?->id,
            'industry' => $industry,
        ]);
    }

    private function admin(): PlatformAdmin
    {
        return PlatformAdmin::firstOrReuseForTests([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'test-override-'.uniqid().'@example.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    public function test_override_grants_feature_not_in_package(): void
    {
        $inst = $this->institute('basic');
        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.pharmacy'), 'basic package should not include medical.pharmacy');

        InstituteFeatureOverride::create([
            'institute_id' => $inst->id,
            'feature_key' => 'medical.pharmacy',
            'enabled' => true,
        ]);

        $this->assertTrue($this->service->isFeatureEnabled($inst, 'medical.pharmacy'), 'override should grant feature');
    }

    public function test_override_denies_feature_in_package(): void
    {
        $inst = $this->institute('advanced');
        $this->assertTrue($this->service->isFeatureEnabled($inst, 'medical.pharmacy'), 'advanced package should include medical.pharmacy');

        InstituteFeatureOverride::create([
            'institute_id' => $inst->id,
            'feature_key' => 'medical.pharmacy',
            'enabled' => false,
        ]);

        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.pharmacy'), 'override should deny feature');
    }

    public function test_override_grant_when_package_disabled(): void
    {
        $inst = $this->institute('advanced');
        PackageFeature::where('package_id', $inst->package_id)
            ->where('feature_key', 'medical.pharmacy')
            ->update(['enabled' => false]);

        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.pharmacy'), 'feature should be disabled at package level');

        InstituteFeatureOverride::create([
            'institute_id' => $inst->id,
            'feature_key' => 'medical.pharmacy',
            'enabled' => true,
        ]);

        $this->assertTrue($this->service->isFeatureEnabled($inst, 'medical.pharmacy'), 'override should grant despite package disable');
    }

    public function test_override_deny_when_package_enabled(): void
    {
        $inst = $this->institute('advanced');
        $this->assertTrue($this->service->isFeatureEnabled($inst, 'medical.pharmacy'), 'advanced package should include medical.pharmacy');

        InstituteFeatureOverride::create([
            'institute_id' => $inst->id,
            'feature_key' => 'medical.pharmacy',
            'enabled' => false,
        ]);

        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.pharmacy'), 'override should deny despite package enable');
    }

    public function test_no_override_falls_through_to_package(): void
    {
        $inst = $this->institute('advanced');
        $this->assertTrue($this->service->isFeatureEnabled($inst, 'medical.pharmacy'), 'no override should fall through to package (enabled)');
    }

    public function test_no_override_and_package_disabled(): void
    {
        $inst = $this->institute('advanced');
        PackageFeature::where('package_id', $inst->package_id)
            ->where('feature_key', 'medical.pharmacy')
            ->update(['enabled' => false]);

        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.pharmacy'), 'no override + package disabled = false');
    }

    public function test_override_does_not_bypass_parent_module_gate(): void
    {
        $inst = $this->institute('advanced');
        $this->service->disableModule($inst, 'medical', null, 'Test disable');
        $this->assertFalse($this->service->isEnabled($inst, 'medical'), 'medical module should be disabled');

        InstituteFeatureOverride::create([
            'institute_id' => $inst->id,
            'feature_key' => 'medical.pharmacy',
            'enabled' => true,
        ]);

        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.pharmacy'), 'Gate 1 should block even with feature override');
    }

    public function test_override_does_not_bypass_registry_gate(): void
    {
        $inst = $this->institute('advanced');

        InstituteFeatureOverride::create([
            'institute_id' => $inst->id,
            'feature_key' => 'medical.nonexistent',
            'enabled' => true,
        ]);

        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.nonexistent'), 'Gate 2 should block non-existent feature');
    }

    public function test_override_uses_unique_constraint(): void
    {
        $inst = $this->institute('advanced');

        InstituteFeatureOverride::create([
            'institute_id' => $inst->id,
            'feature_key' => 'medical.pharmacy',
            'enabled' => true,
        ]);

        $this->expectException(QueryException::class);

        InstituteFeatureOverride::create([
            'institute_id' => $inst->id,
            'feature_key' => 'medical.pharmacy',
            'enabled' => false,
        ]);
    }

    public function test_override_belongs_to_platform_admin(): void
    {
        $admin = $this->admin();
        $inst = $this->institute('advanced');

        $override = InstituteFeatureOverride::create([
            'institute_id' => $inst->id,
            'feature_key' => 'medical.pharmacy',
            'enabled' => false,
            'overridden_by' => $admin->id,
        ]);

        $this->assertNotNull($override->overriddenBy, 'overriddenBy relationship should resolve');
        $this->assertEquals($admin->id, $override->overriddenBy->id);
    }
}
