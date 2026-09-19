<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\PackageScope;
use App\Models\PackageScopedFeature;
use App\Models\PackageFeature;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FeatureAccessCacheScopeTest extends TestCase
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
        return SubscriptionPackage::firstOrCreate(
            ['slug' => "cache-scope-{$slug}-" . uniqid()],
            [
                'name' => "Cache Scope {$slug} " . uniqid(),
                'price_monthly' => 0,
                'price_yearly' => 0,
                'status' => 'active',
                'is_default' => false,
            ]
        );
    }

    private function institute(array $attrs): Institute
    {
        return Institute::withoutEvents(function () use ($attrs) {
            return Institute::create(array_merge([
                'name' => 'Cache Scope Test ' . uniqid(),
                'slug' => 'cache-scope-' . uniqid(),
                'status' => 'active',
            ], $attrs));
        });
    }

    public function test_cache_key_includes_scope_hash(): void
    {
        $pkg = $this->package('ckey');
        $scope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => 21,
            'status' => 'active',
        ]);

        $inst = $this->institute([
            'package_id' => $pkg->id,
            'country_id' => 21,
        ]);

        $this->service->getFeatureAccessMap($inst);

        $expectedKey = 'feature_access:' . $inst->id . ':' . $scope->scope_hash;
        $this->assertTrue(Cache::has($expectedKey));
    }

    public function test_scope_change_flushes_affected_institute_caches(): void
    {
        $pkg = $this->package('cflush');
        $scope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => 21,
            'status' => 'active',
        ]);

        $inst = $this->institute([
            'package_id' => $pkg->id,
            'country_id' => 21,
        ]);

        $this->service->getFeatureAccessMap($inst);

        $oldKey = 'feature_access:' . $inst->id . ':' . $scope->scope_hash;
        $this->assertTrue(Cache::has($oldKey));

        $scope->update(['status' => 'inactive']);

        $this->assertFalse(Cache::has($oldKey));
    }

    public function test_global_scope_change_flushes_all_caches(): void
    {
        $pkg = $this->package('gflush');
        $globalScope = PackageScope::create(['package_id' => $pkg->id, 'status' => 'active']);

        $inst1 = $this->institute(['package_id' => $pkg->id, 'country_id' => 21]);
        $inst2 = $this->institute(['package_id' => $pkg->id, 'country_id' => 1]);

        $this->service->getFeatureAccessMap($inst1);
        $this->service->getFeatureAccessMap($inst2);

        $key1 = 'feature_access:' . $inst1->id . ':' . $globalScope->scope_hash;
        $key2 = 'feature_access:' . $inst2->id . ':' . $globalScope->scope_hash;
        $this->assertTrue(Cache::has($key1), "Cache key {$key1} should exist after getFeatureAccessMap");
        $this->assertTrue(Cache::has($key2), "Cache key {$key2} should exist after getFeatureAccessMap");

        $globalScope->update(['status' => 'inactive']);

        $this->assertFalse(Cache::has($key1), "Cache key {$key1} should be flushed after scope update");
        $this->assertFalse(Cache::has($key2), "Cache key {$key2} should be flushed after scope update");
    }
}
