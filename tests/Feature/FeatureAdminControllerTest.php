<?php

namespace Tests\Feature;

use App\Models\FeatureRegistry;
use App\Models\ModuleAccessLog;
use App\Models\PackageFeature;
use App\Models\PlatformAdmin;
use App\Models\SubscriptionPackage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FeatureAdminControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('feature_registry') || ! Schema::hasTable('package_features')) {
            $this->markTestSkipped('feature_registry or package_features table does not exist.');
        }

        if (FeatureRegistry::count() === 0) {
            (new \Database\Seeders\FeatureRegistrySeeder)->run();
        }
        if (PackageFeature::count() === 0) {
            (new \Database\Seeders\PackageFeatureSeeder)->run();
        }
    }

    private function loginAsAdmin(): PlatformAdmin
    {
        $admin = PlatformAdmin::firstOrReuseForTests([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'test-admin-' . uniqid() . '@example.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($admin, 'platform_admin');
        return $admin;
    }

    public function test_index_requires_auth(): void
    {
        $response = $this->get(route('admin.features.index'));
        $response->assertRedirect();
    }

    public function test_index_returns_200_for_platform_admin(): void
    {
        $this->loginAsAdmin();

        $response = $this->get(route('admin.features.index'));
        $response->assertOk();
        $response->assertSee('Feature Management');
        $response->assertSee('medical.pharmacy');
    }

    public function test_show_requires_auth(): void
    {
        $response = $this->get(route('admin.features.show', 'medical.pharmacy'));
        $response->assertRedirect();
    }

    public function test_show_returns_200_for_valid_feature(): void
    {
        $this->loginAsAdmin();

        $response = $this->get(route('admin.features.show', 'medical.pharmacy'));
        $response->assertOk();
        $response->assertSee('Pharmacy');
        $response->assertSee('medical.pharmacy');
        $response->assertSee('Package Availability');
    }

    public function test_toggle_package_requires_auth(): void
    {
        $pkg = SubscriptionPackage::where('slug', 'advanced')->first();
        if (! $pkg) {
            $this->markTestSkipped('Advanced package not found.');
        }

        $response = $this->post(route('admin.features.toggle-package', [
            'feature_key' => 'medical.pharmacy',
            'package_id' => $pkg->id,
        ]));
        $response->assertRedirect();
    }

    public function test_toggle_package_toggles_feature_state(): void
    {
        $this->loginAsAdmin();

        $pkg = SubscriptionPackage::where('slug', 'advanced')->first();
        if (! $pkg) {
            $this->markTestSkipped('Advanced package not found.');
        }

        $pf = PackageFeature::where('package_id', $pkg->id)
            ->where('feature_key', 'medical.pharmacy')
            ->first();
        $initialState = $pf?->enabled ?? false;

        $this->post(route('admin.features.toggle-package', [
            'feature_key' => 'medical.pharmacy',
            'package_id' => $pkg->id,
            'enabled' => !$initialState,
        ]))->assertRedirect();
        $this->assertDatabaseHas('package_features', [
            'package_id' => $pkg->id,
            'feature_key' => 'medical.pharmacy',
            'enabled' => !$initialState,
        ]);

        $this->post(route('admin.features.toggle-package', [
            'feature_key' => 'medical.pharmacy',
            'package_id' => $pkg->id,
            'enabled' => $initialState,
        ]))->assertRedirect();
        $this->assertDatabaseHas('package_features', [
            'package_id' => $pkg->id,
            'feature_key' => 'medical.pharmacy',
            'enabled' => $initialState,
        ]);
    }

    public function test_index_lists_all_features(): void
    {
        $this->loginAsAdmin();

        $response = $this->get(route('admin.features.index'));
        $response->assertOk();

        $allFeatures = FeatureRegistry::pluck('feature_key')->toArray();
        foreach ($allFeatures as $key) {
            $response->assertSee($key);
        }

        $response->assertSee('ADVANCED');
        $response->assertSee('PREMIUM');
    }

    public function test_index_shows_package_coverage_matrix(): void
    {
        $this->loginAsAdmin();

        $response = $this->get(route('admin.features.index'));
        $response->assertOk();

        $pharmacyFeature = FeatureRegistry::where('feature_key', 'medical.pharmacy')->first();
        $this->assertNotNull($pharmacyFeature, 'medical.pharmacy must exist in feature_registry');

        $advancedPkg = SubscriptionPackage::where('slug', 'advanced')->first();
        $this->assertNotNull($advancedPkg, 'advanced package must exist');

        $pf = PackageFeature::where('package_id', $advancedPkg->id)
            ->where('feature_key', 'medical.pharmacy')
            ->first();
        $this->assertNotNull($pf, 'medical.pharmacy must be in package_features for advanced');

        $response->assertSee('btn-success');
        $response->assertSee('check-circle-fill');
    }

    public function test_show_returns_404_for_unknown_feature(): void
    {
        $this->loginAsAdmin();

        $response = $this->get(route('admin.features.show', 'medical.nonexistent'));
        $response->assertNotFound();
    }

    public function test_toggle_package_logs_to_audit(): void
    {
        $this->loginAsAdmin();

        $pkg = SubscriptionPackage::where('slug', 'advanced')->first();
        if (! $pkg) {
            $this->markTestSkipped('Advanced package not found.');
        }

        $pf = PackageFeature::firstOrCreate(
            ['package_id' => $pkg->id, 'feature_key' => 'medical.pharmacy'],
            ['enabled' => false]
        );
        $pf->update(['enabled' => false]);

        $baselineCount = ModuleAccessLog::count();

        $this->post(route('admin.features.toggle-package', [
            'feature_key' => 'medical.pharmacy',
            'package_id' => $pkg->id,
            'enabled' => true,
        ]))->assertRedirect();

        $this->assertEquals($baselineCount + 1, ModuleAccessLog::count(), 'Audit log entry should be created.');

        $latestLog = ModuleAccessLog::latest()->first();
        $this->assertStringContainsString('feature_', $latestLog->action);
        $this->assertEquals('platform_admin', $latestLog->actor_type);
        $this->assertEquals($pkg->id, $latestLog->package_id);
        $this->assertEquals('feature_enabled', $latestLog->action);
    }

    public function test_toggle_package_is_idempotent(): void
    {
        $this->loginAsAdmin();

        $pkg = SubscriptionPackage::where('slug', 'advanced')->first();
        if (! $pkg) {
            $this->markTestSkipped('Advanced package not found.');
        }

        $pf = PackageFeature::firstOrCreate(
            ['package_id' => $pkg->id, 'feature_key' => 'medical.pharmacy'],
            ['enabled' => true]
        );
        $pf->update(['enabled' => true]);

        $baselineCount = ModuleAccessLog::count();

        $response = $this->post(route('admin.features.toggle-package', [
            'feature_key' => 'medical.pharmacy',
            'package_id' => $pkg->id,
            'enabled' => true,
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('info');

        $this->assertEquals($baselineCount, ModuleAccessLog::count(), 'No audit log entry should be created for idempotent action.');
    }

    public function test_toggle_package_validation_requires_enabled(): void
    {
        $this->loginAsAdmin();

        $pkg = SubscriptionPackage::where('slug', 'advanced')->first();
        if (! $pkg) {
            $this->markTestSkipped('Advanced package not found.');
        }

        $this->post(route('admin.features.toggle-package', [
            'feature_key' => 'medical.pharmacy',
            'package_id' => $pkg->id,
        ]))->assertSessionHasErrors('enabled');

        $this->post(route('admin.features.toggle-package', [
            'feature_key' => 'medical.pharmacy',
            'package_id' => $pkg->id,
            'enabled' => 'not-a-bool',
        ]))->assertSessionHasErrors('enabled');
    }
}
