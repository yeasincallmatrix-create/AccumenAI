<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Restaurant Phase 5 — Delivery & Online Ordering: 6 more children under
 * `restaurant` (delivery_zone, delivery_rider, online_order, qr_order,
 * kiosk, tracking), the delivery engine in config/restaurant.php, industry
 * defaults + sub-category defaults, expanded package tiers (16/36/51) and
 * the new delivery permissions.
 *
 * Phases 1-4 stay asserted in their own phase tests.
 */
class RestaurantPhase5Test extends TestCase
{
    use DatabaseTransactions;

    /**
     * @var array<int, string>
     */
    private const PHASE5_KEYS = [
        'restaurant.delivery_zone',
        'restaurant.delivery_rider',
        'restaurant.online_order',
        'restaurant.qr_order',
        'restaurant.kiosk',
        'restaurant.tracking',
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

    public function test_phase5_children_exist()
    {
        foreach (self::PHASE5_KEYS as $key) {
            $this->assertTrue(
                DB::table('module_registry')->where('key', $key)->exists(),
                "Missing: {$key}"
            );
        }
    }

    public function test_phase5_children_count()
    {
        $count = DB::table('module_registry')
            ->whereIn('key', self::PHASE5_KEYS)
            ->where('status', 'active')
            ->count();
        $this->assertEquals(6, $count);
    }

    public function test_restaurant_has_30_children()
    {
        $count = DB::table('module_registry')
            ->where('parent_key', 'restaurant')
            ->where('status', 'active')
            ->count();
        $this->assertEquals(30, $count);

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
                'restaurant.customer', 'restaurant.loyalty', 'restaurant.feedback',
                'restaurant.membership', 'restaurant.birthday_offer', 'restaurant.preference',
                'restaurant.delivery_zone', 'restaurant.delivery_rider', 'restaurant.online_order',
                'restaurant.qr_order', 'restaurant.kiosk', 'restaurant.tracking',
            ],
            $children,
            'phase 1 (1-12), phase 2 (20-25), phase 3 (30-35), phase 4 (40-45), phase 5 (50-55) keep their sort_order'
        );

