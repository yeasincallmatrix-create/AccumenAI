<?php

namespace Tests\Feature;

use App\Http\Controllers\InstituteOnboardingController;
use App\Models\Country;
use App\Models\Institute;
use App\Services\ModuleAccessService;
use App\Support\IndustryRules;
use Database\Seeders\IndustryTaxonomySeeder;
use Database\Seeders\PackageSeeder;
use Database\Seeders\RestaurantPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 2 - Restaurant promoted to a MAIN INDUSTRY.
 *
 * Covers the eight delivery tasks: legacy retail.restaurant deprecation,
 * industry compatibility, taxonomy sub-categories, onboarding validation,
 * package tiers, sidebar group, permissions, plus seeder idempotency.
 */
class RestaurantMainIndustryTest extends TestCase
{
    use DatabaseTransactions;

    private const RESTAURANT_SUBS = [
        'fine_dining', 'casual', 'fast_food', 'cafe', 'bakery', 'food_court', 'cloud_kitchen',
    ];

    private const RESTAURANT_ROUTES = [
        'restaurant.menu.index',
        'restaurant.menu-category.index',
        'restaurant.menu-item.index',
        'restaurant.table.index',
        'restaurant.table-layout.index',
        'restaurant.reservation.index',
    ];

    private const RESTAURANT_PACKAGES = [
        'restaurant_starter' => 16,
        'restaurant_growth' => 36,
        'restaurant_enterprise' => 51,
    ];

