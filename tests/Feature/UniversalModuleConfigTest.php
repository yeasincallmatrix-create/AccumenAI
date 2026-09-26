<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\PlatformAdmin;
use App\Services\IndustrySubcategoryService;
use App\Services\ModuleAccessService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Universal Module Config — /admin/module-config
 *
 * Covers the platform matrix: read (groups + per-module radios), idempotent
 * write, cache flush for matching tenants, audit trail, sub-category copy and
 * the fourth 'hidden' bucket.
 */
class UniversalModuleConfigTest extends TestCase
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

    private function subcategoryId(string $industry, string $subcategoryKey): int
    {
        return (int) DB::table('industry_subcategories')
            ->where('industry_key', $industry)
            ->where('subcategory_key', $subcategoryKey)
            ->value('id');
    }

    /**
     * @param  array<string, array{module_key: string, category: string}>  $modules
     */
    private function saveMatrix(array $modules, string $industry = 'healthcare', string $subcategory = 'pharmacy')
    {
        return $this->actingAs($this->platformAdmin(), 'platform_admin')->put(
            route('admin.module-config.update'),
            [
                'industry' => $industry,
                'subcategory' => $subcategory,
                'modules' => $modules,
            ]
        );
    }

    public function test_unauthenticated_is_redirected_to_admin_login(): void
    {
        $this->get(route('admin.module-config.index'))->assertRedirect(route('admin.login'));

        $this->put(route('admin.module-config.update'), [])->assertRedirect(route('admin.login'));
    }

    public function test_index_loads_with_sidebar_link_for_platform_admin(): void
    {
        $this->actingAs($this->platformAdmin(), 'platform_admin')
            ->get(route('admin.module-config.index'))
            ->assertOk()
            ->assertSee('Universal Module Configuration')
            ->assertSee('<span class="sidebar-label">Module Config</span>', false);
    }

    public function test_matrix_loads_modules_for_selected_industry_and_subcategory(): void
    {
        $this->actingAs($this->platformAdmin(), 'platform_admin')
            ->get(route('admin.module-config.index', [
                'industry' => 'healthcare',
                'subcategory' => 'pharmacy',
            ]))
            ->assertOk()
            ->assertSee('Medical Modules')
            ->assertSee('healthcare / pharmacy')
            ->assertSee('medical.pharmacy')
            ->assertSee('mc_core_0_hidden', false);
    }

    public function test_saving_matrix_persists_categories(): void
    {
        $id = $this->subcategoryId('healthcare', 'pharmacy');

        $this->saveMatrix([
            'row1' => ['module_key' => 'inventory', 'category' => 'default'],
            'row2' => ['module_key' => 'sales', 'category' => 'optional'],
            'row3' => ['module_key' => 'medical.laboratory', 'category' => 'hidden'],
        ])->assertRedirect(route('admin.module-config.index', [
            'industry' => 'healthcare',
            'subcategory' => 'pharmacy',
        ]));

        $this->assertDatabaseHas('subcategory_default_modules', [
            'subcategory_id' => $id,
            'module_key' => 'inventory',
            'category' => 'default',
        ]);
        $this->assertDatabaseHas('subcategory_default_modules', [
            'subcategory_id' => $id,
            'module_key' => 'sales',
            'category' => 'optional',
        ]);
        $this->assertDatabaseHas('subcategory_default_modules', [
            'subcategory_id' => $id,
            'module_key' => 'medical.laboratory',
            'category' => 'hidden',
        ]);
    }

    public function test_saving_matrix_is_idempotent(): void
    {
        $id = $this->subcategoryId('healthcare', 'pharmacy');

        $payload = [
            'row1' => ['module_key' => 'inventory', 'category' => 'default'],
            'row2' => ['module_key' => 'sales', 'category' => 'optional'],
            'row3' => ['module_key' => 'medical.laboratory', 'category' => 'hidden'],
        ];

        $this->saveMatrix($payload)->assertRedirect();
        $afterFirst = DB::table('subcategory_default_modules')->where('subcategory_id', $id)->count();

        $this->saveMatrix($payload)->assertRedirect();
        $afterSecond = DB::table('subcategory_default_modules')->where('subcategory_id', $id)->count();

        $this->assertSame($afterFirst, $afterSecond, 'Re-saving the same matrix must not duplicate rows');
        $this->assertSame(1, DB::table('subcategory_default_modules')
            ->where('subcategory_id', $id)
            ->where('module_key', 'inventory')
            ->count(), 'Each module keeps exactly one row per sub-category');
    }

    public function test_save_writes_platform_audit_log(): void
    {
        $this->saveMatrix([
            'row1' => ['module_key' => 'inventory', 'category' => 'mandatory'],
        ])->assertRedirect();

        $log = DB::table('module_access_logs')
            ->where('action', 'matrix_update')
            ->where('module_key', '*config*healthcare.pharmacy')
            ->latest('id')
            ->first();

        $this->assertNotNull($log, 'Matrix save must be audited on module_access_logs');
        $this->assertSame('platform_admin', $log->actor_type);
        $this->assertSame('medium', $log->risk_level);
        $this->assertNull($log->institute_id);
        $this->assertNotNull($log->actor_id);
    }

    public function test_save_flushes_tenant_module_cache(): void
    {
        TenantContext::clear();

        $row = DB::table('institutes')->orderBy('id')->first();
        $this->assertNotNull($row, 'Test database must contain at least one institute');

        DB::table('institutes')->where('id', $row->id)->update([
            'industry' => 'healthcare',
            'subcategory_key' => 'pharmacy',
        ]);

        $institute = Institute::withoutGlobalScopes()->findOrFail($row->id);
        $cacheKey = 'module_access:'.$institute->id;

        app(ModuleAccessService::class)->getEnabledModules($institute);
        $this->assertTrue(Cache::has($cacheKey), 'Module cache should be primed before the save');

        $this->saveMatrix([
            'row1' => ['module_key' => 'inventory', 'category' => 'default'],
        ])->assertRedirect();

        $this->assertFalse(Cache::has($cacheKey), 'Save must flush the cache of every matching tenant');
    }

    public function test_copy_configuration_from_another_subcategory(): void
    {
        $sourceId = $this->subcategoryId('education', 'school');
        $targetId = $this->subcategoryId('healthcare', 'pharmacy');

        $expected = DB::table('subcategory_default_modules')
            ->where('subcategory_id', $sourceId)
            ->pluck('category', 'module_key')
            ->all();
        ksort($expected);
        $this->assertNotEmpty($expected, 'Source sub-category must have rows to copy');

        $this->actingAs($this->platformAdmin(), 'platform_admin')
            ->post(route('admin.module-config.copy'), [
                'source_industry' => 'education',
                'source_subcategory' => 'school',
                'target_industry' => 'healthcare',
                'target_subcategory' => 'pharmacy',
            ])
            ->assertRedirect(route('admin.module-config.index', [
                'industry' => 'healthcare',
                'subcategory' => 'pharmacy',
            ]));

        $actual = DB::table('subcategory_default_modules')
            ->where('subcategory_id', $targetId)
            ->pluck('category', 'module_key')
            ->all();
        ksort($actual);

        $this->assertSame($expected, $actual, 'Target must be an exact copy of the source configuration');
    }

    public function test_hidden_category_is_saved_and_exposed_by_service(): void
    {
        $id = $this->subcategoryId('healthcare', 'pharmacy');

        $this->saveMatrix([
            'row1' => ['module_key' => 'sales', 'category' => 'hidden'],
        ])->assertRedirect();

        $this->assertDatabaseHas('subcategory_default_modules', [
            'subcategory_id' => $id,
            'module_key' => 'sales',
            'category' => 'hidden',
        ]);

        $modules = app(IndustrySubcategoryService::class)->getModules('healthcare', 'pharmacy');

        $this->assertContains('sales', $modules['hidden'], 'Hidden bucket must surface for the tenant-side filter');
        $this->assertNotContains('sales', $modules['mandatory']);
        $this->assertNotContains('sales', $modules['default']);
        $this->assertNotContains('sales', $modules['optional']);
    }

    public function test_all_module_groups_render_for_selected_subcategory(): void
    {
        $html = $this->actingAs($this->platformAdmin(), 'platform_admin')
            ->get(route('admin.module-config.index', [
                'industry' => 'healthcare',
                'subcategory' => 'pharmacy',
            ]))
            ->assertOk()
            ->getContent();

        $labels = [
            'Core Modules',
            'Medical Modules',
            'Education Modules',
            'Training Center Modules',
            'Sales Modules',
            'Purchase Modules',
            'Inventory Modules',
            'Manufacturing Modules',
            'Real Estate Modules',
        ];

        foreach ($labels as $label) {
            $this->assertStringContainsString($label, $html, "Missing module group on the matrix page: {$label}");
        }
    }

    public function test_parent_child_hierarchy_is_elaborated_and_singles_stay_flat(): void
    {
        $html = $this->actingAs($this->platformAdmin(), 'platform_admin')
            ->get(route('admin.module-config.index', [
                'industry' => 'healthcare',
                'subcategory' => 'pharmacy',
            ]))
            ->assertOk()
            ->assertSee('data-module-toggle="medical"', false)
            ->assertSee('data-child-of="medical"', false)
            ->assertSee('data-module-toggle', false)
            ->getContent();

        $dom = $this->dom($html);
        $xpath = new \DOMXPath($dom);

        $this->assertSame(
            ['accounting', 'ai', 'crm', 'education', 'finance', 'hr', 'inventory', 'manufacturing', 'medical', 'purchase', 'real_estate', 'reports', 'sales', 'tds', 'training_center', 'vat'],
            $this->attributeValues($xpath, '//*[@data-module-toggle]', 'data-module-toggle'),
            'Every module that owns children must render as a collapsible parent row',
        );

        $this->assertSame(0, $xpath->query('//*[@data-module-toggle="notifications"]')->length,
            'Childless modules must render as a single row with no toggle');
        $this->assertSame(0, $xpath->query('//*[@data-module-toggle="pos"]')->length,
            'Not-yet-built modules must render as a single row with no toggle');

        $this->assertSame(1, $xpath->query('//tr[@data-child-of="medical"]//code[text()="medical.pharmacy"]')->length,
            'medical.pharmacy must render as an indented child row of medical');
        $this->assertSame(1, $xpath->query('//tr[@data-child-of="sales"]//code[text()="sales.orders"]')->length,
            'sales.orders must render as an indented child row of sales');
        $this->assertSame(1, $xpath->query('//td//code[text()="medical"]')->length,
            'The medical parent must render exactly once');
        $this->assertSame(1, $xpath->query('//input[@type="hidden" and @value="medical.pharmacy"]')->length,
            'A child key must submit exactly one module_key input — no duplicate rows');

        $this->assertStringContainsString("row.classList.toggle('d-none'", $html,
            'Collapsible children need the toggle handler on the page');
    }

    public function test_rendered_form_round_trips_parent_child_and_single_rows(): void
    {
        $id = $this->subcategoryId('healthcare', 'pharmacy');

        $html = $this->actingAs($this->platformAdmin(), 'platform_admin')
            ->get(route('admin.module-config.index', [
                'industry' => 'healthcare',
                'subcategory' => 'pharmacy',
            ]))
            ->assertOk()
            ->getContent();

        $payload = [];
        foreach ($this->dom($html)->getElementsByTagName('input') as $input) {
            if ($input->getAttribute('type') !== 'hidden') {
                continue;
            }
            if (! preg_match('/^modules\[(.+)\]\[module_key\]$/', $input->getAttribute('name'), $m)) {
                continue;
            }

            $key = $input->getAttribute('value');
            $payload[$m[1]] = [
                'module_key' => $key,
                'category' => match ($key) {
                    'medical' => 'mandatory',
                    'medical.pharmacy' => 'optional',
                    'crm' => 'default',
                    default => 'hidden',
                },
            ];
        }

        $this->assertArrayHasKey('medical', $this->moduleKeyIndex($payload), 'Parent row missing from the rendered form');
        $this->assertArrayHasKey('medical.pharmacy', $this->moduleKeyIndex($payload), 'Child row missing from the rendered form');
        $this->assertArrayHasKey('crm', $this->moduleKeyIndex($payload), 'Single row missing from the rendered form');

        $this->saveMatrix($payload)->assertRedirect(route('admin.module-config.index', [
            'industry' => 'healthcare',
            'subcategory' => 'pharmacy',
        ]));

        foreach (['medical' => 'mandatory', 'medical.pharmacy' => 'optional', 'crm' => 'default'] as $key => $category) {
            $this->assertDatabaseHas('subcategory_default_modules', [
                'subcategory_id' => $id,
                'module_key' => $key,
                'category' => $category,
            ]);
        }

        $this->assertSame(count($payload), DB::table('subcategory_default_modules')
            ->where('subcategory_id', $id)
            ->whereIn('module_key', array_column($payload, 'module_key'))
            ->count(), 'Every rendered row must persist exactly once');
    }

    private function dom(string $html): \DOMDocument
    {
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        return $dom;
    }

    /**
     * @return array<int, string>
     */
    private function attributeValues(\DOMXPath $xpath, string $query, string $attribute): array
    {
        $values = [];
        foreach ($xpath->query($query) as $node) {
            $values[] = $node->getAttribute($attribute);
        }
        sort($values);

        return $values;
    }

    /**
     * @param  array<string, array{module_key: string, category: string}>  $payload
     * @return array<string, string>
     */
    private function moduleKeyIndex(array $payload): array
    {
        $index = [];
        foreach ($payload as $row) {
            $index[$row['module_key']] = $row['category'];
        }

        return $index;
    }
}
