<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 5 — Real Estate Accounting & Reports.
 *
 * Phase 5A: 7 accounting children (sort 40-46).
 * Phase 5B: 6 reports children (sort 50-55).
 * 13 more children under `real_estate` (7 + 8 + 7 + 6 + 13 = 41).
 * Phase 1-4 rows must stay untouched.
 */
class RealEstatePhase5Test extends TestCase
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

    public function test_phase5a_accounting_children_exist()
    {
        $modules = [
            'real_estate.rent_income', 'real_estate.property_expenses',
            'real_estate.service_charges', 'real_estate.tax_reports',
            'real_estate.financial_reports', 'real_estate.owner_statements',
            'real_estate.tenant_statements',
        ];

        foreach ($modules as $key) {
            $this->assertTrue(
                DB::table('module_registry')->where('key', $key)->exists(),
                "Missing: {$key}"
            );
        }
    }

    public function test_phase5b_reports_children_exist()
    {
        $modules = [
            'real_estate.occupancy_report', 'real_estate.rent_roll',
            'real_estate.aging_report', 'real_estate.profit_loss',
            'real_estate.cash_flow', 'real_estate.portfolio_report',
        ];

        foreach ($modules as $key) {
            $this->assertTrue(
                DB::table('module_registry')->where('key', $key)->exists(),
                "Missing: {$key}"
            );
        }
    }

    public function test_phase5_children_count()
    {
        $count = DB::table('module_registry')
            ->whereIn('key', [
                'real_estate.rent_income', 'real_estate.property_expenses',
                'real_estate.service_charges', 'real_estate.tax_reports',
                'real_estate.financial_reports', 'real_estate.owner_statements',
                'real_estate.tenant_statements', 'real_estate.occupancy_report',
                'real_estate.rent_roll', 'real_estate.aging_report',
                'real_estate.profit_loss', 'real_estate.cash_flow',
                'real_estate.portfolio_report',
            ])
            ->where('status', 'active')
            ->count();
        $this->assertEquals(13, $count);
    }

    public function test_industry_config_includes_phase5()
    {
        $config = config('industry-modules.real_estate');
        $this->assertContains('real_estate.rent_income', $config['default']);
        $this->assertContains('real_estate.property_expenses', $config['default']);
        $this->assertContains('real_estate.occupancy_report', $config['default']);
        $this->assertContains('real_estate.rent_roll', $config['default']);
        $this->assertContains('real_estate.service_charges', $config['optional']);
        $this->assertContains('real_estate.portfolio_report', $config['optional']);
    }

    public function test_previous_phases_unchanged()
    {
        // Phase 1
        $this->assertTrue(DB::table('module_registry')->where('key', 'real_estate.properties')->exists());
        // Phase 2
        $this->assertTrue(DB::table('module_registry')->where('key', 'real_estate.leases')->exists());
        // Phase 3
        $this->assertTrue(DB::table('module_registry')->where('key', 'real_estate.leads')->exists());
        // Phase 4
        $this->assertTrue(DB::table('module_registry')->where('key', 'real_estate.maintenance_requests')->exists());
    }

    public function test_module_config_page_renders_phase5_children()
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

        // Phase-scoped: every Phase 5 child must render indented under the
        // parent (later phases add rows, so no exact total is asserted here).
        foreach ([
            'real_estate.rent_income', 'real_estate.property_expenses',
            'real_estate.service_charges', 'real_estate.tax_reports',
            'real_estate.financial_reports', 'real_estate.owner_statements',
            'real_estate.tenant_statements', 'real_estate.occupancy_report',
            'real_estate.rent_roll', 'real_estate.aging_report',
            'real_estate.profit_loss', 'real_estate.cash_flow',
            'real_estate.portfolio_report',
        ] as $childKey) {
            $this->assertSame(
                1,
                $xpath->query('//tr[@data-child-of="real_estate"]//code[text()="'.$childKey.'"]')->length,
                "Phase 5 child {$childKey} must render as an indented child row"
            );
        }

        $this->assertSame(
            1,
            $xpath->query('//*[@data-module-toggle="real_estate"]')->length,
            'real_estate must render exactly one toggle'
        );
    }
}
