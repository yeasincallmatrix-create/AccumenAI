<?php

namespace Tests\Feature;

use App\Models\FeatureRegistry;
use App\Models\Institute;
use App\Models\PackageFeature;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ModuleAccessFeatureGateTest extends TestCase
{
    use DatabaseTransactions;

    private ModuleAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('feature_registry') || ! Schema::hasTable('package_features')) {
            $this->markTestSkipped('feature_registry or package_features table does not exist.');
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
            'name' => 'FeatureGate Test '.uniqid(),
            'slug' => 'feature-gate-'.uniqid(),
            'status' => 'active',
            'package_id' => $pkg?->id,
            'industry' => $industry,
        ]);
    }

    public function test_feature_enabled_returns_true_for_package_with_feature(): void
    {
        $inst = $this->institute('advanced');
        $this->assertTrue($this->service->isEnabled($inst, 'medical'));
        $this->assertTrue($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));
    }

    public function test_feature_enabled_returns_false_for_package_without_feature(): void
    {
        $inst = $this->institute('basic');
        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));
    }

    public function test_feature_disabled_when_parent_module_disabled(): void
    {
        $inst = $this->institute('advanced');
        $this->assertTrue($this->service->isEnabled($inst, 'medical'));

        $this->service->disableModule($inst, 'medical', null, 'Test disable');
        $this->assertFalse($this->service->isEnabled($inst, 'medical'));
        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));
    }

    public function test_feature_disabled_when_feature_not_in_registry(): void
    {
        $inst = $this->institute('advanced');
        $this->assertTrue($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));
        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.nonexistent'));
    }

    public function test_feature_disabled_when_feature_status_inactive(): void
    {
        $inst = $this->institute('advanced');
        FeatureRegistry::where('feature_key', 'medical.pharmacy')->update(['status' => 'inactive']);

        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));
    }

    public function test_feature_disabled_when_package_features_row_missing(): void
    {
        $inst = $this->institute('advanced');
        $this->assertTrue($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));

        PackageFeature::where('package_id', $inst->package_id)
            ->where('feature_key', 'medical.pharmacy')
            ->delete();

        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));
    }

    public function test_feature_disabled_when_feature_key_malformed(): void
    {
        $inst = $this->institute('advanced');
        $this->assertFalse($this->service->isFeatureEnabled($inst, 'pharmacy'));
        $this->assertFalse($this->service->isFeatureEnabled($inst, ''));
    }

    public function test_fail_closed_when_package_null_falls_back_to_free(): void
    {
        $instId = DB::table('institutes')->insertGetId([
            'name' => 'NoPackage Institute '.uniqid(),
            'slug' => 'nopkg-'.uniqid(),
            'status' => 'active',
            'package_id' => null,
            'industry' => 'healthcare',
            'country' => 'Bangladesh',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $inst = Institute::withoutGlobalScopes()->find($instId);

        $this->service->flushCache($inst->id);

        $this->assertNull($inst->package_id, 'Institute must have null package_id');
        $this->assertFalse(
            $this->service->isFeatureEnabled($inst, 'medical.pharmacy'),
            'Null-package institute must fall back to FREE tier, which does not include medical.pharmacy features'
        );
    }
}
