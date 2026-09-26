<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Restaurant Phase 2 — Order Types: 6 more children under `restaurant`
 * (dine_in, takeaway, delivery, order, order_tracking, pre_order), the
 * orders engine in config/restaurant.php, industry defaults + sub-category
 * defaults for the 7 sub-industries, the expanded package tiers and the 12
 * new order permissions.
 *
 * Phase 1 assertions stay in RestaurantPhase1Test.
 */
class RestaurantPhase2Test extends TestCase
{
    use DatabaseTransactions;

    /**
     * @var array<int, string>
     */
    private const PHASE2_KEYS = [
        'restaurant.dine_in',
        'restaurant.takeaway',
        'restaurant.delivery',
        'restaurant.order',
        'restaurant.order_tracking',
        'restaurant.pre_order',
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

    public function test_phase2_children_exist()
    {
        foreach (self::PHASE2_KEYS as $key) {
            $this->assertTrue(
                DB::table('module_registry')->where('key', $key)->exists(),
                "Missing: {$key}"
            );
        }
    }

    public function test_phase2_children_count()
    {
        $count = DB::table('module_registry')
            ->whereIn('key', self::PHASE2_KEYS)
            ->where('status', 'active')
            ->count();
        $this->assertEquals(6, $count);
    }

    public function test_restaurant_has_12_children()
    {
        $count = DB::table('module_registry')
            ->where('parent_key', 'restaurant')
            ->whereIn('key', array_merge([
                'restaurant.menu', 'restaurant.menu_category', 'restaurant.menu_item',
                'restaurant.table', 'restaurant.table_layout', 'restaurant.reservation',
            ], self::PHASE2_KEYS))
            ->where('status', 'active')
            ->count();
        $this->assertEquals(12, $count);

        $children = DB::table('module_registry')
            ->where('parent_key', 'restaurant')
            ->whereIn('key', array_merge([
                'restaurant.menu', 'restaurant.menu_category', 'restaurant.menu_item',
                'restaurant.table', 'restaurant.table_layout', 'restaurant.reservation',
            ], self::PHASE2_KEYS))
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
            ],
            $children,
            'phase 1 rows keep sort_order 1-12, phase 2 rows 20-25'
        );

        foreach (self::PHASE2_KEYS as $key) {
            $row = DB::table('module_registry')->where('key', $key)->first();
            $this->assertSame('restaurant', $row->parent_key, "{$key} parent");
            $this->assertSame('industry', $row->type, "{$key} inherits parent type");
            $this->assertSame('active', $row->status, "{$key} active");
        }
    }

    public function test_restaurant_config_has_orders_engine()
    {
        $config = config('restaurant');
        $this->assertArrayHasKey('orders', $config['engines']);
        $this->assertSame(
            self::PHASE2_KEYS,
            array_keys($config['engines']['orders']['modules'])
        );

        $matrix = config('industry-modules')['restaurant'];
        foreach (['restaurant.dine_in', 'restaurant.takeaway', 'restaurant.delivery', 'restaurant.order'] as $key) {
            $this->assertContains($key, $matrix['default'], "{$key} is an industry default");
        }
        foreach (['restaurant.order_tracking', 'restaurant.pre_order'] as $key) {
            $this->assertContains($key, $matrix['optional'], "{$key} is optional");
        }
    }

    public function test_phase1_unchanged()
    {
        foreach ([
            'restaurant.menu', 'restaurant.menu_category', 'restaurant.menu_item',
            'restaurant.table', 'restaurant.table_layout', 'restaurant.reservation',
        ] as $key) {
            $this->assertTrue(DB::table('module_registry')->where('key', $key)->exists());
        }

        $this->assertEquals(6, DB::table('module_registry')
            ->where('parent_key', 'restaurant')
            ->whereIn('key', [
                'restaurant.menu', 'restaurant.menu_category', 'restaurant.menu_item',
                'restaurant.table', 'restaurant.table_layout', 'restaurant.reservation',
            ])
            ->where('status', 'active')
            ->count());
    }

    public function test_permissions_count_34()
    {
        $count = DB::table('permissions')
            ->where('name', 'LIKE', 'restaurant.%')
            ->count();
        $this->assertGreaterThanOrEqual(34, $count);

        $orderPermissions = DB::table('permissions')
            ->whereIn('name', [
                'restaurant.dine_in.view', 'restaurant.dine_in.manage',
                'restaurant.takeaway.view', 'restaurant.takeaway.manage',
                'restaurant.delivery.view', 'restaurant.delivery.manage',
                'restaurant.order.view', 'restaurant.order.manage',
                'restaurant.order_tracking.view', 'restaurant.order_tracking.manage',
                'restaurant.pre_order.view', 'restaurant.pre_order.manage',
            ])
            ->count();
        $this->assertEquals(12, $orderPermissions, '12 order-type permissions');
    }

    public function test_page_renders_phase2_children()
    {
        $html = $this->actingAs($this->platformAdmin(), 'platform_admin')
            ->get(route('admin.module-config.index', [
                'industry' => 'restaurant',
                'subcategory' => 'fine_dining',
            ]))
            ->assertOk()
            ->assertSee('restaurant.dine_in', false)
            ->assertSee('restaurant.delivery', false)
            ->getContent();

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_use_internal_errors(false);
        $xpath = new \DOMXPath($dom);

        foreach (self::PHASE2_KEYS as $key) {
            $this->assertSame(
                1,
                $xpath->query('//tr[@data-child-of="restaurant"]//code[text()="'.$key.'"]')->length,
                "phase 2 row {$key} renders as an indented child of restaurant"
            );
        }
        $this->assertGreaterThanOrEqual(
            12,
            $xpath->query('//tr[@data-child-of="restaurant"]')->length,
            'phase 1 + phase 2 rows render (later phases only add rows)'
        );
        $this->assertSame(
            1,
            $xpath->query('//*[@data-module-toggle="restaurant"]')->length,
            'restaurant renders as a single collapsible parent row'
        );
    }

    public function test_subcategory_and_package_defaults_cover_order_types()
    {
        $fineDining = DB::table('industry_subcategories')
            ->where('industry_key', 'restaurant')
            ->where('subcategory_key', 'fine_dining')
            ->firstOrFail();

        $defaults = DB::table('subcategory_default_modules')
            ->where('subcategory_id', $fineDining->id)
            ->pluck('module_key')
            ->all();

        foreach (self::PHASE2_KEYS as $key) {
            $this->assertContains($key, $defaults, "fine_dining defaults include {$key}");
        }

        $packageModules = DB::table('package_industry_modules as pim')
            ->join('subscription_packages as p', 'p.id', '=', 'pim.package_id')
            ->where('p.slug', 'restaurant_starter')
            ->where('pim.industry_key', 'restaurant')
            ->pluck('pim.module_key')
            ->all();

        $this->assertContains('restaurant.dine_in', $packageModules);
        $this->assertContains('restaurant.takeaway', $packageModules);
        $this->assertContains('restaurant.order', $packageModules);
        $this->assertNotContains('restaurant.delivery', $packageModules, 'starter tier stays lean');
    }
}
