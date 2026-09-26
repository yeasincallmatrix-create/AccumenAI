<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 2 — Real Estate Leasing & Rental.
 *
 * 8 more children under `real_estate` (7 Phase 1 + 8 Phase 2 = 15).
 * Phase 1 rows must stay untouched.
 */
class RealEstatePhase2Test extends TestCase
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

    public function test_phase2_children_exist()
    {
        $modules = [
            'real_estate.leases', 'real_estate.tenants', 'real_estate.rent_invoices',
            'real_estate.rent_collection', 'real_estate.security_deposits',
            'real_estate.lease_renewals', 'real_estate.utility_billing',
            'real_estate.cam_charges',
        ];

        foreach ($modules as $key) {
            $this->assertTrue(
                DB::table('module_registry')->where('key', $key)->exists(),
                "Missing: {$key}"
            );
        }
    }

    public function test_real_estate_has_15_children()
    {
        $count = DB::table('module_registry')
            ->where('parent_key', 'real_estate')
            ->where('status', 'active')
            ->count();
        $this->assertEquals(15, $count);
    }

    public function test_industry_config_includes_phase2()
    {
        $config = config('industry-modules.real_estate');
        $this->assertContains('real_estate.leases', $config['default']);
        $this->assertContains('real_estate.tenants', $config['default']);
    }

    public function test_phase1_unchanged()
    {
        // Verify Phase 1 children still there
        $this->assertTrue(DB::table('module_registry')->where('key', 'real_estate.properties')->exists());
        $this->assertTrue(DB::table('module_registry')->where('key', 'real_estate.units')->exists());
    }

    public function test_module_config_page_renders_15_children()
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

        $this->assertSame(
            15,
            $xpath->query('//tr[@data-child-of="real_estate"]')->length,
            'real_estate must render exactly 15 indented child rows (7 Phase 1 + 8 Phase 2)'
        );
        $this->assertSame(
            1,
            $xpath->query('//*[@data-module-toggle="real_estate"]')->length,
            'real_estate must render exactly one toggle'
        );
    }
}
