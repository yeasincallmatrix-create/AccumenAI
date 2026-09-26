<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 3 — Real Estate Sales & CRM.
 *
 * 7 more children under `real_estate` (7 + 8 + 7 = 22).
 * Phase 1 and Phase 2 rows must stay untouched.
 */
class RealEstatePhase3Test extends TestCase
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

    public function test_phase3_children_exist()
    {
        $modules = [
            'real_estate.leads', 'real_estate.site_visits', 'real_estate.bookings',
            'real_estate.sales_agreements', 'real_estate.installments',
            'real_estate.handover', 'real_estate.after_sales',
        ];

        foreach ($modules as $key) {
            $this->assertTrue(
                DB::table('module_registry')->where('key', $key)->exists(),
                "Missing: {$key}"
            );
        }
    }

    public function test_real_estate_has_22_children()
    {
        $count = DB::table('module_registry')
            ->where('parent_key', 'real_estate')
            ->where('status', 'active')
            ->count();
        $this->assertEquals(22, $count);
    }

    public function test_industry_config_includes_phase3()
    {
        $config = config('industry-modules.real_estate');
        $this->assertContains('real_estate.leads', $config['default']);
        $this->assertContains('real_estate.bookings', $config['default']);
    }

    public function test_phase1_and_phase2_unchanged()
    {
        $this->assertTrue(DB::table('module_registry')->where('key', 'real_estate.properties')->exists());
        $this->assertTrue(DB::table('module_registry')->where('key', 'real_estate.leases')->exists());
    }

    public function test_module_config_page_renders_phase3_children()
    {
        $html = $this->actingAs($this->platformAdmin(), 'platform_admin')
            ->get(route('admin.module-config.index', [
                'industry' => 'real_estate',
                'subcategory' => 'property',
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

        // Phase-scoped: every Phase 3 child must render indented under the
        // parent (later phases add rows, so no exact total is asserted here).
        foreach ([
            'real_estate.leads', 'real_estate.site_visits', 'real_estate.bookings',
            'real_estate.sales_agreements', 'real_estate.installments',
            'real_estate.handover', 'real_estate.after_sales',
        ] as $childKey) {
            $this->assertSame(
                1,
                $xpath->query('//tr[@data-child-of="real_estate"]//code[text()="'.$childKey.'"]')->length,
                "Phase 3 child {$childKey} must render as an indented child row"
            );
        }

        $this->assertSame(
            1,
            $xpath->query('//*[@data-module-toggle="real_estate"]')->length,
            'real_estate must render exactly one toggle'
        );
    }
}
