<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Restaurant Phase 1 — Foundation: `restaurant` becomes a standalone
 * industry with 6 children (menu, menu_category, menu_item, table,
 * table_layout, reservation), config/restaurant.php exposes 2 engines and
 * the module-config matrix renders the new hierarchy.
 */
class RestaurantPhase1Test extends TestCase
{
    use DatabaseTransactions;

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

    public function test_restaurant_parent_exists()
    {
        $this->assertTrue(DB::table('module_registry')->where('key', 'restaurant')->exists());
    }

    public function test_phase1_children_exist()
    {
        $modules = [
            'restaurant.menu', 'restaurant.menu_category', 'restaurant.menu_item',
            'restaurant.table', 'restaurant.table_layout', 'restaurant.reservation',
        ];

        foreach ($modules as $key) {
            $this->assertTrue(
                DB::table('module_registry')->where('key', $key)->exists(),
                "Missing: {$key}"
            );
        }
    }

    public function test_phase1_children_count()
    {
        $count = DB::table('module_registry')
            ->whereIn('key', [
                'restaurant.menu', 'restaurant.menu_category', 'restaurant.menu_item',
                'restaurant.table', 'restaurant.table_layout', 'restaurant.reservation',
            ])
            ->where('status', 'active')
            ->count();
        $this->assertEquals(6, $count);
    }

    public function test_restaurant_has_6_children()
    {
        $count = DB::table('module_registry')
            ->where('parent_key', 'restaurant')
            ->where('status', 'active')
            ->count();
        $this->assertEquals(6, $count);
    }

    public function test_restaurant_config_exists()
    {
        $config = config('restaurant');
        $this->assertIsArray($config);
        $this->assertArrayHasKey('engines', $config);
    }

    public function test_page_renders_restaurant_children()
    {
        $page = $this->actingAs($this->platformAdmin(), 'platform_admin')
            ->get(route('admin.module-config.index', [
                'industry' => 'restaurant',
                'subcategory' => 'fine_dining',
            ]));

        $page->assertOk();
        $page->assertSee('restaurant.menu', false);
        $page->assertSee('restaurant.table', false);
    }
}
