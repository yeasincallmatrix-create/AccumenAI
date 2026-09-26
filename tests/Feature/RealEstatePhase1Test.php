<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 1 — Real Estate Foundation (Property Management).
 *
 * Parent `real_estate` elaborated into 7 children; industry + module group
 * config wired so /admin/module-config renders the parent with its children.
 */
class RealEstatePhase1Test extends TestCase
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

    public function test_real_estate_parent_exists()
    {
        $this->assertTrue(DB::table('module_registry')->where('key', 'real_estate')->exists());
    }

    public function test_real_estate_has_7_children()
    {
        // Phase-scoped: later phases add children, so assert the 7 Phase 1
        // keys are the ones registered here rather than pinning a total.
        $phase1 = [
            'real_estate.properties', 'real_estate.buildings', 'real_estate.units',
            'real_estate.owners', 'real_estate.property_types', 'real_estate.amenities',
            'real_estate.documents',
        ];

        $count = DB::table('module_registry')
            ->where('parent_key', 'real_estate')
            ->where('status', 'active')
            ->whereIn('key', $phase1)
            ->count();
        $this->assertEquals(7, $count);
    }

    public function test_property_management_modules_exist()
    {
        $modules = [
            'real_estate.properties',
            'real_estate.buildings',
            'real_estate.units',
            'real_estate.owners',
            'real_estate.property_types',
            'real_estate.amenities',
            'real_estate.documents',
        ];

        foreach ($modules as $key) {
            $this->assertTrue(
                DB::table('module_registry')->where('key', $key)->exists(),
                "Missing: {$key}"
            );
        }
    }

    public function test_industry_config_has_real_estate()
    {
        $config = config('industry-modules.real_estate');
        $this->assertIsArray($config);
        $this->assertNotEmpty($config['default']);
    }

    public function test_registry_total_increased()
    {
        $count = DB::table('module_registry')->where('status', 'active')->count();
        $this->assertGreaterThanOrEqual(162, $count);
    }

    public function test_module_config_page_renders_parent_and_children()
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

        // Phase-scoped: every Phase 1 child must render indented under the
        // parent (later phases add rows, so no exact total is asserted here).
        foreach ([
            'real_estate.properties', 'real_estate.buildings', 'real_estate.units',
            'real_estate.owners', 'real_estate.property_types', 'real_estate.amenities',
            'real_estate.documents',
        ] as $childKey) {
            $this->assertSame(
                1,
                $xpath->query('//tr[@data-child-of="real_estate"]//code[text()="'.$childKey.'"]')->length,
                "Phase 1 child {$childKey} must render as an indented child row"
            );
        }

        $this->assertSame(
            1,
            $xpath->query('//*[@data-module-toggle="real_estate"]')->length,
            'real_estate must render exactly one toggle'
        );
    }
}
