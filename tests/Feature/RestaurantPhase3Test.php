<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Restaurant Phase 3 — Kitchen Operations: 6 more children under
 * `restaurant` (kitchen, kds, kot, chef, station, recipe), the kitchen
 * engine in config/restaurant.php, industry defaults + sub-category
 * defaults, expanded package tiers (12/26/39) and the new kitchen
 * permissions.
 *
 * Phases 1 and 2 stay asserted in their own phase tests.
 */
class RestaurantPhase3Test extends TestCase
{
    use DatabaseTransactions;

    /**
     * @var array<int, string>
     */
    private const PHASE3_KEYS = [
        'restaurant.kitchen',
        'restaurant.kds',
        'restaurant.kot',
        'restaurant.chef',
        'restaurant.station',
        'restaurant.recipe',
    ];

    private function platformAdmin(): PlatformAdmin
    {
        TenantContext::clear();

        return PlatformAdmin::firstOrReuseForTests([
            'email' => 'platform-'.uniqid().'@example.test',
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    public function test_phase3_children_exist()
    {
        foreach (self::PHASE3_KEYS as $key) {
            $this->assertTrue(
                DB::table('module_registry')->where('key', $key)->exists(),
                "Missing: {$key}"
            );
        }
    }

    public function test_phase3_children_count()
    {
        $count = DB::table('module_registry')
            ->whereIn('key', self::PHASE3_KEYS)
            ->where('status', 'active')
            ->count();
        $this->assertEquals(6, $count);
    }

    public function test_restaurant_has_18_children()
    {
        $count = DB::table('module_registry')
            ->where('parent_key', 'restaurant')
            ->where('status', 'active')
            ->count();
        $this->assertEquals(18, $count);

        $children = DB::table('module_registry')
            ->where('parent_key', 'restaurant')
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->pluck('key')
            ->all();

        $this->assertSame(
            [
                'restaurant.menu', 'restaurant.menu_category', 'restaurant.menu_item',
                'restaurant.table', 'restaurant.table_layout', 'restaurant.reservation',
                'restaurant.dine_in', 'restaurant.takeaway', 'restaurant.delivery',
                'restaurant.order', 'restaurant.order_tracking', 'restaurant.pre_order',
                'restaurant.kitchen', 'restaurant.kds', 'restaurant.kot',
                'restaurant.chef', 'restaurant.station', 'restaurant.recipe',
            ],
            $children,
            'phase 1 (1-12), phase 2 (20-25), phase 3 (30-35) keep their sort_order'
        );

        foreach (self::PHASE3_KEYS as $key) {
            $row = DB::table('module_registry')->where('key', $key)->first();
            $this->assertSame('restaurant', $row->parent_key, "{$key} parent");
            $this->assertSame('industry', $row->type, "{$key} inherits parent type");
            $this->assertSame(0, (int) $row->is_core, "{$key} inherits parent is_core");
            $this->assertSame('active', $row->status, "{$key} active");
        }
    }

    public function test_restaurant_config_has_kitchen_engine()
    {
        $config = config('restaurant');
        $this->assertArrayHasKey('kitchen', $config['engines']);
        $this->assertSame(
            self::PHASE3_KEYS,
            array_keys($config['engines']['kitchen']['modules'])
        );

        $matrix = config('industry-modules')['restaurant'];
        foreach (['restaurant.kitchen', 'restaurant.kds', 'restaurant.kot'] as $key) {
            $this->assertContains($key, $matrix['default'], "{$key} is an industry default");
        }
        foreach (['restaurant.chef', 'restaurant.station', 'restaurant.recipe'] as $key) {
            $this->assertContains($key, $matrix['optional'], "{$key} is optional");
        }
    }

    public function test_previous_phases_unchanged()
    {
        $phase1 = [
            'restaurant.menu', 'restaurant.menu_category', 'restaurant.menu_item',
            'restaurant.table', 'restaurant.table_layout', 'restaurant.reservation',
        ];
        $phase2 = [
            'restaurant.dine_in', 'restaurant.takeaway', 'restaurant.delivery',
            'restaurant.order', 'restaurant.order_tracking', 'restaurant.pre_order',
        ];

        foreach (array_merge($phase1, $phase2) as $key) {
            $this->assertTrue(DB::table('module_registry')->where('key', $key)->exists(), "Missing: {$key}");
        }

        $this->assertEquals(6, DB::table('module_registry')
            ->where('parent_key', 'restaurant')
            ->whereIn('key', $phase1)
            ->where('status', 'active')
            ->count());
        $this->assertEquals(6, DB::table('module_registry')
            ->where('parent_key', 'restaurant')
            ->whereIn('key', $phase2)
            ->where('status', 'active')
            ->count());
    }

    public function test_permissions_count_45()
    {
        $count = DB::table('permissions')
            ->where('name', 'LIKE', 'restaurant.%')
            ->count();
        $this->assertGreaterThanOrEqual(45, $count);

        $kitchenPermissions = DB::table('permissions')
            ->whereIn('name', [
                'restaurant.kitchen.view', 'restaurant.kitchen.manage',
                'restaurant.kds.view', 'restaurant.kds.manage',
                'restaurant.kot.view', 'restaurant.kot.manage',
                'restaurant.chef.view', 'restaurant.chef.manage',
                'restaurant.station.view', 'restaurant.station.manage',
                'restaurant.recipe.view', 'restaurant.recipe.manage',
            ])
            ->count();
        $this->assertEquals(12, $kitchenPermissions, '12 kitchen permissions (kitchen.view shared with the module-level capability)');
    }

    public function test_packages_expanded()
    {
        $expected = [
            'restaurant_starter' => 12,
            'restaurant_growth' => 26,
            'restaurant_enterprise' => 39,
        ];

        foreach ($expected as $slug => $count) {
            $modules = DB::table('package_industry_modules as pim')
                ->join('subscription_packages as p', 'p.id', '=', 'pim.package_id')
                ->where('p.slug', $slug)
                ->where('pim.industry_key', 'restaurant')
                ->where('pim.enabled', 1)
                ->pluck('pim.module_key')
                ->all();

            $this->assertCount($count, $modules, "{$slug} modules");
            $this->assertContains('restaurant.kitchen', $modules, "{$slug} has kitchen");
        }

        $starter = DB::table('package_industry_modules as pim')
            ->join('subscription_packages as p', 'p.id', '=', 'pim.package_id')
            ->where('p.slug', 'restaurant_starter')
            ->where('pim.industry_key', 'restaurant')
            ->pluck('pim.module_key')
            ->all();

        $this->assertContains('restaurant.kot', $starter);
        $this->assertNotContains('restaurant.kds', $starter, 'starter tier stays lean');
        $this->assertNotContains('restaurant.recipe', $starter, 'starter tier stays lean');
    }

    public function test_page_renders_phase3_children()
    {
        $html = $this->actingAs($this->platformAdmin(), 'platform_admin')
            ->get(route('admin.module-config.index', [
                'industry' => 'restaurant',
                'subcategory' => 'fine_dining',
            ]))
            ->assertOk()
            ->assertSee('restaurant.kitchen', false)
            ->assertSee('restaurant.kds', false)
            ->getContent();

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_use_internal_errors(false);
        $xpath = new \DOMXPath($dom);

        $this->assertSame(
            18,
            $xpath->query('//tr[@data-child-of="restaurant"]')->length,
            'the matrix renders all 18 restaurant children (6 + 6 + 6)'
        );

        foreach (self::PHASE3_KEYS as $key) {
            $this->assertSame(
                1,
                $xpath->query('//tr[@data-child-of="restaurant"]//code[text()="'.$key.'"]')->length,
                "phase 3 row {$key} renders as an indented child of restaurant"
            );
        }
    }

    public function test_subcategory_defaults_cover_kitchen_modules()
    {
        $expected = [
            'fine_dining' => ['restaurant.kitchen', 'restaurant.kds', 'restaurant.kot', 'restaurant.chef', 'restaurant.station', 'restaurant.recipe'],
            'casual' => ['restaurant.kitchen', 'restaurant.kds', 'restaurant.kot', 'restaurant.chef', 'restaurant.station', 'restaurant.recipe'],
            'cafe' => ['restaurant.kitchen', 'restaurant.kot', 'restaurant.kds', 'restaurant.recipe'],
            'fast_food' => ['restaurant.kitchen', 'restaurant.kds', 'restaurant.kot'],
            'bakery' => ['restaurant.kitchen', 'restaurant.recipe', 'restaurant.kot'],
            'food_court' => ['restaurant.kitchen', 'restaurant.kds', 'restaurant.kot'],
            'cloud_kitchen' => ['restaurant.kitchen', 'restaurant.kds', 'restaurant.kot', 'restaurant.recipe'],
        ];

        foreach ($expected as $subKey => $modules) {
            $sub = DB::table('industry_subcategories')
                ->where('industry_key', 'restaurant')
                ->where('subcategory_key', $subKey)
                ->firstOrFail();

            $defaults = DB::table('subcategory_default_modules')
                ->where('subcategory_id', $sub->id)
                ->pluck('module_key')
                ->all();

            foreach ($modules as $key) {
                $this->assertContains($key, $defaults, "{$subKey} defaults include {$key}");
            }
        }
    }
}
