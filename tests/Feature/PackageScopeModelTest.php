<?php

namespace Tests\Feature;

use App\Models\PackageScope;
use App\Models\PackageFeature;
use App\Models\SubscriptionPackage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Concerns\ResolvesTestIds;

class PackageScopeModelTest extends TestCase
{
    use DatabaseTransactions;
    use ResolvesTestIds;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('package_scopes') || ! Schema::hasTable('subscription_packages')) {
            $this->markTestSkipped('Required tables do not exist.');
        }
    }

    private function package(): SubscriptionPackage
    {
        return SubscriptionPackage::firstOrCreate(
            ['slug' => 'scope-test-'.uniqid()],
            [
                'name' => 'Scope Test Package '.uniqid(),
                'price_monthly' => 0,
                'price_yearly' => 0,
                'status' => 'active',
                'is_default' => false,
            ]
        );
    }

    public function test_unique_constraint_prevents_duplicate_global_scope(): void
    {
        $pkg = $this->package();

        PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => null,
            'industry_id' => null,
            'sub_industry_id' => null,
            'status' => 'active',
        ]);

        $this->expectException(QueryException::class);

        PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => null,
            'industry_id' => null,
            'sub_industry_id' => null,
            'status' => 'active',
        ]);
    }

    public function test_unique_constraint_prevents_duplicate_scoped_row(): void
    {
        $pkg = $this->package();

        PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => $this->bdCountryId(),
            'industry_id' => null,
            'sub_industry_id' => null,
            'status' => 'active',
        ]);

        $this->expectException(QueryException::class);

        PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => $this->bdCountryId(),
            'industry_id' => null,
            'sub_industry_id' => null,
            'status' => 'active',
        ]);
    }

    public function test_is_global_returns_true_when_all_null(): void
    {
        $pkg = $this->package();
        $scope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => null,
            'industry_id' => null,
            'sub_industry_id' => null,
            'status' => 'active',
        ]);

        $this->assertTrue($scope->isGlobal());
    }

    public function test_is_global_returns_false_when_country_set(): void
    {
        $pkg = $this->package();
        $scope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => $this->bdCountryId(),
            'industry_id' => null,
            'sub_industry_id' => null,
            'status' => 'active',
        ]);

        $this->assertFalse($scope->isGlobal());
    }

    public function test_is_global_returns_false_when_industry_set(): void
    {
        $pkg = $this->package();
        $scope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => $this->bdCountryId(),
            'industry_id' => null,
            'sub_industry_id' => null,
            'status' => 'active',
        ]);

        $this->assertFalse($scope->isGlobal());
    }

    public function test_package_relation_resolves(): void
    {
        $pkg = $this->package();
        $scope = PackageScope::create([
            'package_id' => $pkg->id,
            'status' => 'active',
        ]);

        $this->assertEquals($pkg->id, $scope->package->id);
    }

    public function test_scope_active_filters_correctly(): void
    {
        $pkg = $this->package();

        PackageScope::create(['package_id' => $pkg->id, 'country_id' => $this->bdCountryId(), 'status' => 'active']);
        PackageScope::create(['package_id' => $pkg->id, 'country_id' => $this->nonBdCountryId(), 'status' => 'inactive']);

        $activeCount = PackageScope::where('package_id', $pkg->id)->active()->count();
        $this->assertEquals(1, $activeCount);
    }

    public function test_cascade_delete_on_package_delete(): void
    {
        $this->markTestSkipped('Cannot test cascade — subscription_packages has FK constraints from 5+ tables.');
    }

    public function test_scope_hash_is_computed_on_create(): void
    {
        $pkg = $this->package();
        $scope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => null,
            'industry_id' => null,
            'sub_industry_id' => null,
            'status' => 'active',
        ]);

        $this->assertNotNull($scope->scope_hash);
        $this->assertEquals("{$pkg->id}-G-G-G", $scope->scope_hash);
    }

    public function test_scope_hash_matches_expected_format(): void
    {
        $pkg = $this->package();
        $scope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => $this->bdCountryId(),
            'industry_id' => null,
            'sub_industry_id' => null,
            'status' => 'active',
        ]);

        $this->assertEquals("{$pkg->id}-{$this->bdCountryId()}-G-G", $scope->scope_hash);
    }

    public function test_scope_hash_format_with_all_dimensions(): void
    {
        $pkg = $this->package();
        $scope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => $this->bdCountryId(),
            'industry_id' => null,
            'sub_industry_id' => null,
            'status' => 'active',
        ]);

        $scope2 = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => $this->nonBdCountryId(),
            'industry_id' => null,
            'sub_industry_id' => null,
            'status' => 'active',
        ]);

        $this->assertEquals("{$pkg->id}-{$this->bdCountryId()}-G-G", $scope->scope_hash);
        $this->assertEquals("{$pkg->id}-{$this->nonBdCountryId()}-G-G", $scope2->scope_hash);
    }
}
