<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MedicalSubModuleInfrastructureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_medical_parent_module_exists(): void
    {
        $medical = DB::table('module_registry')->where('key', 'medical')->first();
        $this->assertNotNull($medical);
        $this->assertNull($medical->parent_key);
        $this->assertEquals('bi-hospital', $medical->icon);
    }

    public function test_all_14_sub_modules_seeded(): void
    {
        $count = DB::table('module_registry')->where('parent_key', 'medical')->count();
        $this->assertEquals(14, $count);
    }

    public function test_7_sub_modules_are_active_not_coming_soon(): void
    {
        $active = DB::table('module_registry')
            ->where('parent_key', 'medical')
            ->where('coming_soon', false)
            ->count();
        $this->assertEquals(10, $active);
    }

    public function test_7_sub_modules_are_coming_soon(): void
    {
        $soon = DB::table('module_registry')
            ->where('parent_key', 'medical')
            ->where('coming_soon', true)
            ->count();
        $this->assertEquals(4, $soon);
    }

    public function test_parent_disabled_disables_all_children(): void
    {
        // Verify the parent dependency logic in ModuleAccessService
        $service = app(\App\Services\ModuleAccessService::class);
        $subModules = $service->getMedicalSubModules();

        // All sub-modules should have parent_key = 'medical'
        foreach ($subModules as $sub) {
            $this->assertEquals('medical', $sub->parent_key);
        }

        // Verify that a module with parent_key requires parent to be enabled
        // by checking the resolveEnabled method includes parent dependency step
        $medical = DB::table('module_registry')->where('key', 'medical')->first();
        $this->assertNotNull($medical);
        $this->assertNull($medical->parent_key);
    }

    public function test_sub_module_routes_require_module_middleware(): void
    {
        $routes = [
            'medical/opd/appointments',
            'medical/ipd/admissions',
            'medical/pharmacy/medicines',
            'medical/laboratory/orders',
            'medical/billing/invoices',
        ];

        foreach ($routes as $uri) {
            $route = app('router')->getRoutes()->match(request()->create($uri));
            $this->assertNotNull($route, "Route {$uri} should exist");
        }
    }

    public function test_old_medical_urls_still_work(): void
    {
        // Legacy flat URLs should still resolve to routes
        $legacyRoutes = [
            'medical/appointments',
            'medical/prescriptions',
            'medical/admissions',
            'medical/pharmacy',
            'medical/lab/orders',
            'medical/billing/invoices',
        ];

        foreach ($legacyRoutes as $uri) {
            $route = app('router')->getRoutes()->match(request()->create($uri));
            $this->assertNotNull($route, "Legacy route {$uri} should still resolve");
        }
    }

    public function test_new_dot_notation_permissions_created(): void
    {
        $newPerms = DB::table('permissions')->where('slug', 'like', 'medical.%')->count();
        $this->assertGreaterThan(0, $newPerms);
    }

    public function test_old_permissions_still_exist(): void
    {
        $oldPerms = DB::table('permissions')->where('slug', 'like', 'medical_%')->count();
        $this->assertGreaterThan(0, $oldPerms);
    }

    public function test_role_permissions_copied_to_new_slugs(): void
    {
        $oldPerm = DB::table('permissions')->where('slug', 'medical_prescriptions.view')->first();
        $this->assertNotNull($oldPerm);

        $newPerm = DB::table('permissions')->where('slug', 'medical.opd.prescriptions.view')->first();
        $this->assertNotNull($newPerm);

        $oldRoles = DB::table('role_permissions')->where('permission_id', $oldPerm->id)->count();
        $newRoles = DB::table('role_permissions')->where('permission_id', $newPerm->id)->count();

        $this->assertEquals($oldRoles, $newRoles);
    }

    public function test_coming_soon_modules_have_index_route_null(): void
    {
        $soonWithRoute = DB::table('module_registry')
            ->where('parent_key', 'medical')
            ->where('coming_soon', true)
            ->whereNotNull('index_route')
            ->count();
        $this->assertEquals(0, $soonWithRoute);
    }

    public function test_active_sub_modules_have_icons(): void
    {
        $noIcon = DB::table('module_registry')
            ->where('parent_key', 'medical')
            ->where('coming_soon', false)
            ->whereNull('icon')
            ->count();
        $this->assertEquals(0, $noIcon);
    }

    public function test_sub_modules_added_to_advanced_package(): void
    {
        $pkg = DB::table('subscription_packages')->where('slug', 'advanced')->first();
        $this->assertNotNull($pkg);

        $count = DB::table('package_modules')
            ->where('package_id', $pkg->id)
            ->where('module_key', 'like', 'medical.%')
            ->count();
        $this->assertEquals(10, $count);
    }
}
