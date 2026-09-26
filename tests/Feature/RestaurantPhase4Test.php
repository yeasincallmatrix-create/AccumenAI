<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Restaurant Phase 4 — Customer & Loyalty: 6 more children under
 * `restaurant` (customer, loyalty, feedback, membership, birthday_offer,
 * preference), the customer engine in config/restaurant.php, industry
 * defaults + sub-category defaults, expanded package tiers (14/31/45) and
 * the new customer permissions.
 *
 * Phases 1-3 stay asserted in their own phase tests.
 */
class RestaurantPhase4Test extends TestCase
{
    use DatabaseTransactions;

    /**
     * @var array<int, string>
     */
    private const PHASE4_KEYS = [
        'restaurant.customer',
        'restaurant.loyalty',
        'restaurant.feedback',
        'restaurant.membership',
        'restaurant.birthday_offer',
        'restaurant.preference',
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

    public function test_phase4_children_exist()
    {
        foreach (self::PHASE4_KEYS as $key) {
            $this->assertTrue(
                DB::table('module_registry')->where('key', $key)->exists(),
                "Missing: {$key}"
            );
        }
    }

    public function test_phase4_children_count()
    {
        $count = DB::table('module_registry')
            ->whereIn('key', self::PHASE4_KEYS)
            ->where('status', 'active')
            ->count();
        $this->assertEquals(6, $count);
    }

    public function test_restaurant_has_24_children()
    {
        $count = DB::table('module_registry')
            ->where('parent_key', 'restaurant')
            ->where('status', 'active')
            ->count();
        $this->assertEquals(24, $count);

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
            ],
            $children,
            'phase 1 (1-12), phase 2 (20-25), phase 3 (30-35), phase 4 (40-45) keep their sort_order'
        );

        foreach (self::PHASE4_KEYS as $key) {
            $row = DB::table('module_registry')->where('key', $key)->first();
            $this->assertSame('restaurant', $row->parent_key, "{$key} parent");
            $this->assertSame('industry', $row->type, "{$key} inherits parent type");
            $this->assertSame(0, (int) $row->is_core, "{$key} inherits parent is_core");
            $this->assertSame('active', $row->status, "{$key} active");
        }
    }

    public function test_restaurant_config_has_customer_engine()
    {
        $config = config('restaurant');
        $this->assertArrayHasKey('customer', $config['engines']);
        $this->assertSame(
            self::PHASE4_KEYS,
            array_keys($config['engines']['customer']['modules'])
        );
        $this->assertFalse((bool) $config['engines']['customer']['required'], 'customer engine is opt-in');

        $matrix = config('industry-modules')['restaurant'];
        foreach (['restaurant.customer', 'restaurant.loyalty'] as $key) {
            $this->assertContains($key, $matrix['default'], "{$key} is an industry default");
        }
        foreach (['restaurant.feedback', 'restaurant.membership', 'restaurant.birthday_offer', 'restaurant.preference'] as $key) {
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

    public function test_permissions_count_gte_57()
    {
        $count = DB::table('permissions')
            ->where('name', 'LIKE', 'restaurant.%')
            ->count();
        $this->assertGreaterThanOrEqual(57, $count);

        $phase4 = DB::table('permissions')
            ->whereIn('name', [
                'restaurant.customer.view', 'restaurant.customer.manage',
                'restaurant.loyalty.view', 'restaurant.loyalty.manage',
                'restaurant.feedback.view', 'restaurant.feedback.manage',
                'restaurant.membership.view', 'restaurant.membership.manage',
                'restaurant.birthday_offer.view', 'restaurant.birthday_offer.manage',
                'restaurant.preference.view', 'restaurant.preference.manage',
            ])
            ->count();
        $this->assertEquals(12, $phase4, '12 customer/loyalty permissions');
    }

    public function test_packages_expanded()
    {
        $expected = [
            'restaurant_starter' => 14,
            'restaurant_growth' => 31,
            'restaurant_enterprise' => 45,
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
            $this->assertContains('restaurant.customer', $modulesBySlug[$slug], "{$slug} has customer");
            $this->assertContains('restaurant.loyalty', $modulesBySlug[$slug], "{$slug} has loyalty");
        }

        $this->assertNotContains('restaurant.feedback', $modulesBySlug['restaurant_starter'], 'starter stays lean');
        $this->assertContains('restaurant.feedback', $modulesBySlug['restaurant_growth']);
        $this->assertNotContains('restaurant.birthday_offer', $modulesBySlug['restaurant_growth'], 'birthday_offer is enterprise only');
        $this->assertContains('restaurant.birthday_offer', $modulesBySlug['restaurant_enterprise']);
    }

    public function test_subcategory_defaults_include_phase4()
    {
        $count = DB::table('subcategory_default_modules as sdm')
            ->join('industry_subcategories as isub', 'isub.id', '=', 'sdm.subcategory_id')
            ->where('isub.industry_key', 'restaurant')
            ->whereIn('sdm.module_key', self::PHASE4_KEYS)
            ->count();
        $this->assertEquals(42, $count, '6 phase 4 modules x 7 sub-categories');

        $fineDining = DB::table('industry_subcategories')
            ->where('industry_key', 'restaurant')
            ->where('subcategory_key', 'fine_dining')
            ->firstOrFail();

        $defaults = DB::table('subcategory_default_modules')
            ->where('subcategory_id', $fineDining->id)
            ->pluck('module_key')
            ->all();

        foreach (self::PHASE4_KEYS as $key) {
            $this->assertContains($key, $defaults, "fine_dining defaults include {$key}");
        }
    }

    public function test_page_renders_phase4_children()
    {
        $html = $this->actingAs($this->platformAdmin(), 'platform_admin')
            ->get(route('admin.module-config.index', [
                'industry' => 'restaurant',
                'subcategory' => 'fine_dining',
            ]))
            ->assertOk()
            ->assertSee('restaurant.customer', false)
            ->assertSee('restaurant.loyalty', false)
            ->getContent();

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_use_internal_errors(false);
        $xpath = new \DOMXPath($dom);

        $this->assertSame(
            24,
            $xpath->query('//tr[@data-child-of="restaurant"]')->length,
            'the matrix renders all 24 restaurant children (6 + 6 + 6 + 6)'
        );

        foreach (self::PHASE4_KEYS as $key) {
            $this->assertSame(
                1,
                $xpath->query('//tr[@data-child-of="restaurant"]//code[text()="'.$key.'"]')->length,
                "phase 4 row {$key} renders as an indented child of restaurant"
            );
        }
    }
}
