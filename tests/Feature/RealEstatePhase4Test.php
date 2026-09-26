<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 4 — Real Estate Maintenance & Operations.
 *
 * 6 more children under `real_estate` (7 + 8 + 7 + 6 = 28).
 * Phase 1, 2 and 3 rows must stay untouched.
 */
class RealEstatePhase4Test extends TestCase
{
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
        $modules = [
            'real_estate.maintenance_requests', 'real_estate.work_orders',
            'real_estate.vendors', 'real_estate.inspections',
            'real_estate.assets', 'real_estate.preventive_maintenance',
        ];

        foreach ($modules as $key) {
            $this->assertTrue(
                DB::table('module_registry')->where('key', $key)->exists(),
                "Missing: {$key}"
            );
        }
    }

    public function test_phase4_children_count()
    {
        $count = DB::table('module_registry')
            ->whereIn('key', [
                'real_estate.maintenance_requests', 'real_estate.work_orders',
                'real_estate.vendors', 'real_estate.inspections',
                'real_estate.assets', 'real_estate.preventive_maintenance',
            ])
            ->where('status', 'active')
            ->count();
        $this->assertEquals(6, $count);
    }

    public function test_industry_config_includes_phase4()
    {
        $config = config('industry-modules.real_estate');
        $this->assertContains('real_estate.maintenance_requests', $config['default']);
        $this->assertContains('real_estate.work_orders', $config['default']);
        $this->assertContains('real_estate.inspections', $config['optional']);
        $this->assertContains('real_estate.vendors', $config['optional']);
    }

    public function test_previous_phases_unchanged()
    {
        // Phase 1
        $this->assertTrue(DB::table('module_registry')->where('key', 'real_estate.properties')->exists());
        // Phase 2
        $this->assertTrue(DB::table('module_registry')->where('key', 'real_estate.leases')->exists());
        // Phase 3
        $this->assertTrue(DB::table('module_registry')->where('key', 'real_estate.leads')->exists());
    }

    public function test_module_config_page_renders_phase4_children()
    {
        $html = $this->actingAs($this->platformAdmin(), 'platform_admin')
            ->get(route('admin.module-config.index', [
                'industry' => 'real_estate',
                'subcategory' => 'rental',
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-module-toggle="real_estate"', $html,
            'real_estate must render as a collapsible parent row');

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        // Phase-scoped: every Phase 4 child must render indented under the
        // parent (later phases add rows, so no exact total is asserted here).
        foreach ([
            'real_estate.maintenance_requests', 'real_estate.work_orders',
            'real_estate.vendors', 'real_estate.inspections',
            'real_estate.assets', 'real_estate.preventive_maintenance',
        ] as $childKey) {
            $this->assertSame(
                1,
                $xpath->query('//tr[@data-child-of="real_estate"]//code[text()="'.$childKey.'"]')->length,
                "Phase 4 child {$childKey} must render as an indented child row"
            );
        }

        $this->assertSame(
            1,
            $xpath->query('//*[@data-module-toggle="real_estate"]')->length,
            'real_estate must render exactly one toggle'
        );
    }
}
