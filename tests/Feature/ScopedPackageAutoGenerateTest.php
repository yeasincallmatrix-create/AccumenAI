<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\PackageScope;
use App\Models\PackageScopedFeature;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ScopedPackageAutoGenerateTest extends TestCase
{
    use DatabaseTransactions;

    private ModuleAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('package_scopes')) {
            $this->markTestSkipped('package_scopes table does not exist.');
        }

        $this->service = app(ModuleAccessService::class);
    }

    private function package(string $slug): SubscriptionPackage
    {
        return SubscriptionPackage::firstOrCreate(
            ['slug' => "auto-gen-{$slug}-" . uniqid()],
            [
                'name' => "Auto Gen {$slug} " . uniqid(),
                'price_monthly' => 0,
                'price_yearly' => 0,
                'status' => 'active',
                'is_default' => false,
            ]
        );
    }

    public function test_institute_create_auto_generates_scope(): void
    {
        $pkg = $this->package('create');

        $inst = Institute::create([
            'name' => 'Auto Gen Create Test ' . uniqid(),
            'slug' => 'auto-gen-create-' . uniqid(),
            'status' => 'active',
            'package_id' => $pkg->id,
            'country_id' => 21,
            'industry_id' => 3418,
        ]);

        $scope = PackageScope::where('package_id', $pkg->id)
            ->where('country_id', 21)
            ->where('industry_id', 3418)
            ->first();

        $this->assertNotNull($scope, 'Scope should be auto-generated on institute create');
        $this->assertTrue($scope->inherit_from_parent);
    }

    public function test_institute_update_generates_scope_when_industry_changes(): void
    {
        $pkg = $this->package('update');

        $inst = Institute::create([
            'name' => 'Auto Gen Update Test ' . uniqid(),
            'slug' => 'auto-gen-update-' . uniqid(),
            'status' => 'active',
            'package_id' => $pkg->id,
            'country_id' => 21,
        ]);

        $scopeBefore = PackageScope::where('package_id', $pkg->id)
            ->where('country_id', 21)
            ->where('industry_id', 3418)
            ->first();
        $this->assertNull($scopeBefore, 'No scope should exist before industry change');

        $inst->update(['industry_id' => 3418]);

        $scopeAfter = PackageScope::where('package_id', $pkg->id)
            ->where('country_id', 21)
            ->where('industry_id', 3418)
            ->first();

        $this->assertNotNull($scopeAfter, 'Scope should be auto-generated on industry change');
    }

    public function test_institute_update_flushes_feature_cache(): void
    {
        $pkg = $this->package('flush');

        $inst = Institute::create([
            'name' => 'Auto Gen Flush Test ' . uniqid(),
            'slug' => 'auto-gen-flush-' . uniqid(),
            'status' => 'active',
            'package_id' => $pkg->id,
            'country_id' => 21,
        ]);

        $cacheKey = 'feature_access:' . $inst->id . ':global';
        Cache::put($cacheKey, ['test' => true], 3600);
        $this->assertTrue(Cache::has($cacheKey), 'Cache should exist before update');

        $inst->update(['industry_id' => 3418]);

        $this->assertFalse(Cache::has($cacheKey), 'Cache should be flushed after scope-changing update');
    }

    public function test_ensure_scope_is_idempotent(): void
    {
        $pkg = $this->package('idempotent');

        $inst = Institute::create([
            'name' => 'Auto Gen Idempotent Test ' . uniqid(),
            'slug' => 'auto-gen-idempotent-' . uniqid(),
            'status' => 'active',
            'package_id' => $pkg->id,
            'country_id' => 21,
            'industry_id' => 3418,
        ]);

        $scope1 = $this->service->ensureScopeExistsForInstitute($inst);
        $scope2 = $this->service->ensureScopeExistsForInstitute($inst);

        $this->assertNotNull($scope1);
        $this->assertNotNull($scope2);
        $this->assertEquals($scope1->id, $scope2->id, 'Second call should return existing scope');

        $count = PackageScope::where('package_id', $pkg->id)
            ->where('country_id', 21)
            ->where('industry_id', 3418)
            ->count();
        $this->assertEquals(1, $count, 'Only one scope row should exist');
    }

    public function test_auto_generate_copies_parent_features(): void
    {
        $pkg = $this->package('copy');

        // GLOBAL scope with features
        $globalScope = PackageScope::create(['package_id' => $pkg->id, 'status' => 'active']);
        PackageScopedFeature::create(['package_scope_id' => $globalScope->id, 'feature_key' => 'medical.pharmacy', 'enabled' => true]);
        PackageScopedFeature::create(['package_scope_id' => $globalScope->id, 'feature_key' => 'medical.laboratory', 'enabled' => true]);

        $inst = Institute::create([
            'name' => 'Auto Gen Copy Test ' . uniqid(),
            'slug' => 'auto-gen-copy-' . uniqid(),
            'status' => 'active',
            'package_id' => $pkg->id,
            'country_id' => 21,
        ]);

        $scope = PackageScope::where('package_id', $pkg->id)
            ->where('country_id', 21)
            ->first();
        $this->assertNotNull($scope);

        $features = PackageScopedFeature::where('package_scope_id', $scope->id)->pluck('feature_key')->toArray();
        $this->assertContains('medical.pharmacy', $features, 'Should copy parent features');
        $this->assertContains('medical.laboratory', $features, 'Should copy parent features');
    }

    public function test_auto_generate_skips_when_parent_missing(): void
    {
        $pkg = $this->package('no-parent');

        $inst = Institute::create([
            'name' => 'Auto Gen No Parent Test ' . uniqid(),
            'slug' => 'auto-gen-no-parent-' . uniqid(),
            'status' => 'active',
            'package_id' => $pkg->id,
            'country_id' => 21,
        ]);

        $scope = PackageScope::where('package_id', $pkg->id)
            ->where('country_id', 21)
            ->first();

        $this->assertNotNull($scope, 'Scope should still be created');
        $this->assertTrue($scope->inherit_from_parent);

        // No features should be copied since GLOBAL scope doesn't exist
        $features = PackageScopedFeature::where('package_scope_id', $scope->id)->count();
        $this->assertEquals(0, $features, 'No features should be copied when parent is missing');
    }
}