        foreach (self::PHASE5_KEYS as $key) {
            $row = DB::table('module_registry')->where('key', $key)->first();
            $this->assertSame('restaurant', $row->parent_key, "{$key} parent");
            $this->assertSame('industry', $row->type, "{$key} inherits parent type");
            $this->assertSame(0, (int) $row->is_core, "{$key} inherits parent is_core");
            $this->assertSame('active', $row->status, "{$key} active");
        }
    }

    public function test_restaurant_config_has_delivery_engine()
    {
        $config = config('restaurant');
        $this->assertArrayHasKey('delivery', $config['engines']);
        $this->assertSame(
            self::PHASE5_KEYS,
            array_keys($config['engines']['delivery']['modules'])
        );
        $this->assertFalse((bool) $config['engines']['delivery']['required'], 'delivery engine is opt-in');

        $matrix = config('industry-modules')['restaurant'];
        foreach (['restaurant.delivery_zone', 'restaurant.online_order'] as $key) {
            $this->assertContains($key, $matrix['default'], "{$key} is an industry default");
        }
        foreach (['restaurant.delivery_rider', 'restaurant.qr_order', 'restaurant.kiosk', 'restaurant.tracking'] as $key) {
            $this->assertContains($key, $matrix['optional'], "{$key} is optional");
        }
    }

    public function test_previous_phases_unchanged()
    {
        $previous = [
            'restaurant.menu', 'restaurant.menu_category', 'restaurant.menu_item',
            'restaurant.table', 'restaurant.table_layout', 'restaurant.reservation',
            'restaurant.dine_in', 'restaurant.takeaway', 'restaurant.delivery',
            'restaurant.order', 'restaurant.order_tracking', 'restaurant.pre_order',
            'restaurant.kitchen', 'restaurant.kds', 'restaurant.kot',
            'restaurant.chef', 'restaurant.station', 'restaurant.recipe',
            'restaurant.customer', 'restaurant.loyalty', 'restaurant.feedback',
            'restaurant.membership', 'restaurant.birthday_offer', 'restaurant.preference',
        ];

        foreach ($previous as $key) {
            $this->assertTrue(DB::table('module_registry')->where('key', $key)->exists(), "Missing: {$key}");
        }

        foreach (array_chunk($previous, 6) as $phaseKeys) {
            $this->assertEquals(6, DB::table('module_registry')
                ->where('parent_key', 'restaurant')
                ->whereIn('key', $phaseKeys)
                ->where('status', 'active')
                ->count());
        }
    }

    public function test_permissions_count_gte_69()
    {
        $count = DB::table('permissions')
            ->where('name', 'LIKE', 'restaurant.%')
            ->count();
        $this->assertGreaterThanOrEqual(69, $count);

        $phase5 = DB::table('permissions')
            ->whereIn('name', [
                'restaurant.delivery_zone.view', 'restaurant.delivery_zone.manage',
                'restaurant.delivery_rider.view', 'restaurant.delivery_rider.manage',
                'restaurant.online_order.view', 'restaurant.online_order.manage',
                'restaurant.qr_order.view', 'restaurant.qr_order.manage',
                'restaurant.kiosk.view', 'restaurant.kiosk.manage',
                'restaurant.tracking.view', 'restaurant.tracking.manage',
            ])
            ->count();
        $this->assertEquals(12, $phase5, '12 delivery/online permissions');
    }

    public function test_packages_expanded()
    {
        $expected = [
            'restaurant_starter' => 16,
            'restaurant_growth' => 36,
            'restaurant_enterprise' => 51,
        ];

        $modulesBySlug = [];
        foreach ($expected as $slug => $count) {
            $modules = DB::table('package_industry_modules as pim')
                ->join('subscription_packages as p', 'p.id', '=', 'pim.package_id')
                ->where('p.slug', $slug)
                ->where('pim.industry_key', 'restaurant')
                ->where('pim.enabled', 1)
                ->pluck('pim.module_key')
                ->all();

            $this->assertCount($count, $modules, "{$slug} modules");
            $modulesBySlug[$slug] = $modules;
        }

        foreach (['restaurant_starter', 'restaurant_growth', 'restaurant_enterprise'] as $slug) {
            $this->assertContains('restaurant.delivery_zone', $modulesBySlug[$slug], "{$slug} has delivery_zone");
            $this->assertContains('restaurant.online_order', $modulesBySlug[$slug], "{$slug} has online_order");
        }

        $this->assertNotContains('restaurant.qr_order', $modulesBySlug['restaurant_starter'], 'starter stays lean');
        $this->assertContains('restaurant.qr_order', $modulesBySlug['restaurant_growth']);
        $this->assertNotContains('restaurant.kiosk', $modulesBySlug['restaurant_growth'], 'kiosk is enterprise only');
        $this->assertContains('restaurant.kiosk', $modulesBySlug['restaurant_enterprise']);
        $this->assertContains('restaurant.tracking', $modulesBySlug['restaurant_enterprise']);
    }

    public function test_subcategory_defaults_include_phase5()
    {
        $count = DB::table('subcategory_default_modules as sdm')
            ->join('industry_subcategories as isub', 'isub.id', '=', 'sdm.subcategory_id')
            ->where('isub.industry_key', 'restaurant')
            ->whereIn('sdm.module_key', self::PHASE5_KEYS)
            ->count();
        $this->assertEquals(42, $count, '6 phase 5 modules x 7 sub-categories');

        $cloudKitchen = DB::table('industry_subcategories')
            ->where('industry_key', 'restaurant')
            ->where('subcategory_key', 'cloud_kitchen')
            ->firstOrFail();

        $defaults = DB::table('subcategory_default_modules')
            ->where('subcategory_id', $cloudKitchen->id)
            ->pluck('module_key')
            ->all();

        foreach (self::PHASE5_KEYS as $key) {
            $this->assertContains($key, $defaults, "cloud_kitchen defaults include {$key}");
        }

        $this->assertEquals(
            'mandatory',
            DB::table('subcategory_default_modules')
                ->where('subcategory_id', $cloudKitchen->id)
                ->where('module_key', 'restaurant.delivery_zone')
                ->value('category'),
            'cloud_kitchen is delivery-first'
        );
    }

    public function test_page_renders_phase5_children()
    {
        $html = $this->actingAs($this->platformAdmin(), 'platform_admin')
            ->get(route('admin.module-config.index', [
                'industry' => 'restaurant',
                'subcategory' => 'cloud_kitchen',
            ]))
            ->assertOk()
            ->assertSee('restaurant.delivery_zone', false)
            ->assertSee('restaurant.online_order', false)
            ->getContent();

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_use_internal_errors(false);
        $xpath = new \DOMXPath($dom);

        $this->assertSame(
            30,
            $xpath->query('//tr[@data-child-of="restaurant"]')->length,
            'the matrix renders all 30 restaurant children (6 x 5 phases)'
        );

        foreach (self::PHASE5_KEYS as $key) {
            $this->assertSame(
                1,
                $xpath->query('//tr[@data-child-of="restaurant"]//code[text()="'.$key.'"]')->length,
                "phase 5 row {$key} renders as an indented child of restaurant"
            );
        }
    }
}
