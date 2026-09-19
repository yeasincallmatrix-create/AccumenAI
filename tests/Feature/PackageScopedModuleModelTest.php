<?php

namespace Tests\Feature;

use App\Models\PackageScope;
use App\Models\PackageScopedModule;
use App\Models\SubscriptionPackage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PackageScopedModuleModelTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('package_scoped_modules') || ! Schema::hasTable('package_scopes')) {
            $this->markTestSkipped('Required tables do not exist.');
        }
    }

    private function package(): SubscriptionPackage
    {
        return SubscriptionPackage::firstOrCreate(
            ['slug' => 'scoped-mod-' . uniqid()],
            [
                'name' => 'Scoped Mod ' . uniqid(),
                'price_monthly' => 0,
                'price_yearly' => 0,
                'status' => 'active',
                'is_default' => false,
            ]
        );
    }

    private function scope(): PackageScope
    {
        return PackageScope::create([
            'package_id' => $this->package()->id,
            'inherit_from_parent' => false,
            'status' => 'active',
        ]);
    }

    public function test_scope_hash_computed_on_create(): void
    {
        $scope = $this->scope();

        $row = PackageScopedModule::create([
            'package_scope_id' => $scope->id,
            'module_key' => 'reports',
            'enabled' => true,
        ]);

        $this->assertEquals($scope->id . '|reports', $row->scope_hash);
    }

    public function test_unique_scope_module_constraint(): void
    {
        $scope = $this->scope();

        PackageScopedModule::create([
            'package_scope_id' => $scope->id,
            'module_key' => 'reports',
            'enabled' => true,
        ]);

        $this->expectException(QueryException::class);

        PackageScopedModule::create([
            'package_scope_id' => $scope->id,
            'module_key' => 'reports',
            'enabled' => false,
        ]);
    }

    public function test_enabled_cast_bool(): void
    {
        $scope = $this->scope();

        $row = PackageScopedModule::create([
            'package_scope_id' => $scope->id,
            'module_key' => 'reports',
            'enabled' => 1,
        ]);

        $this->assertTrue($row->fresh()->enabled);
        $this->assertIsBool($row->fresh()->enabled);
    }

    public function test_cascade_delete_with_scope(): void
    {
        $scope = $this->scope();

        PackageScopedModule::create([
            'package_scope_id' => $scope->id,
            'module_key' => 'reports',
            'enabled' => true,
        ]);

        $this->assertEquals(1, PackageScopedModule::where('package_scope_id', $scope->id)->count());

        $scope->delete();

        $this->assertEquals(0, PackageScopedModule::where('package_scope_id', $scope->id)->count());
    }

    public function test_scope_relation(): void
    {
        $scope = $this->scope();

        $row = PackageScopedModule::create([
            'package_scope_id' => $scope->id,
            'module_key' => 'reports',
            'enabled' => true,
        ]);

        $this->assertNotNull($row->scope);
        $this->assertEquals($scope->id, $row->scope->id);
    }
}