    private ModuleAccessService $modules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->modules = app(ModuleAccessService::class);
    }

    private function country(): Country
    {
        return Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]
        );
    }

    private function institute(string $industry, ?string $sub = null, ?string $packageSlug = null): Institute
    {
        $country = $this->country();

        $institute = Institute::create([
            'name' => 'RMI ' . uniqid(),
            'slug' => 'rmi-' . uniqid(),
            'country' => $country->name,
            'country_id' => $country->id,
            'industry' => $industry,
            'sub_industry' => $sub,
            'status' => 'active',
        ]);

        $packageId = $packageSlug
            ? DB::table('subscription_packages')->whereRaw('LOWER(slug) = ?', [$packageSlug])->value('id')
            : DB::table('subscription_packages')->whereRaw('LOWER(slug) = ?', ['free'])->value('id');

        if ($packageId) {
            $institute->forceFill(['package_id' => $packageId])->save();

            DB::table('institute_subscriptions')->insert([
                'institute_id' => $institute->id,
                'package_id' => $packageId,
                'billing_cycle' => 'monthly',
                'price_paid' => 0,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addYear()->toDateString(),
                'status' => 'active',
                'created_at' => now(),
            ]);
        }

        $this->modules->flushCache($institute->id);

        return $institute;
    }

    // 1 - Legacy retail.restaurant is deprecated (kept, inactive).

    public function test_retail_restaurant_subcategory_is_deprecated(): void
    {
        $row = DB::table('industry_subcategories')
            ->where('industry_key', 'retail')
            ->where('subcategory_key', 'restaurant')
            ->first();

        $this->assertNotNull($row, 'legacy retail.restaurant row must be kept (deprecate, do not delete)');
        $this->assertFalse((bool) $row->is_active, 'legacy retail.restaurant must be deactivated');

        $restaurantRows = DB::table('industry_subcategories')
            ->where('industry_key', 'restaurant')
            ->where('is_active', 1)
            ->count();

        $this->assertGreaterThanOrEqual(7, $restaurantRows, 'restaurant is the main industry with its own sub-categories');
    }

    // 2 - Restaurant modules are industry-compatible only for restaurant.

    public function test_restaurant_modules_are_scoped_to_the_restaurant_industry(): void
    {
        $restaurant = $this->institute('restaurant', 'fine_dining');

        $this->assertTrue($this->modules->isIndustryCompatible($restaurant, 'restaurant'));
        $this->assertTrue($this->modules->isIndustryCompatible($restaurant, 'restaurant.menu'));
        $this->assertTrue($this->modules->isIndustryCompatible($restaurant, 'restaurant.reservation'));
        $this->assertTrue($this->modules->isIndustryCompatible($restaurant, 'finance'));

        $retail = $this->institute('retail', 'supermarket');
        $education = $this->institute('education', 'school');
        $healthcare = $this->institute('healthcare', 'clinic');

        $this->assertFalse($this->modules->isIndustryCompatible($retail, 'restaurant'));
        $this->assertFalse($this->modules->isIndustryCompatible($retail, 'restaurant.menu'));
        $this->assertFalse($this->modules->isIndustryCompatible($education, 'restaurant'));
        $this->assertFalse($this->modules->isIndustryCompatible($healthcare, 'restaurant'));
        $this->assertFalse($this->modules->isIndustryCompatible($restaurant, 'education'));
    }

    // 3 - Sub-industries available for the onboarding dropdown.

    public function test_restaurant_sub_industries_match_the_phase1_matrix(): void
    {
        $this->assertArrayHasKey('restaurant', IndustryRules::industries('Bangladesh'));

        $subs = IndustryRules::subIndustries('Bangladesh', 'restaurant');
        $this->assertCount(7, $subs);
        $this->assertTrue(IndustryRules::hasSubIndustries('Bangladesh', 'restaurant'));

        $subsKeys = array_keys($subs);
        sort($subsKeys);

        $expected = self::RESTAURANT_SUBS;
        sort($expected);
        $this->assertSame($expected, $subsKeys, '7 restaurant sub-industries for Bangladesh');

        $matrix = DB::table('industry_subcategories')
            ->where('industry_key', 'restaurant')
            ->where('is_active', 1)
            ->pluck('subcategory_key')
            ->sort()
            ->values()
            ->all();

        $this->assertSame($matrix, $subsKeys, 'dropdown options match the module-config matrix');
    }

    // 4 - Registration / onboarding validation accepts restaurant.

    public function test_restaurant_selection_is_validated_for_registration(): void
    {
        $accepted = InstituteOnboardingController::validatedSelection([
            'country' => 'Bangladesh',
            'industry' => 'restaurant',
            'sub_industry' => 'fine_dining',
        ]);

        $this->assertSame('restaurant', $accepted['industry']);
        $this->assertSame('fine_dining', $accepted['sub_industry']);

        $retail = InstituteOnboardingController::validatedSelection([
            'country' => 'Bangladesh',
            'industry' => 'retail',
            'sub_industry' => 'general_store',
        ]);

        $this->assertSame('retail', $retail['industry']);

        try {
            InstituteOnboardingController::validatedSelection([
                'country' => 'Bangladesh',
                'industry' => 'restaurant',
            ]);
            $this->fail('restaurant without sub-industry must be rejected in Bangladesh');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('sub_industry', $e->errors());
        }

        try {
            InstituteOnboardingController::validatedSelection([
                'country' => 'Bangladesh',
                'industry' => 'restaurant',
                'sub_industry' => 'night_club',
            ]);
            $this->fail('unknown restaurant sub-industry must be rejected');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('sub_industry', $e->errors());
        }

        try {
            InstituteOnboardingController::validatedSelection([
                'country' => 'Bangladesh',
                'industry' => 'restaurantx',
            ]);
            $this->fail('unknown industry must be rejected');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('industry', $e->errors());
        }
    }

    // 5 - Three restaurant packages wired into the industry/module mapping.

    public function test_restaurant_package_tiers_are_seeded_and_linked(): void
    {
        $packageIds = [];

        foreach (array_keys(self::RESTAURANT_PACKAGES) as $slug) {
            $package = DB::table('subscription_packages')->where('slug', $slug)->first();

            $this->assertNotNull($package, "package {$slug} must exist");
            $this->assertSame('active', $package->status);
            $this->assertEquals(0, (int) $package->is_default);
            $this->assertEquals(
                (float) $package->price_monthly * 10,
                (float) $package->price_yearly,
                "{$slug} yearly price is 10x monthly"
            );

            $packageIds[] = $package->id;
        }

        $mapped = DB::table('package_industries')
            ->where('industry_key', 'restaurant')
            ->where('is_active', 1)
            ->pluck('package_id')
            ->sort()
            ->values()
            ->all();

        sort($packageIds);
        $this->assertSame($packageIds, $mapped, 'restaurant is offered on exactly its 3 tiers');

        $moduleSets = [];
        foreach (array_keys(self::RESTAURANT_PACKAGES) as $slug) {
            $moduleSets[$slug] = $this->packageModules($slug);
        }

        $starter = $moduleSets['restaurant_starter'];
        $growth = $moduleSets['restaurant_growth'];
        $enterprise = $moduleSets['restaurant_enterprise'];

        $this->assertCount(self::RESTAURANT_PACKAGES['restaurant_starter'], $starter);
        $this->assertCount(self::RESTAURANT_PACKAGES['restaurant_growth'], $growth);
        $this->assertCount(self::RESTAURANT_PACKAGES['restaurant_enterprise'], $enterprise);

        $this->assertSame([], array_diff($starter, $growth), 'starter modules are a subset of growth');
        $this->assertSame([], array_diff($growth, $enterprise), 'growth modules are a subset of enterprise');

        foreach (['restaurant', 'restaurant.menu', 'restaurant.reservation'] as $moduleKey) {
            $this->assertContains($moduleKey, $starter, "{$moduleKey} ships on every tier");
        }

        $registry = DB::table('module_registry')->pluck('key')->all();
        foreach ($enterprise as $moduleKey) {
            $this->assertContains($moduleKey, $registry, "package module {$moduleKey} must exist in the registry");
        }
    }

    // 6 - Sidebar group exists, placed after Finance and properly gated.

    public function test_sidebar_renders_the_restaurant_group_after_finance(): void
    {
        $blade = file_get_contents(resource_path('views/layouts/institute.blade.php'));

        $this->assertStringContainsString('id="restaurantNavGroup"', $blade, 'restaurant nav-group present');
        $this->assertStringContainsString('fw-semibold">Restaurant<', $blade, 'restaurant group label present');
        $this->assertStringContainsString("moduleEnabled('restaurant')", $blade, 'group gated by module enablement');
        $this->assertStringContainsString('Route::has($restaurantRoute)', $blade, 'links guarded until routes exist');

        foreach (self::RESTAURANT_ROUTES as $route) {
            $this->assertStringContainsString("'{$route}'", $blade, "sidebar lists {$route}");
        }

        $finance = strpos($blade, 'id="financeNavGroup"');
        $restaurant = strpos($blade, 'id="restaurantNavGroup"');

        $this->assertNotFalse($finance, 'finance group present');
        $this->assertNotFalse($restaurant, 'restaurant group present');
        $this->assertGreaterThan($finance, $restaurant, 'restaurant group renders after finance');
    }

    // 7 - 69 restaurant permissions exist.

    public function test_restaurant_permissions_are_seeded(): void
    {
        $declared = RestaurantPermissionSeeder::permissions();

        $this->assertCount(69, $declared, '69 declared permissions');
        $this->assertCount(69, array_unique(array_column($declared, 'slug')), 'declared slugs unique');

        foreach ($declared as $permission) {
            $this->assertSame('restaurant', $permission['module']);
            $this->assertStringStartsWith('restaurant.', $permission['slug']);
        }

        $rows = DB::table('permissions')->where('module', 'restaurant')->get();

        $this->assertCount(69, $rows, '69 restaurant permissions persisted');
        $this->assertCount(69, $rows->pluck('slug')->unique(), 'persisted slugs unique');
        $this->assertCount(
            69,
            $rows->filter(static fn ($row) => str_starts_with((string) $row->name, 'restaurant.')),
            'names carry the permission key'
        );

        foreach (array_column($declared, 'slug') as $slug) {
            $this->assertTrue($rows->pluck('slug')->contains($slug), "missing permission {$slug}");
        }
    }

    // 8 - Module resolution: restaurant modules on, other industries off.

    public function test_restaurant_modules_resolve_for_a_restaurant_tenant(): void
    {
        $restaurant = $this->institute('restaurant', 'fine_dining');

        $this->assertTrue($this->modules->isEnabled($restaurant, 'restaurant'));
        $this->assertTrue($this->modules->isEnabled($restaurant, 'restaurant.menu'));
        $this->assertTrue($this->modules->isEnabled($restaurant, 'restaurant.table_layout'));
        $this->assertTrue($this->modules->isEnabled($restaurant, 'restaurant.reservation'));
        $this->assertFalse($this->modules->isEnabled($restaurant, 'education'));
        $this->assertFalse($this->modules->isEnabled($restaurant, 'medical'));

        $retail = $this->institute('retail', 'supermarket');
        $this->assertFalse($this->modules->isEnabled($retail, 'restaurant'));

        $growth = $this->institute('restaurant', 'cafe', 'restaurant_growth');
        $this->assertTrue($this->modules->isEnabled($growth, 'restaurant'));
        $this->assertTrue($this->modules->isEnabled($growth, 'pos.loyalty'), 'growth tier unlocks pos.loyalty');
        $this->assertTrue($this->modules->isEnabled($growth, 'pos.split_payment'), 'growth tier unlocks pos.split_payment');

        $starter = $this->institute('restaurant', 'cafe', 'restaurant_starter');
        $this->assertFalse($this->modules->isEnabled($starter, 'pos.loyalty'), 'starter tier does not unlock pos.loyalty');
    }

    // 9 - Seeders stay idempotent.

    public function test_restaurant_seeders_are_idempotent(): void
    {
        $before = [
            'packages' => DB::table('subscription_packages')->count(),
            'package_industries' => DB::table('package_industries')->count(),
            'package_industry_modules' => DB::table('package_industry_modules')->count(),
            'package_modules' => DB::table('package_modules')->count(),
            'permissions' => DB::table('permissions')->count(),
            'sub_industries' => DB::table('sub_industries')->count(),
            'industry_subcategories' => DB::table('industry_subcategories')->count(),
        ];

        (new PackageSeeder)->run();
        (new RestaurantPermissionSeeder)->run();
        (new IndustryTaxonomySeeder)->run();

        (new PackageSeeder)->run();
        (new RestaurantPermissionSeeder)->run();
        (new IndustryTaxonomySeeder)->run();

        $this->assertSame($before, [
            'packages' => DB::table('subscription_packages')->count(),
            'package_industries' => DB::table('package_industries')->count(),
            'package_industry_modules' => DB::table('package_industry_modules')->count(),
            'package_modules' => DB::table('package_modules')->count(),
            'permissions' => DB::table('permissions')->count(),
            'sub_industries' => DB::table('sub_industries')->count(),
            'industry_subcategories' => DB::table('industry_subcategories')->count(),
        ], 're-running the restaurant seeders must not change row counts');
    }

    /**
     * @return array<int, string>
     */
    private function packageModules(string $slug): array
    {
        return DB::table('package_industry_modules as pim')
            ->join('subscription_packages as p', 'p.id', '=', 'pim.package_id')
            ->where('p.slug', $slug)
            ->where('pim.industry_key', 'restaurant')
            ->where('pim.enabled', 1)
            ->orderBy('pim.module_key')
            ->pluck('pim.module_key')
            ->all();
    }
}
