<?php

namespace Tests\Feature;

use App\Models\FeatureRegistry;
use App\Models\Institute;
use App\Models\Membership;
use App\Models\PackageFeature;
use App\Models\Role;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Services\ModuleAccessService;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MedicalPharmacyFeatureGateTest extends TestCase
{
    use DatabaseTransactions;

    private ModuleAccessService $moduleAccess;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        if (! Schema::hasTable('feature_registry') || ! Schema::hasTable('package_features')) {
            $this->markTestSkipped('feature_registry or package_features table does not exist.');
        }

        if (FeatureRegistry::count() === 0) {
            (new \Database\Seeders\FeatureRegistrySeeder)->run();
        }
        if (PackageFeature::count() === 0) {
            (new \Database\Seeders\PackageFeatureSeeder)->run();
        }

        $this->moduleAccess = app(ModuleAccessService::class);
    }

    private function instituteWithPackage(string $packageSlug): Institute
    {
        $pkg = SubscriptionPackage::whereRaw('LOWER(slug) = ?', [strtolower($packageSlug)])->firstOrFail();
        $inst = Institute::create([
            'name' => 'PharmacyGate Test '.uniqid(),
            'slug' => 'pharmacy-gate-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
            'package_id' => $pkg->id,
        ]);
        DB::table('institute_subscriptions')->insert([
            'institute_id' => $inst->id,
            'package_id' => $pkg->id,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);
        return $inst;
    }

    private function user(Institute $inst): User
    {
        $user = User::factory()->create([
            'account_type' => 'owner',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        $roleId = Role::where('slug', 'institute-owner')->value('id');
        Membership::create([
            'user_id' => $user->id,
            'institution_id' => $inst->id,
            'role_id' => $roleId,
            'status' => 'active',
        ]);
        return $user;
    }

    public function test_pharmacy_route_accessible_when_feature_enabled(): void
    {
        $inst = $this->instituteWithPackage('advanced');
        $user = $this->user($inst);

        $this->actingAs($user, 'web');
        Workspace::set($inst->id);

        $response = $this->get(route('medical.pharmacy.medicines.index'));
        $response->assertOk();
    }

    public function test_pharmacy_route_blocked_when_feature_disabled(): void
    {
        $inst = $this->instituteWithPackage('basic');
        $user = $this->user($inst);

        $this->actingAs($user, 'web');
        Workspace::set($inst->id);

        $response = $this->get(route('medical.pharmacy.medicines.index'));
        $response->assertStatus(403);
    }

    public function test_pharmacy_route_blocked_when_module_disabled(): void
    {
        $inst = $this->instituteWithPackage('advanced');
        $this->moduleAccess->disableModule($inst, 'medical');
        $user = $this->user($inst);

        $this->actingAs($user, 'web');
        Workspace::set($inst->id);

        $response = $this->get(route('medical.pharmacy.medicines.index'));
        $response->assertStatus(403);
    }

    public function test_other_medical_routes_unaffected(): void
    {
        $inst = $this->instituteWithPackage('advanced');
        $user = $this->user($inst);

        $this->actingAs($user, 'web');
        Workspace::set($inst->id);

        $labRoute = route('medical.laboratory.orders.index');
        $response = $this->get($labRoute);
        $this->assertNotEquals(403, $response->getStatusCode(), 'Feature middleware should not block non-pharmacy routes.');
    }

    public function test_feature_middleware_only_on_pharmacy_routes(): void
    {
        $pharmacyRoute = Route::getRoutes()->getByName('medical.pharmacy.medicines.index');
        $this->assertNotNull($pharmacyRoute, 'Pharmacy route not found.');
        $this->assertContains('feature:medical.pharmacy', $pharmacyRoute->gatherMiddleware());

        $labRoute = Route::getRoutes()->getByName('medical.laboratory.orders.index');
        $this->assertNotNull($labRoute, 'Laboratory route not found.');
        $this->assertNotContains('feature:medical.pharmacy', $labRoute->gatherMiddleware());
    }

    public function test_all_pharmacy_routes_require_feature_gate(): void
    {
        $allRoutes = Route::getRoutes();
        $pharmacyRoutes = collect($allRoutes->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->getAction('as') ?? '', 'medical.pharmacy.'));

        $this->assertGreaterThanOrEqual(10, $pharmacyRoutes->count(), 'Expected at least 10 pharmacy routes.');

        foreach ($pharmacyRoutes as $route) {
            $name = $route->getAction('as');
            $middleware = $route->gatherMiddleware();

            $this->assertContains(
                'medical.module:medical.pharmacy',
                $middleware,
                "Route {$name} is missing medical.module:medical.pharmacy middleware."
            );
            $this->assertContains(
                'feature:medical.pharmacy',
                $middleware,
                "Route {$name} is missing feature:medical.pharmacy middleware."
            );
        }
    }
}
