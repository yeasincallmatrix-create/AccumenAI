<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\PackageModule;
use App\Models\PackageScope;
use App\Models\PackageScopedModule;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Concerns\ResolvesTestIds;

class ScopedModuleBackfillTest extends TestCase
{
    use DatabaseTransactions;
    use ResolvesTestIds;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('package_scopes')
            || ! Schema::hasTable('package_scoped_modules')
            || ! Schema::hasTable('package_modules')) {
            $this->markTestSkipped('Required tables do not exist.');
        }
    }

    private function package(): SubscriptionPackage
    {
        return SubscriptionPackage::firstOrCreate(
            ['slug' => 'scoped-modbf-' . uniqid()],
            [
                'name' => 'Scoped ModBf ' . uniqid(),
                'price_monthly' => 0,
                'price_yearly' => 0,
                'status' => 'active',
                'is_default' => false,
            ]
        );
    }

    private function globalScope(SubscriptionPackage $pkg): ?PackageScope
    {
        return PackageScope::where('package_id', $pkg->id)
            ->whereNull('country_id')
            ->whereNull('industry_id')
            ->whereNull('sub_industry_id')
            ->first();
    }

    public function test_backfill_copies_package_modules_to_global_scope(): void
    {
        $pkg = $this->package();
        PackageModule::create(['package_id' => $pkg->id, 'module_key' => 'reports', 'enabled' => true]);
        PackageModule::create(['package_id' => $pkg->id, 'module_key' => 'finance', 'enabled' => false]);

        Artisan::call('packages:generate-scopes', ['--backfill' => true]);

        $scope = $this->globalScope($pkg);
        $this->assertNotNull($scope, 'Global scope should exist after backfill');

        $this->assertDatabaseHas('package_scoped_modules', [
            'package_scope_id' => $scope->id,
            'module_key' => 'reports',
        ]);
        $this->assertDatabaseHas('package_scoped_modules', [
            'package_scope_id' => $scope->id,
            'module_key' => 'finance',
        ]);

        $this->assertTrue((bool) PackageScopedModule::where('package_scope_id', $scope->id)
            ->where('module_key', 'reports')->value('enabled'));
        $this->assertFalse((bool) PackageScopedModule::where('package_scope_id', $scope->id)
            ->where('module_key', 'finance')->value('enabled'));
    }

    public function test_backfill_creates_scope_modules_for_new_scope(): void
    {
        $pkg = $this->package();
        PackageModule::create(['package_id' => $pkg->id, 'module_key' => 'reports', 'enabled' => true]);

        $instId = DB::table('institutes')->insertGetId([
            'name' => 'Backfill Mod ' . uniqid(),
            'slug' => 'backfillmod-' . uniqid(),
            'country_id' => $this->bdCountryId(),
            'status' => 'active',
            'package_id' => $pkg->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Artisan::call('packages:generate-scopes', ['--backfill' => true]);

        $scope = PackageScope::where('package_id', $pkg->id)
            ->where('country_id', $this->bdCountryId())
            ->first();
        $this->assertNotNull($scope, 'Institute scope should exist after backfill');

        $this->assertDatabaseHas('package_scoped_modules', [
            'package_scope_id' => $scope->id,
            'module_key' => 'reports',
        ]);

        // Cleanup helper variable (institute created via query builder).
        $this->assertNotNull(Institute::withoutGlobalScopes()->find($instId));
    }

    public function test_backfill_is_idempotent(): void
    {
        $pkg = $this->package();
        PackageModule::create(['package_id' => $pkg->id, 'module_key' => 'reports', 'enabled' => true]);

        Artisan::call('packages:generate-scopes', ['--backfill' => true]);

        $scope = $this->globalScope($pkg);
        $this->assertNotNull($scope);
        $firstCount = PackageScopedModule::where('package_scope_id', $scope->id)->count();

        Artisan::call('packages:generate-scopes', ['--backfill' => true]);

        $this->assertEquals(
            $firstCount,
            PackageScopedModule::where('package_scope_id', $scope->fresh()->id)->count()
        );
    }

    public function test_ensure_scope_copies_parent_modules(): void
    {
        $pkg = $this->package();

        $globalScope = PackageScope::create([
            'package_id' => $pkg->id,
            'inherit_from_parent' => true,
            'status' => 'active',
        ]);
        PackageScopedModule::create([
            'package_scope_id' => $globalScope->id,
            'module_key' => 'reports',
            'enabled' => true,
        ]);

        $instId = DB::table('institutes')->insertGetId([
            'name' => 'Ensure Mod ' . uniqid(),
            'slug' => 'ensuremod-' . uniqid(),
            'country_id' => $this->bdCountryId(),
            'status' => 'active',
            'package_id' => $pkg->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $inst = Institute::withoutGlobalScopes()->find($instId);

        $scope = app(ModuleAccessService::class)->ensureScopeExistsForInstitute($inst);

        $this->assertNotNull($scope);
        $this->assertEquals($this->bdCountryId(), $scope->country_id);
        $this->assertDatabaseHas('package_scoped_modules', [
            'package_scope_id' => $scope->id,
            'module_key' => 'reports',
        ]);
    }

    public function test_dry_run_makes_no_changes(): void
    {
        $beforeScopeCount = PackageScope::count();
        $beforeModuleCount = PackageScopedModule::count();

        Artisan::call('packages:generate-scopes', ['--backfill' => true, '--dry-run' => true]);

        $this->assertEquals($beforeScopeCount, PackageScope::count());
        $this->assertEquals($beforeModuleCount, PackageScopedModule::count());
    }
}
