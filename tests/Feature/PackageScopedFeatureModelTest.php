<?php

namespace Tests\Feature;

use App\Models\PackageScope;
use App\Models\PackageScopedFeature;
use App\Models\PackageFeature;
use App\Models\SubscriptionPackage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PackageScopedFeatureModelTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('package_scoped_features') || ! Schema::hasTable('package_scopes') || ! Schema::hasTable('subscription_packages')) {
            $this->markTestSkipped('Required tables do not exist.');
        }
    }

    private function scope(): PackageScope
    {
        $pkg = SubscriptionPackage::firstOrCreate(
            ['slug' => 'scoped-feat-test-'.uniqid()],
            [
                'name' => 'Scoped Feature Test Package '.uniqid(),
                'price_monthly' => 0,
                'price_yearly' => 0,
                'status' => 'active',
                'is_default' => false,
            ]
        );

        return PackageScope::create([
            'package_id' => $pkg->id,
            'status' => 'active',
        ]);
    }

    public function test_unique_constraint_prevents_duplicate_feature_key_per_scope(): void
    {
        $scope = $this->scope();

        PackageScopedFeature::create([
            'package_scope_id' => $scope->id,
            'feature_key' => 'medical.pharmacy',
            'enabled' => true,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        PackageScopedFeature::create([
            'package_scope_id' => $scope->id,
            'feature_key' => 'medical.pharmacy',
            'enabled' => false,
        ]);
    }

    public function test_scope_relation_resolves(): void
    {
        $scope = $this->scope();
        $feat = PackageScopedFeature::create([
            'package_scope_id' => $scope->id,
            'feature_key' => 'medical.pharmacy',
            'enabled' => true,
        ]);

        $this->assertEquals($scope->id, $feat->scope->id);
    }

    public function test_enabled_bool_cast(): void
    {
        $scope = $this->scope();
        $feat = PackageScopedFeature::create([
            'package_scope_id' => $scope->id,
            'feature_key' => 'medical.pharmacy',
            'enabled' => true,
        ]);

        $this->assertIsBool($feat->enabled);
        $this->assertTrue($feat->enabled);
    }

    public function test_different_scopes_can_have_same_feature_key(): void
    {
        $scope1 = $this->scope();
        $scope2 = $this->scope();

        PackageScopedFeature::create([
            'package_scope_id' => $scope1->id,
            'feature_key' => 'medical.pharmacy',
            'enabled' => true,
        ]);

        $feat2 = PackageScopedFeature::create([
            'package_scope_id' => $scope2->id,
            'feature_key' => 'medical.pharmacy',
            'enabled' => false,
        ]);

        $this->assertDatabaseHas('package_scoped_features', [
            'package_scope_id' => $scope2->id,
            'feature_key' => 'medical.pharmacy',
            'enabled' => false,
        ]);
    }

    public function test_cascade_delete_on_scope_delete(): void
    {
        $scope = $this->scope();
        $featId = PackageScopedFeature::create([
            'package_scope_id' => $scope->id,
            'feature_key' => 'medical.pharmacy',
            'enabled' => true,
        ])->id;

        $scope->delete();

        $this->assertDatabaseMissing('package_scoped_features', ['id' => $featId]);
    }
}
