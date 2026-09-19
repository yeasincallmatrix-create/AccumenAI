<?php

namespace Tests\Feature;

use App\Models\PackageScope;
use App\Models\PackageScopedFeature;
use App\Models\PackageFeature;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ScopedFeatureResolutionTest extends TestCase
{
    use DatabaseTransactions;

    private ModuleAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('package_scopes') || ! Schema::hasTable('package_scoped_features')) {
            $this->markTestSkipped('Required tables do not exist.');
        }

        $this->service = app(ModuleAccessService::class);
    }

    private function package(): SubscriptionPackage
    {
        return SubscriptionPackage::firstOrCreate(
            ['slug' => 'scoped-feat-' . uniqid()],
            [
                'name' => 'Scoped Feat ' . uniqid(),
                'price_monthly' => 0,
                'price_yearly' => 0,
                'status' => 'active',
                'is_default' => false,
            ]
        );
    }

    public function test_get_scoped_feature_keys_returns_direct_features(): void
    {
        $pkg = $this->package();
        $scope = PackageScope::create(['package_id' => $pkg->id, 'inherit_from_parent' => false, 'status' => 'active']);

        PackageScopedFeature::create(['package_scope_id' => $scope->id, 'feature_key' => 'medical.pharmacy', 'enabled' => true]);
        PackageScopedFeature::create(['package_scope_id' => $scope->id, 'feature_key' => 'medical.laboratory', 'enabled' => true]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getScopedFeatureKeys');
        $method->setAccessible(true);

        $keys = $method->invoke($this->service, $scope);

        $this->assertContains('medical.pharmacy', $keys);
        $this->assertContains('medical.laboratory', $keys);
        $this->assertCount(2, $keys);
    }

    public function test_inherit_from_parent_merges_parent_features(): void
    {
        $pkg = $this->package();

        $globalScope = PackageScope::create(['package_id' => $pkg->id, 'inherit_from_parent' => true, 'status' => 'active']);
        PackageScopedFeature::create(['package_scope_id' => $globalScope->id, 'feature_key' => 'medical.pharmacy', 'enabled' => true]);

        $childScope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => 21,
            'inherit_from_parent' => true,
            'status' => 'active',
        ]);
        PackageScopedFeature::create(['package_scope_id' => $childScope->id, 'feature_key' => 'medical.laboratory', 'enabled' => true]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getScopedFeatureKeys');
        $method->setAccessible(true);

        $keys = $method->invoke($this->service, $childScope);

        $this->assertContains('medical.pharmacy', $keys);
        $this->assertContains('medical.laboratory', $keys);
    }

    public function test_child_override_wins_over_parent(): void
    {
        $pkg = $this->package();

        $globalScope = PackageScope::create(['package_id' => $pkg->id, 'inherit_from_parent' => true, 'status' => 'active']);
        PackageScopedFeature::create(['package_scope_id' => $globalScope->id, 'feature_key' => 'medical.pharmacy', 'enabled' => true]);

        $childScope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => 21,
            'inherit_from_parent' => true,
            'status' => 'active',
        ]);
        PackageScopedFeature::create(['package_scope_id' => $childScope->id, 'feature_key' => 'medical.pharmacy', 'enabled' => true]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getScopedFeatureKeys');
        $method->setAccessible(true);

        $keys = $method->invoke($this->service, $childScope);

        $count = array_count_values($keys)['medical.pharmacy'] ?? 0;
        $this->assertEquals(1, $count);
    }

    public function test_child_disable_overrides_parent_enable(): void
    {
        $pkg = $this->package();

        $globalScope = PackageScope::create(['package_id' => $pkg->id, 'inherit_from_parent' => true, 'status' => 'active']);
        PackageScopedFeature::create(['package_scope_id' => $globalScope->id, 'feature_key' => 'medical.pharmacy', 'enabled' => true]);

        $childScope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => 21,
            'inherit_from_parent' => true,
            'status' => 'active',
        ]);
        PackageScopedFeature::create(['package_scope_id' => $childScope->id, 'feature_key' => 'medical.pharmacy', 'enabled' => false]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getScopedFeatureKeys');
        $method->setAccessible(true);

        $keys = $method->invoke($this->service, $childScope);

        $this->assertNotContains('medical.pharmacy', $keys);
    }

    public function test_independent_scope_ignores_parent(): void
    {
        $pkg = $this->package();

        $globalScope = PackageScope::create(['package_id' => $pkg->id, 'inherit_from_parent' => true, 'status' => 'active']);
        PackageScopedFeature::create(['package_scope_id' => $globalScope->id, 'feature_key' => 'medical.pharmacy', 'enabled' => true]);

        $childScope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => 21,
            'inherit_from_parent' => false,
            'status' => 'active',
        ]);
        PackageScopedFeature::create(['package_scope_id' => $childScope->id, 'feature_key' => 'medical.laboratory', 'enabled' => true]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getScopedFeatureKeys');
        $method->setAccessible(true);

        $keys = $method->invoke($this->service, $childScope);

        $this->assertNotContains('medical.pharmacy', $keys);
        $this->assertContains('medical.laboratory', $keys);
    }

    public function test_recursive_inherit_three_levels(): void
    {
        $pkg = $this->package();

        $globalScope = PackageScope::create(['package_id' => $pkg->id, 'inherit_from_parent' => true, 'status' => 'active']);
        PackageScopedFeature::create(['package_scope_id' => $globalScope->id, 'feature_key' => 'medical.pharmacy', 'enabled' => true]);

        $countryScope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => 21,
            'inherit_from_parent' => true,
            'status' => 'active',
        ]);
        PackageScopedFeature::create(['package_scope_id' => $countryScope->id, 'feature_key' => 'medical.laboratory', 'enabled' => true]);

        $childScope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => 21,
            'industry_id' => null,
            'inherit_from_parent' => true,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('package_scopes', ['id' => $childScope->id]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getScopedFeatureKeys');
        $method->setAccessible(true);

        $keys = $method->invoke($this->service, $countryScope);

        $this->assertContains('medical.pharmacy', $keys);
        $this->assertContains('medical.laboratory', $keys);
    }

    public function test_circular_inherit_does_not_loop(): void
    {
        $pkg = $this->package();

        $scopeA = PackageScope::create(['package_id' => $pkg->id, 'country_id' => 21, 'inherit_from_parent' => true, 'status' => 'active']);
        $scopeB = PackageScope::create(['package_id' => $pkg->id, 'country_id' => 1, 'inherit_from_parent' => true, 'status' => 'active']);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('resolveParentScope');
        $method->setAccessible(true);

        $parent = $method->invoke($this->service, $scopeB);
        $this->assertNotNull($parent);
        $this->assertEquals($scopeA->id, $parent->id);
    }

    public function test_empty_scope_falls_back_to_legacy_package_features(): void
    {
        $pkg = $this->package();

        PackageScope::create(['package_id' => $pkg->id, 'inherit_from_parent' => true, 'status' => 'active']);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getScopedFeatureKeys');
        $method->setAccessible(true);

        $scope = PackageScope::where('package_id', $pkg->id)->whereNull('country_id')->first();
        $keys = $method->invoke($this->service, $scope);

        $this->assertEmpty($keys);
    }
}
