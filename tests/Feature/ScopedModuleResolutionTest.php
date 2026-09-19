<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\PackageModule;
use App\Models\PackageScope;
use App\Models\PackageScopedModule;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ScopedModuleResolutionTest extends TestCase
{
    use DatabaseTransactions;

    private ModuleAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('package_scopes') || ! Schema::hasTable('package_scoped_modules')) {
            $this->markTestSkipped('Required tables do not exist.');
        }

        $this->service = app(ModuleAccessService::class);
    }

    private function package(): SubscriptionPackage
    {
        return SubscriptionPackage::firstOrCreate(
            ['slug' => 'scoped-modres-' . uniqid()],
            [
                'name' => 'Scoped ModRes ' . uniqid(),
                'price_monthly' => 0,
                'price_yearly' => 0,
                'status' => 'active',
                'is_default' => false,
            ]
        );
    }

    private function scopedKeys(PackageScope $scope): array
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getScopedModuleKeys');
        $method->setAccessible(true);

        return $method->invoke($this->service, $scope);
    }

    private function packageModules(Institute $institute): array
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('resolvePackageModules');
        $method->setAccessible(true);

        return $method->invoke($this->service, $institute);
    }

    private function makeInstitute(SubscriptionPackage $pkg, bool $withSubscription = true): Institute
    {
        $instId = DB::table('institutes')->insertGetId([
            'name' => 'ScopedMod Test ' . uniqid(),
            'slug' => 'scopedmod-' . uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
            'package_id' => $pkg->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($withSubscription) {
            DB::table('institute_subscriptions')->insert([
                'institute_id' => $instId,
                'package_id' => $pkg->id,
                'status' => 'active',
                'start_date' => now()->toDateString(),
                'end_date' => now()->addYear()->toDateString(),
            ]);
        }

        return Institute::withoutGlobalScopes()->find($instId);
    }

    public function test_get_scoped_module_keys_returns_direct(): void
    {
        $pkg = $this->package();
        $scope = PackageScope::create(['package_id' => $pkg->id, 'inherit_from_parent' => false, 'status' => 'active']);

        PackageScopedModule::create(['package_scope_id' => $scope->id, 'module_key' => 'reports', 'enabled' => true]);
        PackageScopedModule::create(['package_scope_id' => $scope->id, 'module_key' => 'finance', 'enabled' => true]);

        $keys = $this->scopedKeys($scope);

        $this->assertContains('reports', $keys);
        $this->assertContains('finance', $keys);
        $this->assertCount(2, $keys);
    }

    public function test_inherit_from_parent_merges_modules(): void
    {
        $pkg = $this->package();

        $globalScope = PackageScope::create(['package_id' => $pkg->id, 'inherit_from_parent' => true, 'status' => 'active']);
        PackageScopedModule::create(['package_scope_id' => $globalScope->id, 'module_key' => 'reports', 'enabled' => true]);

        $childScope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => 21,
            'inherit_from_parent' => true,
            'status' => 'active',
        ]);
        PackageScopedModule::create(['package_scope_id' => $childScope->id, 'module_key' => 'finance', 'enabled' => true]);

        $keys = $this->scopedKeys($childScope);

        $this->assertContains('reports', $keys);
        $this->assertContains('finance', $keys);
    }

    public function test_child_disable_overrides_parent_enable(): void
    {
        $pkg = $this->package();

        $globalScope = PackageScope::create(['package_id' => $pkg->id, 'inherit_from_parent' => true, 'status' => 'active']);
        PackageScopedModule::create(['package_scope_id' => $globalScope->id, 'module_key' => 'reports', 'enabled' => true]);

        $childScope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => 21,
            'inherit_from_parent' => true,
            'status' => 'active',
        ]);
        PackageScopedModule::create(['package_scope_id' => $childScope->id, 'module_key' => 'reports', 'enabled' => false]);

        $keys = $this->scopedKeys($childScope);

        $this->assertNotContains('reports', $keys);
    }

    public function test_independent_scope_ignores_parent(): void
    {
        $pkg = $this->package();

        $globalScope = PackageScope::create(['package_id' => $pkg->id, 'inherit_from_parent' => true, 'status' => 'active']);
        PackageScopedModule::create(['package_scope_id' => $globalScope->id, 'module_key' => 'reports', 'enabled' => true]);

        $childScope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => 21,
            'inherit_from_parent' => false,
            'status' => 'active',
        ]);
        PackageScopedModule::create(['package_scope_id' => $childScope->id, 'module_key' => 'finance', 'enabled' => true]);

        $keys = $this->scopedKeys($childScope);

        $this->assertContains('finance', $keys);
        $this->assertNotContains('reports', $keys);
    }

    public function test_recursive_inherit_three_levels(): void
    {
        $pkg = $this->package();
        $industryId = DB::table('industries')->value('id') ?? 1;

        $globalScope = PackageScope::create(['package_id' => $pkg->id, 'inherit_from_parent' => true, 'status' => 'active']);
        PackageScopedModule::create(['package_scope_id' => $globalScope->id, 'module_key' => 'reports', 'enabled' => true]);

        $countryScope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => 21,
            'inherit_from_parent' => true,
            'status' => 'active',
        ]);
        PackageScopedModule::create(['package_scope_id' => $countryScope->id, 'module_key' => 'finance', 'enabled' => true]);

        $leafScope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => 21,
            'industry_id' => $industryId,
            'inherit_from_parent' => true,
            'status' => 'active',
        ]);
        PackageScopedModule::create(['package_scope_id' => $leafScope->id, 'module_key' => 'notifications', 'enabled' => true]);

        $keys = $this->scopedKeys($leafScope);

        $this->assertContains('reports', $keys);
        $this->assertContains('finance', $keys);
        $this->assertContains('notifications', $keys);
    }

    public function test_resolve_package_modules_uses_scoped_when_available(): void
    {
        $pkg = $this->package();
        PackageModule::create(['package_id' => $pkg->id, 'module_key' => 'reports', 'enabled' => true]);
        PackageModule::create(['package_id' => $pkg->id, 'module_key' => 'finance', 'enabled' => true]);

        $scope = PackageScope::create(['package_id' => $pkg->id, 'inherit_from_parent' => false, 'status' => 'active']);
        PackageScopedModule::create(['package_scope_id' => $scope->id, 'module_key' => 'reports', 'enabled' => true]);

        $inst = $this->makeInstitute($pkg);

        $modules = $this->packageModules($inst);

        $this->assertEquals(['reports'], array_values($modules));
    }

    public function test_resolve_package_modules_falls_back_to_legacy(): void
    {
        $pkg = $this->package();
        PackageModule::create(['package_id' => $pkg->id, 'module_key' => 'reports', 'enabled' => true]);
        PackageModule::create(['package_id' => $pkg->id, 'module_key' => 'finance', 'enabled' => true]);

        // No scopes exist for this fresh package → legacy path.
        $inst = $this->makeInstitute($pkg);

        $modules = $this->packageModules($inst);

        $this->assertEqualsCanonicalizing(['reports', 'finance'], $modules);
    }

    public function test_resolve_package_modules_handles_null_package(): void
    {
        $free = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['free'])->first();
        $this->assertNotNull($free, 'FREE package must exist');

        // Clear any committed scopes for FREE so legacy path is deterministic.
        PackageScope::where('package_id', $free->id)->delete();
        PackageModule::firstOrCreate(
            ['package_id' => $free->id, 'module_key' => 'reports'],
            ['enabled' => true]
        );

        $instId = DB::table('institutes')->insertGetId([
            'name' => 'Null Pkg ' . uniqid(),
            'slug' => 'nullpkg-' . uniqid(),
            'industry' => 'healthcare',
            'status' => 'active',
            'package_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $inst = Institute::withoutGlobalScopes()->find($instId);

        $modules = $this->packageModules($inst);

        $legacy = PackageModule::where('package_id', $free->id)
            ->where('enabled', true)
            ->pluck('module_key')
            ->all();

        $this->assertEqualsCanonicalizing($legacy, $modules);
        $this->assertContains('reports', $modules);
    }

    public function test_existing_institute_resolution_unchanged(): void
    {
        $pkg = $this->package();
        PackageModule::create(['package_id' => $pkg->id, 'module_key' => 'reports', 'enabled' => true]);
        PackageModule::create(['package_id' => $pkg->id, 'module_key' => 'notifications', 'enabled' => true]);

        // No scopes for this fresh package → identical to legacy.
        $inst = $this->makeInstitute($pkg);

        $enabled = $this->service->getEnabledModules($inst);

        $this->assertEqualsCanonicalizing(['reports', 'notifications'], $enabled);
    }

    public function test_free_package_scoping(): void
    {
        $free = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['free'])->first();
        $this->assertNotNull($free, 'FREE package must exist');

        // Clear any committed scopes for FREE, then scope it explicitly.
        PackageScope::where('package_id', $free->id)->delete();

        $scope = PackageScope::create(['package_id' => $free->id, 'inherit_from_parent' => false, 'status' => 'active']);
        PackageScopedModule::create(['package_scope_id' => $scope->id, 'module_key' => 'reports', 'enabled' => true]);

        $instId = DB::table('institutes')->insertGetId([
            'name' => 'Free Scoped ' . uniqid(),
            'slug' => 'freescoped-' . uniqid(),
            'industry' => 'healthcare',
            'status' => 'active',
            'package_id' => $free->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('institute_subscriptions')->insert([
            'institute_id' => $instId,
            'package_id' => $free->id,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);
        $inst = Institute::withoutGlobalScopes()->find($instId);

        $modules = $this->packageModules($inst);

        $this->assertEquals(['reports'], array_values($modules));
    }
}
