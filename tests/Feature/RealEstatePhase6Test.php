<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 6 (FINAL) — Real Estate Integrations + Custom module support.
 *
 * 7 more children under `real_estate` (7 + 8 + 7 + 6 + 13 + 7 = 48).
 * Phase 1-5 rows must stay untouched. All counts are phase-scoped.
 */
class RealEstatePhase6Test extends TestCase
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

    public function test_phase6_integrations_exist()
    {
        $modules = [
            'real_estate.sales_integration',
            'real_estate.purchase_integration',
            'real_estate.finance_integration',
            'real_estate.accounting_integration',
            'real_estate.hr_integration',
            'real_estate.crm_integration',
            'real_estate.inventory_integration',
        ];

        foreach ($modules as $key) {
            $this->assertTrue(
                DB::table('module_registry')->where('key', $key)->exists(),
                "Missing: {$key}"
            );
        }
    }

    public function test_phase6_children_count()
    {
        $count = DB::table('module_registry')
            ->whereIn('key', [
                'real_estate.sales_integration', 'real_estate.purchase_integration',
                'real_estate.finance_integration', 'real_estate.accounting_integration',
                'real_estate.hr_integration', 'real_estate.crm_integration',
                'real_estate.inventory_integration',
            ])
            ->where('status', 'active')
            ->count();
        $this->assertEquals(7, $count);
    }

    public function test_integrations_engine_and_custom_module_support()
    {
        $engines = config('real-estate.engines');
        $this->assertArrayHasKey('integrations', $engines);
        $this->assertCount(7, $engines['integrations']['modules']);
        $this->assertSame('Integrations', $engines['integrations']['name']);

        $this->assertTrue(config('real-estate.allow_custom_modules'));
        $this->assertSame('real_estate.custom.', config('real-estate.custom_module_prefix'));
    }

    public function test_industry_config_includes_phase6()
    {
        $config = config('industry-modules.real_estate');
        $this->assertContains('real_estate.sales_integration', $config['optional']);
        $this->assertContains('real_estate.finance_integration', $config['optional']);
        $this->assertContains('real_estate.inventory_integration', $config['optional']);
    }

    public function test_all_previous_phases_unchanged()
    {
        // Phase 1
        $this->assertTrue(DB::table('module_registry')->where('key', 'real_estate.properties')->exists());
        // Phase 2
        $this->assertTrue(DB::table('module_registry')->where('key', 'real_estate.leases')->exists());
        // Phase 3
        $this->assertTrue(DB::table('module_registry')->where('key', 'real_estate.leads')->exists());
        // Phase 4
        $this->assertTrue(DB::table('module_registry')->where('key', 'real_estate.maintenance_requests')->exists());
        // Phase 5
        $this->assertTrue(DB::table('module_registry')->where('key', 'real_estate.rent_income')->exists());
    }

    public function test_module_config_page_renders_phase6_children()
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

        // Phase-scoped: every Phase 6 integration must render indented under
        // the parent (no exact totals asserted here).
        foreach ([
            'real_estate.sales_integration', 'real_estate.purchase_integration',
            'real_estate.finance_integration', 'real_estate.accounting_integration',
            'real_estate.hr_integration', 'real_estate.crm_integration',
            'real_estate.inventory_integration',
        ] as $childKey) {
            $this->assertSame(
                1,
                $xpath->query('//tr[@data-child-of="real_estate"]//code[text()="'.$childKey.'"]')->length,
                "Phase 6 child {$childKey} must render as an indented child row"
            );
        }

        $this->assertSame(
            1,
            $xpath->query('//*[@data-module-toggle="real_estate"]')->length,
            'real_estate must render exactly one toggle'
        );
    }
}
