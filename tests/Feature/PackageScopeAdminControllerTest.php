<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\FeatureRegistry;
use App\Models\Institute;
use App\Models\PackageScope;
use App\Models\PackageScopedFeature;
use App\Models\PlatformAdmin;
use App\Models\SubscriptionPackage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PackageScopeAdminControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('package_scopes') || ! Schema::hasTable('subscription_packages')) {
            $this->markTestSkipped('Required tables do not exist.');
        }

        if (FeatureRegistry::count() === 0) {
            (new \Database\Seeders\FeatureRegistrySeeder)->run();
        }
    }

    private function loginAsAdmin(): PlatformAdmin
    {
        $admin = PlatformAdmin::firstOrReuseForTests([
            'first_name' => 'Scope',
            'last_name' => 'Admin',
            'email' => 'scope-admin-' . uniqid() . '@example.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($admin, 'platform_admin');

        return $admin;
    }

    private function package(string $suffix): SubscriptionPackage
    {
        return SubscriptionPackage::firstOrCreate(
            ['slug' => 'scope-admin-' . $suffix . '-' . uniqid()],
            [
                'name' => 'Scope Admin ' . $suffix . ' ' . uniqid(),
                'price_monthly' => 1000,
                'price_yearly' => 10000,
                'status' => 'active',
                'is_default' => false,
            ]
        );
    }

    private function institute(array $attrs): Institute
    {
        return Institute::create(array_merge([
            'name' => 'Scope Admin Inst ' . uniqid(),
            'slug' => 'scope-admin-inst-' . uniqid(),
            'status' => 'active',
        ], $attrs));
    }

    public function test_index_requires_platform_admin(): void
    {
        $pkg = $this->package('idxauth');

        $this->get(route('admin.packages.scopes.index', $pkg))
            ->assertRedirect();
    }

    public function test_index_lists_scopes(): void
    {
        $this->loginAsAdmin();
        $pkg = $this->package('idxlist');

        PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => null,
            'industry_id' => null,
            'sub_industry_id' => null,
            'status' => 'active',
        ]);

        $this->get(route('admin.packages.scopes.index', $pkg))
            ->assertOk()
            ->assertSee('Scoped Packages')
            ->assertSee($pkg->name);
    }

    public function test_index_filter_by_package(): void
    {
        $this->loginAsAdmin();
        $pkgA = $this->package('filtera');
        $pkgB = $this->package('filterb');

        $scopeA = PackageScope::create(['package_id' => $pkgA->id, 'status' => 'active']);
        $scopeB = PackageScope::create(['package_id' => $pkgB->id, 'status' => 'active']);

        $response = $this->get(route('admin.packages.scopes.index', $pkgA, false)
            . '?package_id=' . $pkgB->id);

        $response->assertOk();
        // NOTE: the bound package's name/slug render in the page header
        // and filter dropdown, so assert on scope row links which only
        // appear for listed scopes.
        $response->assertSee('scopes/' . $scopeB->id);
        $response->assertDontSee('scopes/' . $scopeA->id);
    }

    public function test_show_displays_scope_details(): void
    {
        $this->loginAsAdmin();
        $pkg = $this->package('show');

        $scope = PackageScope::create([
            'package_id' => $pkg->id,
            'status' => 'active',
            'price_monthly' => 2500,
            'currency' => 'BDT',
        ]);

        $this->get(route('admin.scopes.show', $scope))
            ->assertOk()
            ->assertSee($pkg->name)
            ->assertSee('2500');
    }

    public function test_create_form_renders(): void
    {
        $this->loginAsAdmin();
        $pkg = $this->package('create');

        $this->get(route('admin.packages.scopes.create', $pkg))
            ->assertOk()
            ->assertSee('Create Scope');
    }

    public function test_store_creates_scope_with_correct_hash(): void
    {
        $this->loginAsAdmin();
        $pkg = $this->package('store');
        $country = Country::orderBy('id')->firstOrFail();

        $this->post(route('admin.packages.scopes.store', $pkg), [
            'package_id' => $pkg->id,
            'country_id' => $country->id,
            'industry_id' => null,
            'sub_industry_id' => null,
            'inherit_from_parent' => true,
            'price_monthly' => 3000,
            'price_yearly' => 30000,
            'currency' => 'BDT',
            'status' => 'active',
        ])->assertRedirect();

        $expectedHash = $pkg->id . '-' . $country->id . '-G-G';

        $this->assertDatabaseHas('package_scopes', [
            'package_id' => $pkg->id,
            'country_id' => $country->id,
            'scope_hash' => $expectedHash,
        ]);
    }

    public function test_store_copies_parent_features_when_inherit(): void
    {
        $this->loginAsAdmin();
        $pkg = $this->package('inherit');
        $country = Country::orderBy('id')->firstOrFail();

        $parent = PackageScope::create([
            'package_id' => $pkg->id,
            'status' => 'active',
        ]);

        PackageScopedFeature::create([
            'package_scope_id' => $parent->id,
            'feature_key' => 'medical.pharmacy',
            'enabled' => true,
        ]);

        $this->post(route('admin.packages.scopes.store', $pkg), [
            'package_id' => $pkg->id,
            'country_id' => $country->id,
            'inherit_from_parent' => true,
            'status' => 'active',
        ])->assertRedirect();

        $child = PackageScope::where('package_id', $pkg->id)
            ->where('country_id', $country->id)
            ->firstOrFail();

        $this->assertDatabaseHas('package_scoped_features', [
            'package_scope_id' => $child->id,
            'feature_key' => 'medical.pharmacy',
            'enabled' => true,
        ]);
    }

    public function test_update_changes_pricing(): void
    {
        $this->loginAsAdmin();
        $pkg = $this->package('update');

        $scope = PackageScope::create([
            'package_id' => $pkg->id,
            'status' => 'active',
            'price_monthly' => 1000,
        ]);

        $this->put(route('admin.scopes.update', $scope), [
            'inherit_from_parent' => true,
            'price_monthly' => 7777,
            'price_yearly' => 77770,
            'currency' => 'BDT',
            'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('package_scopes', [
            'id' => $scope->id,
            'price_monthly' => 7777,
        ]);
    }

    public function test_update_features_syncs_scope_features(): void
    {
        $this->loginAsAdmin();
        $pkg = $this->package('feat');

        $scope = PackageScope::create([
            'package_id' => $pkg->id,
            'status' => 'active',
        ]);

        $this->put(route('admin.scopes.features.update', $scope), [
            'features' => ['medical.pharmacy'],
        ])->assertRedirect();

        $this->assertDatabaseHas('package_scoped_features', [
            'package_scope_id' => $scope->id,
            'feature_key' => 'medical.pharmacy',
            'enabled' => true,
        ]);
        $this->assertDatabaseHas('package_scoped_features', [
            'package_scope_id' => $scope->id,
            'feature_key' => 'medical.laboratory',
            'enabled' => false,
        ]);
    }

    public function test_destroy_prevents_delete_when_institutes_exist(): void
    {
        $this->loginAsAdmin();
        $pkg = $this->package('destroy');

        $scope = PackageScope::create([
            'package_id' => $pkg->id,
            'status' => 'active',
        ]);

        $this->institute(['package_id' => $pkg->id]);

        $this->delete(route('admin.scopes.destroy', $scope))
            ->assertStatus(422);

        $this->assertDatabaseHas('package_scopes', ['id' => $scope->id]);
    }
}
