<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\PackageScope;
use App\Models\PackageScopedFeature;
use App\Models\PackageFeature;
use App\Models\SubscriptionPackage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PackagesGenerateScopesCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('package_scopes') || ! Schema::hasTable('subscription_packages')) {
            $this->markTestSkipped('Required tables do not exist.');
        }

        if (PackageFeature::count() === 0) {
            $this->markTestSkipped('No package_features data — run seeder first.');
        }
    }

    private function package(string $slug): SubscriptionPackage
    {
        return SubscriptionPackage::where('slug', $slug)->first();
    }

    public function test_dry_run_creates_nothing(): void
    {
        $beforeScopeCount = PackageScope::count();
        $beforeFeatureCount = PackageScopedFeature::count();

        Artisan::call('packages:generate-scopes', ['--backfill' => true, '--dry-run' => true]);

        $this->assertEquals($beforeScopeCount, PackageScope::count());
        $this->assertEquals($beforeFeatureCount, PackageScopedFeature::count());
    }

    public function test_backfill_creates_global_scope_for_each_package(): void
    {
        Artisan::call('packages:generate-scopes', ['--backfill' => true]);

        $packages = SubscriptionPackage::all();
        foreach ($packages as $pkg) {
            $this->assertDatabaseHas('package_scopes', [
                'package_id' => $pkg->id,
                'country_id' => null,
                'industry_id' => null,
                'sub_industry_id' => null,
                'status' => 'active',
            ]);
        }
    }

    public function test_backfill_creates_institute_scope_rows(): void
    {
        Artisan::call('packages:generate-scopes', ['--backfill' => true]);

        $institutes = Institute::query()->whereNotNull('package_id')->get();
        foreach ($institutes as $inst) {
            $this->assertDatabaseHas('package_scopes', [
                'package_id' => $inst->package_id,
                'country_id' => $inst->country_id,
                'industry_id' => $inst->industry_id,
                'sub_industry_id' => $inst->sub_industry_id,
                'status' => 'active',
            ]);
        }
    }

    public function test_feature_rows_copied_from_package_features(): void
    {
        Artisan::call('packages:generate-scopes', ['--backfill' => true]);

        $packages = SubscriptionPackage::all();
        foreach ($packages as $pkg) {
            $expectedCount = PackageFeature::where('package_id', $pkg->id)->count();
            $globalScope = PackageScope::where('package_id', $pkg->id)
                ->whereNull('country_id')
                ->whereNull('industry_id')
                ->whereNull('sub_industry_id')
                ->first();

            if ($expectedCount > 0) {
                $this->assertNotNull($globalScope, "Global scope should exist for package {$pkg->name}");
                $scopedCount = PackageScopedFeature::where('package_scope_id', $globalScope->id)->count();
                $this->assertEquals($expectedCount, $scopedCount, "Feature count mismatch for package {$pkg->name}");
            }
        }
    }

    public function test_idempotency_second_run_creates_zero(): void
    {
        Artisan::call('packages:generate-scopes', ['--backfill' => true]);
        $firstRun = PackageScope::count();

        Artisan::call('packages:generate-scopes', ['--backfill' => true]);
        $secondRun = PackageScope::count();

        $this->assertEquals($firstRun, $secondRun);
    }

    public function test_missing_package_id_falls_back_to_free(): void
    {
        $pkg = $this->package('free');
        $this->assertNotNull($pkg, 'FREE package must exist in DB');

        $inst = Institute::create([
            'name' => 'No Package Institute '.uniqid(),
            'slug' => 'no-pkg-'.uniqid(),
            'status' => 'active',
            'package_id' => null,
        ]);

        Artisan::call('packages:generate-scopes', ['--backfill' => true]);

        $this->assertDatabaseHas('package_scopes', [
            'package_id' => $pkg->id,
            'country_id' => $inst->country_id,
            'industry_id' => $inst->industry_id,
            'sub_industry_id' => $inst->sub_industry_id,
            'status' => 'active',
        ]);
    }

    public function test_scope_row_for_bd_healthcare_institute_created(): void
    {
        $pkg = $this->package('free');
        if (! $pkg) {
            $this->markTestSkipped('FREE package not in DB');
        }

        $inst = Institute::where('country_id', 21)
            ->where('industry_id', 1)
            ->first();

        if (! $inst) {
            $this->markTestSkipped('No BD healthcare institute in DB');
        }

        Artisan::call('packages:generate-scopes', ['--backfill' => true]);

        $this->assertDatabaseHas('package_scopes', [
            'package_id' => $pkg->id,
            'country_id' => 21,
            'industry_id' => 1,
            'status' => 'active',
        ]);
    }

    public function test_cascade_deleting_scope_deletes_features(): void
    {
        $pkg = SubscriptionPackage::create([
            'name' => 'Cascade Test Package '.uniqid(),
            'slug' => 'cascade-test-'.uniqid(),
            'price_monthly' => 0,
            'price_yearly' => 0,
            'status' => 'active',
            'is_default' => false,
        ]);

        $scope = PackageScope::create([
            'package_id' => $pkg->id,
            'status' => 'active',
        ]);

        PackageScopedFeature::create([
            'package_scope_id' => $scope->id,
            'feature_key' => 'test.cascade',
            'enabled' => true,
        ]);

        $featureCount = PackageScopedFeature::where('package_scope_id', $scope->id)->count();
        $this->assertEquals(1, $featureCount);

        $scope->delete();

        $remaining = PackageScopedFeature::where('package_scope_id', $scope->id)->count();
        $this->assertEquals(0, $remaining);
    }
}
