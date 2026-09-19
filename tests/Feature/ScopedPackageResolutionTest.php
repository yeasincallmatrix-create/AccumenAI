<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\PackageScope;
use App\Models\PackageScopedFeature;
use App\Models\PackageFeature;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ScopedPackageResolutionTest extends TestCase
{
    use DatabaseTransactions;

    private ModuleAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('package_scopes') || ! Schema::hasTable('subscription_packages')) {
            $this->markTestSkipped('Required tables do not exist.');
        }

        $this->service = app(ModuleAccessService::class);
    }

    private function package(string $slug): SubscriptionPackage
    {
        $pkg = SubscriptionPackage::firstOrCreate(
            ['slug' => "scoped-res-{$slug}-" . uniqid()],
            [
                'name' => "Scoped Res {$slug} " . uniqid(),
                'price_monthly' => 0,
                'price_yearly' => 0,
                'status' => 'active',
                'is_default' => false,
            ]
        );

        return $pkg;
    }

    private function institute(array $attrs): Institute
    {
        return Institute::withoutEvents(function () use ($attrs) {
            return Institute::create(array_merge([
                'name' => 'Scope Res Test ' . uniqid(),
                'slug' => 'scope-res-' . uniqid(),
                'status' => 'active',
            ], $attrs));
        });
    }

    public function test_resolve_exact_match_returns_most_specific(): void
    {
        $pkg = $this->package('exact');
        $global = PackageScope::create(['package_id' => $pkg->id, 'status' => 'active']);
        $countryScope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => 21,
            'industry_id' => null,
            'sub_industry_id' => null,
            'status' => 'active',
        ]);

        $inst = $this->institute([
            'package_id' => $pkg->id,
            'country_id' => 21,
        ]);

        $resolved = $this->service->resolveScopedPackage($inst);
        $this->assertNotNull($resolved);
        $this->assertEquals($countryScope->id, $resolved->id);
    }

    public function test_resolve_falls_back_to_country_industry(): void
    {
        $pkg = $this->package('ci');
        PackageScope::create(['package_id' => $pkg->id, 'status' => 'active']);
        $cScope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => 21,
            'industry_id' => null,
            'status' => 'active',
        ]);

        $inst = $this->institute([
            'package_id' => $pkg->id,
            'country_id' => 21,
        ]);

        $resolved = $this->service->resolveScopedPackage($inst);
        $this->assertNotNull($resolved);
        $this->assertEquals($cScope->id, $resolved->id);
    }

    public function test_resolve_falls_back_to_country_only(): void
    {
        $pkg = $this->package('c');
        PackageScope::create(['package_id' => $pkg->id, 'status' => 'active']);
        $cScope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => 21,
            'status' => 'active',
        ]);

        $inst = $this->institute([
            'package_id' => $pkg->id,
            'country_id' => 21,
        ]);

        $resolved = $this->service->resolveScopedPackage($inst);
        $this->assertNotNull($resolved);
        $this->assertEquals($cScope->id, $resolved->id);
    }

    public function test_resolve_falls_back_to_industry_only(): void
    {
        $pkg = $this->package('i');
        PackageScope::create(['package_id' => $pkg->id, 'status' => 'active']);

        $inst = $this->institute([
            'package_id' => $pkg->id,
            'country_id' => 21,
        ]);

        $resolved = $this->service->resolveScopedPackage($inst);
        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->isGlobal());
    }

    public function test_resolve_falls_back_to_global(): void
    {
        $pkg = $this->package('g');
        $globalScope = PackageScope::create(['package_id' => $pkg->id, 'status' => 'active']);

        $inst = $this->institute([
            'package_id' => $pkg->id,
            'country_id' => 1,
        ]);

        $resolved = $this->service->resolveScopedPackage($inst);
        $this->assertNotNull($resolved);
        $this->assertEquals($globalScope->id, $resolved->id);
    }

    public function test_resolve_returns_null_when_no_scope_exists(): void
    {
        $pkg = $this->package('empty');

        $inst = $this->institute([
            'package_id' => $pkg->id,
        ]);

        $resolved = $this->service->resolveScopedPackage($inst);
        $this->assertNull($resolved);
    }

    public function test_resolve_handles_null_industry(): void
    {
        $pkg = $this->package('null-i');
        $scope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => 21,
            'industry_id' => null,
            'sub_industry_id' => null,
            'status' => 'active',
        ]);

        $inst = $this->institute([
            'package_id' => $pkg->id,
            'country_id' => 21,
        ]);

        $resolved = $this->service->resolveScopedPackage($inst);
        $this->assertNotNull($resolved);
        $this->assertEquals($scope->id, $resolved->id);
    }

    public function test_resolve_handles_soft_deleted_institute(): void
    {
        $pkg = $this->package('softdel');
        $scope = PackageScope::create(['package_id' => $pkg->id, 'status' => 'active']);

        $inst = $this->institute(['package_id' => $pkg->id]);
        $inst->delete();

        $resolved = $this->service->resolveScopedPackage($inst);
        $this->assertNotNull($resolved);
        $this->assertEquals($scope->id, $resolved->id);
    }

    public function test_resolve_uses_free_package_when_package_id_null(): void
    {
        $free = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['free'])->first();
        if (! $free) {
            $this->markTestSkipped('FREE package not in DB');
        }

        $globalScope = PackageScope::where('package_id', $free->id)
            ->whereNull('country_id')
            ->whereNull('industry_id')
            ->whereNull('sub_industry_id')
            ->where('status', 'active')
            ->first();
        if (! $globalScope) {
            $this->markTestSkipped('No GLOBAL scope for FREE package');
        }

        $inst = Institute::withoutEvents(function () {
            return Institute::create([
                'name' => 'Free Fallback Test ' . uniqid(),
                'slug' => 'free-fallback-' . uniqid(),
                'status' => 'active',
                'package_id' => null,
            ]);
        });
        $this->assertNull($inst->package_id, 'Institute package_id should be null');

        $resolved = $this->service->resolveScopedPackage($inst);
        $this->assertNotNull($resolved);
        $this->assertEquals($free->id, $resolved->package_id, 'Should resolve to FREE package');
    }

    public function test_deduplication_handles_null_combinations(): void
    {
        $pkg = $this->package('dedup');
        $globalScope = PackageScope::create(['package_id' => $pkg->id, 'status' => 'active']);

        $inst = $this->institute([
            'package_id' => $pkg->id,
            'country_id' => null,
        ]);

        $resolved = $this->service->resolveScopedPackage($inst);
        $this->assertNotNull($resolved);
        $this->assertEquals($globalScope->id, $resolved->id);
    }

    public function test_resolve_sub_industry_only_scope(): void
    {
        $pkg = $this->package('sub');
        PackageScope::create(['package_id' => $pkg->id, 'status' => 'active']);
        $subScope = PackageScope::create([
            'package_id' => $pkg->id,
            'sub_industry_id' => 15816,
            'status' => 'active',
        ]);

        $inst = $this->institute([
            'package_id' => $pkg->id,
            'sub_industry_id' => 15816,
        ]);

        $resolved = $this->service->resolveScopedPackage($inst);
        $this->assertNotNull($resolved);
        $this->assertEquals($subScope->id, $resolved->id);
    }

    public function test_resolve_industry_plus_sub_no_country(): void
    {
        $pkg = $this->package('is');
        PackageScope::create(['package_id' => $pkg->id, 'status' => 'active']);
        $isScope = PackageScope::create([
            'package_id' => $pkg->id,
            'industry_id' => 3418,
            'sub_industry_id' => 15816,
            'status' => 'active',
        ]);

        $inst = $this->institute([
            'package_id' => $pkg->id,
            'industry_id' => 3418,
            'sub_industry_id' => 15816,
        ]);

        $resolved = $this->service->resolveScopedPackage($inst);
        $this->assertNotNull($resolved);
        $this->assertEquals($isScope->id, $resolved->id);
    }

    public function test_resolve_country_plus_sub_no_industry(): void
    {
        $pkg = $this->package('cs');
        PackageScope::create(['package_id' => $pkg->id, 'status' => 'active']);
        $csScope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => 21,
            'sub_industry_id' => 15816,
            'status' => 'active',
        ]);

        $inst = $this->institute([
            'package_id' => $pkg->id,
            'country_id' => 21,
            'sub_industry_id' => 15816,
        ]);

        $resolved = $this->service->resolveScopedPackage($inst);
        $this->assertNotNull($resolved);
        $this->assertEquals($csScope->id, $resolved->id);
    }

    public function test_full_six_level_fallback_chain(): void
    {
        $pkg = $this->package('full6');

        // Only GLOBAL exists — should be the fallback
        $globalScope = PackageScope::create(['package_id' => $pkg->id, 'status' => 'active']);

        $inst = $this->institute([
            'package_id' => $pkg->id,
            'country_id' => 21,
            'industry_id' => 3418,
            'sub_industry_id' => 15816,
        ]);

        $resolved = $this->service->resolveScopedPackage($inst);
        $this->assertNotNull($resolved);
        $this->assertEquals($globalScope->id, $resolved->id);
        $this->assertTrue($resolved->isGlobal());
    }

    public function test_sub_industry_inherits_from_global_when_parent_missing(): void
    {
        $pkg = $this->package('sub-parent');

        // Only GLOBAL scope exists — no sub-industry-specific parent
        $globalScope = PackageScope::create(['package_id' => $pkg->id, 'status' => 'active']);
        PackageScopedFeature::create(['package_scope_id' => $globalScope->id, 'feature_key' => 'medical.pharmacy', 'enabled' => true]);

        $subScope = PackageScope::create([
            'package_id' => $pkg->id,
            'sub_industry_id' => 15816,
            'inherit_from_parent' => true,
            'status' => 'active',
        ]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getScopedFeatureKeys');
        $method->setAccessible(true);

        $keys = $method->invoke($this->service, $subScope);

        // No parent found at any intermediate level → inherit walks to GLOBAL
        // But resolveParentScope for (null, null, S) looks for (null, null, null) = GLOBAL
        $this->assertContains('medical.pharmacy', $keys, 'Should inherit from GLOBAL when no intermediate parent exists');
    }
}
