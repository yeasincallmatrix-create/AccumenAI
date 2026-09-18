<?php

namespace Tests\Feature;

use App\Models\FeatureRegistry;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\PackageFeature;
use App\Models\PlatformAdmin;
use App\Models\Role;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CheckFeatureAccessMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('feature_registry') || ! Schema::hasTable('package_features')) {
            $this->markTestSkipped('feature_registry or package_features table does not exist.');
        }

        if (FeatureRegistry::count() === 0) {
            (new \Database\Seeders\FeatureRegistrySeeder)->run();
        }
        if (PackageFeature::count() === 0) {
            (new \Database\Seeders\PackageFeatureSeeder)->run();
        }

        // Define inline test routes (not in production route files)
        Route::get('/_test/feature-gate', function () {
            return response('ok');
        })->middleware('feature:medical.pharmacy');

        Route::get('/_test/feature-gate-invalid', function () {
            return response('ok');
        })->middleware('feature:invalid-no-dot');
    }

    private function package(string $slug): SubscriptionPackage
    {
        return SubscriptionPackage::whereRaw('LOWER(slug) = ?', [strtolower($slug)])->firstOrFail();
    }

    private function institute(string $packageSlug, string $industry = 'healthcare'): Institute
    {
        $pkg = $this->package($packageSlug);
        return Institute::create([
            'name' => 'FeatureGate Test '.uniqid(),
            'slug' => 'feature-gate-'.uniqid(),
            'status' => 'active',
            'package_id' => $pkg->id,
            'industry' => $industry,
        ]);
    }

    private function user(Institute $inst, string $roleSlug = 'institute-owner'): InstituteUser
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();
        $prev = \App\Support\TenantContext::id();
        \App\Support\TenantContext::clear();
        $user = InstituteUser::create([
            'institute_id' => $inst->id,
            'role_id' => $role->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $roleSlug.'-'.uniqid().'@test.local',
            'phone' => '017'.rand(10000000, 99999999),
            'password_hash' => bcrypt('secret'),
            'status' => 'active',
        ]);
        if ($prev !== null) {
            \App\Support\TenantContext::set($prev);
        }
        return $user;
    }

    public function test_allows_when_feature_enabled(): void
    {
        $inst = $this->institute('advanced');
        $user = $this->user($inst);

        \App\Support\TenantContext::set($inst->id);
        $this->actingAs($user, 'institute_user')
            ->get('/_test/feature-gate')
            ->assertOk();
    }

    public function test_blocks_when_feature_disabled(): void
    {
        $inst = $this->institute('basic');
        $user = $this->user($inst);

        \App\Support\TenantContext::set($inst->id);
        $this->actingAs($user, 'institute_user')
            ->get('/_test/feature-gate')
            ->assertForbidden();
    }

    public function test_platform_admin_bypasses_feature_check(): void
    {
        $inst = $this->institute('basic');
        $admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'feat-admin-'.uniqid().'@example.test',
            'password_hash' => bcrypt('secret'),
            'status' => 'active',
        ]);

        \App\Support\TenantContext::set($inst->id);
        $this->actingAs($admin, 'platform_admin')
            ->get('/_test/feature-gate')
            ->assertOk();
    }

    public function test_blocks_when_no_tenant_context(): void
    {
        $this->get('/_test/feature-gate')
            ->assertForbidden();
    }

    public function test_blocks_when_parent_module_disabled(): void
    {
        $inst = $this->institute('advanced');
        $user = $this->user($inst);

        app(ModuleAccessService::class)->disableModule($inst, 'medical', null, 'Test disable');

        \App\Support\TenantContext::set($inst->id);
        $this->actingAs($user, 'institute_user')
            ->get('/_test/feature-gate')
            ->assertForbidden();
    }

    public function test_fail_closed_on_invalid_feature_key(): void
    {
        $inst = $this->institute('advanced');
        $user = $this->user($inst);

        \App\Support\TenantContext::set($inst->id);
        $this->actingAs($user, 'institute_user')
            ->get('/_test/feature-gate-invalid')
            ->assertForbidden();
    }
}
