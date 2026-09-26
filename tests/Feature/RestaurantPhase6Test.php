<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Restaurant Phase 6 (final) — Integrations: 5 more children under
 * `restaurant` (pos_integration, sales_integration, purchase_integration,
 * finance_integration, accounting_integration), the integrations engine in
 * config/restaurant.php, custom-module support flags, industry defaults +
 * sub-category defaults, expanded package tiers (18/41/56) and the new
 * integration permissions.
 *
 * Phases 1-5 stay asserted in their own phase tests.
 */
class RestaurantPhase6Test extends TestCase
{
    use DatabaseTransactions;

    /**
     * @var array<int, string>
     */
    private const PHASE6_KEYS = [
        'restaurant.pos_integration',
        'restaurant.sales_integration',
        'restaurant.purchase_integration',
        'restaurant.finance_integration',
        'restaurant.accounting_integration',
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

    public function test_phase6_children_exist()
    {
        foreach (self::PHASE6_KEYS as $key) {
            $this->assertTrue(
                DB::table('module_registry')->where('key', $key)->exists(),
                "Missing: {$key}"
            );
        }
    }

    public function test_phase6_children_count()
    {
        $count = DB::table('module_registry')
            ->whereIn('key', self::PHASE6_KEYS)
            ->where('status', 'active')
            ->count();
        $this->assertEquals(5, $count);
    }

    public function test_restaurant_has_35_children()
    {
        $count = DB::table('module_registry')
            ->where('parent_key', 'restaurant')
            ->where('status', 'active')
            ->count();
        $this->assertEquals(35, $count);

        foreach (self::PHASE6_KEYS as $key) {
            $row = DB::table('module_registry')->where('key', $key)->first();
            $this->assertSame('restaurant', $row->parent_key, "{$key} parent");
            $this->assertSame('industry', $row->type, "{$key} inherits parent type");
            $this->assertSame(0, (int) $row->is_core, "{$key} inherits parent is_core");
            $this->assertSame('active', $row->status, "{$key} active");
        }

        $orders = DB::table('module_registry')
            ->where('parent_key', 'restaurant')
            ->whereIn('key', self::PHASE6_KEYS)
            ->orderBy('sort_order')
            ->pluck('key')
            ->all();
        $this->assertSame(self::PHASE6_KEYS, $orders, 'phase 6 keeps sort_order 60-64');
    }

    public function test_restaurant_config_has_integrations_engine()
    {
        $config = config('restaurant');
        $this->assertArrayHasKey('integrations', $config['engines']);
        $this->assertSame(
            self::PHASE6_KEYS,
            array_keys($config['engines']['integrations']['modules'])
        );
        $this->assertFalse((bool) $config['engines']['integrations']['required'], 'integrations engine is opt-in');
        $this->assertCount(7, $config['engines'], 'menu, table, orders, kitchen, customer, delivery, integrations');
        $this->assertSame(
            35,
            collect($config['engines'])->sum(fn ($engine) => count($engine['modules'])),
            '35 engine modules across 7 engines'
        );
    }

    public function test_custom_module_support_enabled()
    {
        $config = config('restaurant');
        $this->assertTrue($config['allow_custom_modules']);
        $this->assertEquals('restaurant.custom.', $config['custom_module_prefix']);
        foreach (['menu_type', 'table_section', 'report', 'workflow', 'other'] as $type) {
            $this->assertContains($type, $config['custom_module_types'], "custom type {$type}");
        }

        $matrix = config('industry-modules')['restaurant'];
        foreach (['restaurant.pos_integration', 'restaurant.sales_integration'] as $key) {
            $this->assertContains($key, $matrix['default'], "{$key} is an industry default");
        }
        foreach (['restaurant.purchase_integration', 'restaurant.finance_integration', 'restaurant.accounting_integration'] as $key) {
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
            'restaurant.delivery_zone', 'restaurant.delivery_rider', 'restaurant.online_order',
            'restaurant.qr_order', 'restaurant.kiosk', 'restaurant.tracking',
        ];

        foreach (array_chunk($previous, 6) as $phaseKeys) {
            $this->assertEquals(6, DB::table('module_registry')
                ->where('parent_key', 'restaurant')
                ->whereIn('key', $phaseKeys)
                ->where('status', 'active')
                ->count());
        }
    }

    public function test_permissions_count_gte_79()
    {
        $count = DB::table('permissions')
            ->where('name', 'LIKE', 'restaurant.%')
            ->count();
        $this->assertGreaterThanOrEqual(79, $count);

        $phase6 = DB::table('permissions')
            ->whereIn('name', [
                'restaurant.pos_integration.view', 'restaurant.pos_integration.manage',
                'restaurant.sales_integration.view', 'restaurant.sales_integration.manage',
                'restaurant.purchase_integration.view', 'restaurant.purchase_integration.manage',
                'restaurant.finance_integration.view', 'restaurant.finance_integration.manage',
                'restaurant.accounting_integration.view', 'restaurant.accounting_integration.manage',
            ])
            ->count();
        $this->assertEquals(10, $phase6, '10 integration permissions');
    }

    public function test_packages_expanded()
    {
        $expected = [
            'restaurant_starter' => 18,
            'restaurant_growth' => 41,
            'restaurant_enterprise' => 56,
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
            $this->assertContains('restaurant.pos_integration', $modulesBySlug[$slug], "{$slug} has pos_integration");
            $this->assertContains('restaurant.sales_integration', $modulesBySlug[$slug], "{$slug} has sales_integration");
        }

        $this->assertNotContains('restaurant.purchase_integration', $modulesBySlug['restaurant_starter'], 'starter stays lean');
        $this->assertContains('restaurant.purchase_integration', $modulesBySlug['restaurant_growth']);
        $this->assertContains('restaurant.purchase_integration', $modulesBySlug['restaurant_enterprise']);
        $this->assertContains('restaurant.accounting_integration', $modulesBySlug['restaurant_enterprise']);
    }

    public function test_subcategory_defaults_include_phase6()
    {
        $count = DB::table('subcategory_default_modules as sdm')
            ->join('industry_subcategories as isub', 'isub.id', '=', 'sdm.subcategory_id')
            ->where('isub.industry_key', 'restaurant')
            ->whereIn('sdm.module_key', self::PHASE6_KEYS)
            ->count();
        $this->assertEquals(35, $count, '5 phase 6 modules x 7 sub-categories');

        $fineDining = DB::table('industry_subcategories')
            ->where('industry_key', 'restaurant')
            ->where('subcategory_key', 'fine_dining')
            ->firstOrFail();

        $this->assertEquals(
            'mandatory',
            DB::table('subcategory_default_modules')
                ->where('subcategory_id', $fineDining->id)
                ->where('module_key', 'restaurant.pos_integration')
                ->value('category'),
            'fine_dining billing is mandatory'
        );
        $this->assertEquals(
            'optional',
            DB::table('subcategory_default_modules')
                ->where('subcategory_id', $fineDining->id)
                ->where('module_key', 'restaurant.accounting_integration')
                ->value('category'),
            'fine_dining accounting is optional'
        );

        $cloudKitchen = DB::table('industry_subcategories')
            ->where('industry_key', 'restaurant')
            ->where('subcategory_key', 'cloud_kitchen')
            ->firstOrFail();

        $this->assertEquals(
            'default',
            DB::table('subcategory_default_modules')
                ->where('subcategory_id', $cloudKitchen->id)
                ->where('module_key', 'restaurant.accounting_integration')
                ->value('category'),
            'cloud_kitchen accounting is default'
        );
    }

    public function test_page_renders_phase6_children()
    {
        $html = $this->actingAs($this->platformAdmin(), 'platform_admin')
            ->get(route('admin.module-config.index', [
                'industry' => 'restaurant',
                'subcategory' => 'fine_dining',
            ]))
            ->assertOk()
            ->assertSee('restaurant.pos_integration', false)
            ->assertSee('restaurant.sales_integration', false)
            ->getContent();

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_use_internal_errors(false);
        $xpath = new \DOMXPath($dom);

        $this->assertSame(
            35,
            $xpath->query('//tr[@data-child-of="restaurant"]')->length,
            'the matrix renders all 35 restaurant children (6 x 5 phases + 5)'
        );

        foreach (self::PHASE6_KEYS as $key) {
            $this->assertSame(
                1,
                $xpath->query('//tr[@data-child-of="restaurant"]//code[text()="'.$key.'"]')->length,
                "phase 6 row {$key} renders as an indented child of restaurant"
            );
        }
    }
}
