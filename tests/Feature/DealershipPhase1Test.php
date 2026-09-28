<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DealershipPhase1Test extends TestCase
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

    public function test_dealership_parent_exists()
    {
        $parent = DB::table('module_registry')->where('key', 'dealership')->first();
        $this->assertNotNull($parent);
        $this->assertEquals('core', $parent->type);
        $this->assertNull($parent->parent_key);
    }

    public function test_5_children_exist()
    {
        $modules = [
            'dealership.brands',
            'dealership.products',
            'dealership.sales_force',
            'dealership.beats',
            'dealership.customers',
        ];

        foreach ($modules as $key) {
            $this->assertTrue(
                DB::table('module_registry')->where('key', $key)->exists(),
                "Missing: {$key}"
            );
        }
    }

    public function test_children_parent_is_dealership()
    {
        $count = DB::table('module_registry')
            ->where('parent_key', 'dealership')
            ->where('status', 'active')
            ->count();
        $this->assertEquals(16, $count);
    }

    public function test_children_inherit_type_from_parent()
    {
        $children = DB::table('module_registry')
            ->where('parent_key', 'dealership')
            ->get();

        foreach ($children as $child) {
            $this->assertEquals('core', $child->type);
        }
    }

    public function test_dealership_config_exists()
    {
        $config = config('dealership');
        $this->assertIsArray($config);
        $this->assertArrayHasKey('engines', $config);
        $this->assertArrayHasKey('catalog', $config['engines']);
        $this->assertArrayHasKey('field_force', $config['engines']);
    }

    public function test_registry_total_290()
    {
        $count = DB::table('module_registry')->where('status', 'active')->count();
        $this->assertEquals(290, $count);
    }

    public function test_page_renders_dealership_group()
    {
        $page = $this->actingAs($this->platformAdmin(), 'platform_admin')
            ->get(route('admin.module-config.index', [
                'industry' => 'retail',
                'subcategory' => 'grocery',
            ]));

        $page->assertOk();
        $page->assertSee('dealership.brands', false);
        $page->assertSee('dealership.customers', false);
    }
}
