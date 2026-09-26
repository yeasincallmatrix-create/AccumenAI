<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Universal Manufacturing — the `manufacturing` parent elaborated into
 * 22 children across 5 core engines (7 core modules incl. parent,
 * 16 optional modules).
 */
class ManufacturingModuleTest extends TestCase
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

    public function test_manufacturing_parent_exists(): void
    {
        $row = DB::table('module_registry')->where('key', 'manufacturing')->first();

        $this->assertNotNull($row);
        $this->assertNull($row->parent_key);
        $this->assertSame('active', $row->status);
    }

    public function test_manufacturing_has_22_children(): void
    {
        $count = DB::table('module_registry')
            ->where('parent_key', 'manufacturing')
            ->where('status', 'active')
            ->count();

        $this->assertSame(22, $count);
    }

    public function test_core_manufacturing_modules_exist(): void
    {
        $core = [
            'manufacturing.bom', 'manufacturing.routing', 'manufacturing.work_centers',
            'manufacturing.production_orders', 'manufacturing.costing', 'manufacturing.reports',
        ];

        foreach ($core as $key) {
            $row = DB::table('module_registry')->where('key', $key)->first();

            $this->assertNotNull($row, "Core module missing: {$key}");
            $this->assertSame('manufacturing', $row->parent_key, "{$key} must nest under manufacturing");
            $this->assertSame('active', $row->status);
        }
    }

    public function test_quality_engine_modules_exist(): void
    {
        $quality = [
            'manufacturing.quality_control', 'manufacturing.quality_lab',
            'manufacturing.sample_management', 'manufacturing.regulatory_compliance',
        ];

        foreach ($quality as $key) {
            $this->assertTrue(
                DB::table('module_registry')->where('key', $key)->exists(),
                "Quality module missing: {$key}"
            );
        }
    }

    public function test_traceability_engine_modules_exist(): void
    {
        $trace = [
            'manufacturing.batch_tracking', 'manufacturing.expiry_tracking',
            'manufacturing.serial_number',
        ];

        foreach ($trace as $key) {
            $this->assertTrue(
                DB::table('module_registry')->where('key', $key)->exists(),
                "Traceability module missing: {$key}"
            );
        }
    }

    public function test_process_modules_exist(): void
    {
        $process = [
            'manufacturing.recipe', 'manufacturing.cutting', 'manufacturing.welding',
            'manufacturing.finishing', 'manufacturing.printing', 'manufacturing.packaging',
        ];

        foreach ($process as $key) {
            $this->assertTrue(
                DB::table('module_registry')->where('key', $key)->exists(),
                "Process module missing: {$key}"
            );
        }
    }

    public function test_production_extension_and_post_sales_modules_exist(): void
    {
        $extra = [
            'manufacturing.assembly_line', 'manufacturing.mold_management', 'manufacturing.warranty',
        ];

        foreach ($extra as $key) {
            $this->assertTrue(
                DB::table('module_registry')->where('key', $key)->exists(),
                "Module missing: {$key}"
            );
        }
    }

    public function test_children_inherit_parent_type_and_icons(): void
    {
        $parentType = DB::table('module_registry')->where('key', 'manufacturing')->value('type');

        $mismatched = DB::table('module_registry')
            ->where('parent_key', 'manufacturing')
            ->where('type', '!=', $parentType)
            ->pluck('key');

        $this->assertSame([], $mismatched->all(), 'Children must inherit the parent module type');

        $noIcon = DB::table('module_registry')
            ->where('parent_key', 'manufacturing')
            ->where(function ($q) {
                $q->whereNull('icon')->orWhere('icon', '');
            })
            ->pluck('key');

        $this->assertSame([], $noIcon->all(), 'Every manufacturing child must carry an icon');
    }

    public function test_manufacturing_renders_in_module_config(): void
    {
        $html = $this->actingAs($this->platformAdmin(), 'platform_admin')
            ->get(route('admin.module-config.index', [
                'industry' => 'manufacturing',
                'subcategory' => 'general',
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-module-toggle="manufacturing"', $html,
            'Manufacturing must render as a collapsible parent');
        $this->assertStringContainsString('manufacturing.bom', $html);
        $this->assertStringContainsString('manufacturing.routing', $html);
        $this->assertStringContainsString('manufacturing.quality_control', $html);
        $this->assertStringContainsString('manufacturing.warranty', $html);

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        $this->assertSame(
            22,
            $xpath->query('//tr[@data-child-of="manufacturing"]')->length,
            'Manufacturing must render exactly 22 indented child rows'
        );
        $this->assertSame(
            1,
            $xpath->query('//*[@data-module-toggle="manufacturing"]')->length,
            'Manufacturing must render exactly one toggle'
        );
    }

    public function test_manufacturing_count_in_industry_config(): void
    {
        $config = config('industry-modules.manufacturing');

        $this->assertIsArray($config);
        $this->assertNotEmpty($config['default'] ?? []);
        $this->assertContains('manufacturing', $config['default']);
        $this->assertCount(38, $config['optional'] ?? [],
            '16 manufacturing optionals + 11 POS phase 1/2 keys + 5 POS phase 3 keys + 6 POS phase 4 keys');

        foreach ($config['default'] as $key) {
            $this->assertTrue(
                DB::table('module_registry')->where('key', $key)->exists(),
                "Industry default module not registered: {$key}"
            );
        }
        foreach ($config['optional'] as $key) {
            $this->assertTrue(
                DB::table('module_registry')->where('key', $key)->exists(),
                "Industry optional module not registered: {$key}"
            );
        }
    }

    public function test_manufacturing_engines_config_covers_all_children(): void
    {
        $engines = config('manufacturing.engines');

        $this->assertIsArray($engines);
        $this->assertCount(8, $engines);

        $configured = [];
        foreach ($engines as $engine) {
            $this->assertNotEmpty($engine['modules'], "Engine {$engine['name']} must define modules");
            foreach ($engine['modules'] as $key => $meta) {
                $configured[$key] = $meta;
            }
        }

        $this->assertCount(22, $configured, 'The 5 core engines must define all 22 child modules');

        $registry = DB::table('module_registry')
            ->where('key', 'like', 'manufacturing%')
            ->pluck('key')
            ->flip()
            ->all();

        // 22 children + the parent = 23 base modules.
        $this->assertCount(23, $registry, 'Parent + 22 children must equal 23 base modules');

        foreach (array_keys($configured) as $key) {
            $this->assertArrayHasKey($key, $registry, "Engine module not registered: {$key}");
            $this->assertNotSame('manufacturing', $key, 'The parent must not be listed as an engine child');
        }

        $this->assertTrue(config('manufacturing.allow_custom_modules'));
        $this->assertSame('manufacturing.custom.', config('manufacturing.custom_module_prefix'));
    }
}
