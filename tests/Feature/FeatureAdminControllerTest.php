<?php

namespace Tests\Feature;

use App\Models\FeatureRegistry;
use App\Models\Institute;
use App\Models\InstituteFeatureOverride;
use App\Models\ModuleAccessLog;
use App\Models\PackageFeature;
use App\Models\PlatformAdmin;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
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
        $response->assertSee('Package Coverage');
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

    public function test_index_filter_by_search_query(): void
    {
        $this->loginAsAdmin();

        $response = $this->get(route('admin.features.index', ['q' => 'pharmacy']));
        $response->assertOk();
        $response->assertSee('medical.pharmacy');
        $response->assertSee('Pharmacy');
        $response->assertDontSee('medical.laboratory');
    }

    public function test_index_filter_by_module(): void
    {
        $this->loginAsAdmin();

        $response = $this->get(route('admin.features.index', ['module' => 'medical']));
        $response->assertOk();
        $response->assertSee('medical.pharmacy');
        $response->assertSee('medical.laboratory');

        $allFeatures = FeatureRegistry::where('module_key', '!=', 'medical')->pluck('feature_key')->toArray();
        foreach ($allFeatures as $key) {
            $response->assertDontSee($key);
        }
    }

    public function test_index_filter_by_package(): void
    {
        $this->loginAsAdmin();

        $advancedPkg = SubscriptionPackage::where('slug', 'advanced')->first();
        $premiumPkg = SubscriptionPackage::where('slug', 'premium')->first();
        if (! $advancedPkg || ! $premiumPkg) {
            $this->markTestSkipped('Advanced or Premium package not found.');
        }

        $response = $this->get(route('admin.features.index', [
            'package' => $advancedPkg->id,
        ]));
        $response->assertOk();

        // Selected package IS visible
        $response->assertSee($advancedPkg->name);

        // Other packages' matrix column headers should NOT appear.
        // The dropdown always lists all packages as <option> text,
        // so a bare assertDontSee('Premium') would always fail.
        // We target the matrix-specific HTML: the <th> with class "text-center"
        // followed by the package name, which only renders in the matrix header.
        // Extract the rendered view and inspect the <thead> section.
        $content = $response->getContent();
        preg_match('/<thead>[\s\S]*?<\/thead>/', $content, $matches);
        $this->assertNotEmpty($matches, 'Matrix thead should be present');
        $thead = $matches[0];
        $this->assertStringContainsString($advancedPkg->name, $thead);
        $this->assertStringNotContainsString($premiumPkg->name, $thead);
    }

    public function test_index_preserves_filters_in_pagination_urls(): void
    {
        $this->loginAsAdmin();

        $response = $this->get(route('admin.features.index', ['q' => 'medical']));
        $response->assertOk();

        $features = $response->viewData('features');
        $this->assertNotNull($features, 'features paginator missing from view data');

        // url(1) should include q=medical in the query string
        $this->assertStringContainsString(
            'q=medical',
            $features->url(1),
            'Pagination URL should preserve q filter'
        );
    }

    public function test_index_empty_state_for_no_matches(): void
    {
        $this->loginAsAdmin();

        $response = $this->get(route('admin.features.index', ['q' => 'nonexistent-xyz']));
        $response->assertOk();
        $response->assertSee('No features match your filters');
        $response->assertDontSee('No features configured');
    }

    public function test_show_displays_institute_overrides_section(): void
    {
        $this->loginAsAdmin();

        $response = $this->get(route('admin.features.show', 'medical.pharmacy'));
        $response->assertOk();
        $response->assertSee('Institute Overrides');
    }

    public function test_show_displays_existing_override(): void
    {
        $this->loginAsAdmin();

        $pkg = SubscriptionPackage::where('slug', 'advanced')->first();
        if (! $pkg) {
            $this->markTestSkipped('Advanced package not found.');
        }

        $inst = Institute::create([
            'name' => 'Override Display Test ' . uniqid(),
            'slug' => 'override-display-' . uniqid(),
            'status' => 'active',
            'package_id' => $pkg->id,
            'industry' => 'healthcare',
        ]);

        InstituteFeatureOverride::create([
            'institute_id' => $inst->id,
            'feature_key'  => 'medical.pharmacy',
            'enabled'      => true,
        ]);

        $response = $this->get(route('admin.features.show', 'medical.pharmacy'));
        $response->assertOk();
        $response->assertSee($inst->name);
        $response->assertSee('Enabled');
    }

    public function test_add_institute_override_creates_record(): void
    {
        $this->loginAsAdmin();

        $pkg = SubscriptionPackage::where('slug', 'advanced')->first();
        if (! $pkg) {
            $this->markTestSkipped('Advanced package not found.');
        }

        $inst = Institute::create([
            'name' => 'Override Create Test ' . uniqid(),
            'slug' => 'override-create-' . uniqid(),
            'status' => 'active',
            'package_id' => $pkg->id,
            'industry' => 'healthcare',
        ]);

        $this->post(route('admin.features.institute-override.add', 'medical.pharmacy'), [
            'institute_id' => $inst->id,
            'enabled'      => true,
            'reason'       => 'Test grant',
        ])->assertRedirect();

        $this->assertDatabaseHas('institute_feature_overrides', [
            'institute_id' => $inst->id,
            'feature_key'  => 'medical.pharmacy',
            'enabled'      => true,
        ]);
    }

    public function test_remove_institute_override_deletes_record(): void
    {
        $this->loginAsAdmin();

        $pkg = SubscriptionPackage::where('slug', 'advanced')->first();
        if (! $pkg) {
            $this->markTestSkipped('Advanced package not found.');
        }

        $inst = Institute::create([
            'name' => 'Override Remove Test ' . uniqid(),
            'slug' => 'override-remove-' . uniqid(),
            'status' => 'active',
            'package_id' => $pkg->id,
            'industry' => 'healthcare',
        ]);

        InstituteFeatureOverride::create([
            'institute_id' => $inst->id,
            'feature_key'  => 'medical.pharmacy',
            'enabled'      => false,
        ]);

        $this->assertDatabaseHas('institute_feature_overrides', [
            'institute_id' => $inst->id,
            'feature_key'  => 'medical.pharmacy',
        ]);

        $this->delete(route('admin.features.institute-override.remove', [
            'feature_key'  => 'medical.pharmacy',
            'institute_id' => $inst->id,
        ]))->assertRedirect();

        $this->assertDatabaseMissing('institute_feature_overrides', [
            'institute_id' => $inst->id,
            'feature_key'  => 'medical.pharmacy',
        ]);
    }

    public function test_add_override_logs_to_audit(): void
    {
        $this->loginAsAdmin();

        $pkg = SubscriptionPackage::where('slug', 'advanced')->first();
        if (! $pkg) {
            $this->markTestSkipped('Advanced package not found.');
        }

        $inst = Institute::create([
            'name' => 'Override Audit Test ' . uniqid(),
            'slug' => 'override-audit-' . uniqid(),
            'status' => 'active',
            'package_id' => $pkg->id,
            'industry' => 'healthcare',
        ]);

        $baselineCount = ModuleAccessLog::count();

        $this->post(route('admin.features.institute-override.add', 'medical.pharmacy'), [
            'institute_id' => $inst->id,
            'enabled'      => true,
        ])->assertRedirect();

        $this->assertEquals($baselineCount + 1, ModuleAccessLog::count(), 'Audit log entry should be created.');

        $latestLog = ModuleAccessLog::latest()->first();
        $this->assertStringContainsString('feature_override_', $latestLog->action);
        $this->assertEquals($inst->id, $latestLog->institute_id);
        $this->assertEquals('platform_admin', $latestLog->actor_type);
    }

    public function test_add_override_is_idempotent(): void
    {
        $this->loginAsAdmin();

        $pkg = SubscriptionPackage::where('slug', 'advanced')->first();
        if (! $pkg) {
            $this->markTestSkipped('Advanced package not found.');
        }

        $inst = Institute::create([
            'name' => 'Override Idempotent Test ' . uniqid(),
            'slug' => 'override-idempotent-' . uniqid(),
            'status' => 'active',
            'package_id' => $pkg->id,
            'industry' => 'healthcare',
        ]);

        InstituteFeatureOverride::create([
            'institute_id' => $inst->id,
            'feature_key'  => 'medical.pharmacy',
            'enabled'      => true,
        ]);

        $baselineCount = ModuleAccessLog::count();

        $response = $this->post(route('admin.features.institute-override.add', 'medical.pharmacy'), [
            'institute_id' => $inst->id,
            'enabled'      => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('info');

        $this->assertEquals($baselineCount, ModuleAccessLog::count(), 'No audit log entry should be created for idempotent action.');
    }

    public function test_granted_by_displays_system_for_null_actor(): void
    {
        $this->loginAsAdmin();

        $pkg = SubscriptionPackage::where('slug', 'advanced')->first();
        if (! $pkg) {
            $this->markTestSkipped('Advanced package not found.');
        }

        $inst = Institute::create([
            'name' => 'Override Null Actor Test ' . uniqid(),
            'slug' => 'override-null-actor-' . uniqid(),
            'status' => 'active',
            'package_id' => $pkg->id,
            'industry' => 'healthcare',
        ]);

        InstituteFeatureOverride::create([
            'institute_id'  => $inst->id,
            'feature_key'   => 'medical.pharmacy',
            'enabled'       => true,
            'overridden_by' => null,
        ]);

        $response = $this->get(route('admin.features.show', 'medical.pharmacy'));
        $response->assertOk();
        $response->assertSee('System');
    }

    public function test_toggle_package_flushes_feature_cache(): void
    {
        $this->loginAsAdmin();

        $pkg = SubscriptionPackage::where('slug', 'advanced')->first();
        if (! $pkg) {
            $this->markTestSkipped('Advanced package not found.');
        }

        $inst = Institute::create([
            'name' => 'Cache Flush Test ' . uniqid(),
            'slug' => 'cache-flush-' . uniqid(),
            'status' => 'active',
            'package_id' => $pkg->id,
            'industry' => 'healthcare',
        ]);

        $service = app(ModuleAccessService::class);

        // Warm cache — feature should be enabled for advanced
        $this->assertTrue($service->isFeatureEnabled($inst, 'medical.pharmacy'));

        // Disable via admin toggle
        $this->post(route('admin.features.toggle-package', [
            'feature_key' => 'medical.pharmacy',
            'package_id' => $pkg->id,
            'enabled' => false,
        ]))->assertRedirect();

        // Cache was flushed; isFeatureEnabled must reflect new DB state
        $this->assertFalse($service->isFeatureEnabled($inst, 'medical.pharmacy'), 'Feature cache should be flushed after toggle');

        // Re-enable
        $this->post(route('admin.features.toggle-package', [
            'feature_key' => 'medical.pharmacy',
            'package_id' => $pkg->id,
            'enabled' => true,
        ]))->assertRedirect();

        $this->assertTrue($service->isFeatureEnabled($inst, 'medical.pharmacy'), 'Feature cache should be flushed after re-enable');
    }
}
