<?php

namespace Tests\Feature;

use App\Services\IndustrySubcategoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 6 — Industry Sub-Category service against Phase 3 seeded data
 * (industry_subcategories + subcategory_default_modules on both DBs).
 */
class IndustrySubcategoryTest extends TestCase
{
    use DatabaseTransactions;

    private IndustrySubcategoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(IndustrySubcategoryService::class);
    }

    public function test_subcategories_seeded(): void
    {
        $count = DB::table('industry_subcategories')->where('is_active', true)->count();
        $this->assertGreaterThanOrEqual(23, $count, 'Phase 3 seeded 23 sub-categories');
    }

    public function test_subcategory_module_mappings_seeded(): void
    {
        $count = DB::table('subcategory_default_modules')->count();
        $this->assertGreaterThanOrEqual(118, $count, 'Phase 3 seeded 118 module mappings');
    }

    public function test_pharmacy_subcategory_returns_expected_modules(): void
    {
        $modules = $this->service->getModules('healthcare', 'pharmacy');

        $this->assertContains('medical.billing', $modules['mandatory']);
        $this->assertContains('medical.pharmacy', $modules['mandatory']);
        $this->assertContains('inventory', $modules['default']);
        $this->assertContains('medical.records', $modules['default']);
        $this->assertContains('medical.laboratory', $modules['optional']);
    }

    public function test_school_subcategory_returns_expected_modules(): void
    {
        $modules = $this->service->getModules('education', 'school');

        $this->assertContains('education.fees', $modules['mandatory']);
        $this->assertContains('education.classes', $modules['default']);
        $this->assertContains('education.exams', $modules['default']);
        $this->assertContains('education.students', $modules['default']);
    }

    public function test_all_three_categories_populated_for_pharmacy(): void
    {
        $modules = $this->service->getModules('healthcare', 'pharmacy');

        $this->assertNotEmpty($modules['mandatory'], 'mandatory category populated');
        $this->assertNotEmpty($modules['default'], 'default category populated');
        $this->assertNotEmpty($modules['optional'], 'optional category populated');
    }

    public function test_all_mapping_keys_exist_in_module_registry(): void
    {
        $registry = DB::table('module_registry')->pluck('key')->flip();
        $bad = DB::table('subcategory_default_modules')
            ->whereNotIn('module_key', $registry->keys()->all())
            ->pluck('module_key')
            ->unique()
            ->all();

        $this->assertSame([], $bad, 'Every mapping must reference a real registry key');
    }

    public function test_list_for_industry_returns_only_that_industry(): void
    {
        $rows = $this->service->listForIndustry('healthcare');

        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertSame('healthcare', $row->industry_key);
            $this->assertTrue((bool) $row->is_active);
        }
    }

    public function test_find_by_key_returns_subcategory(): void
    {
        $sub = $this->service->findByKey('healthcare', 'pharmacy');

        $this->assertNotNull($sub);
        $this->assertSame('pharmacy', $sub->subcategory_key);
        $this->assertNotEmpty($sub->name);
    }

    public function test_find_by_key_unknown_returns_null(): void
    {
        $this->assertNull($this->service->findByKey('healthcare', 'does-not-exist'));
    }

    public function test_unknown_subcategory_returns_empty_groups(): void
    {
        $modules = $this->service->getModules('healthcare', 'does-not-exist');

        $this->assertSame([], $modules['mandatory']);
        $this->assertSame([], $modules['default']);
        $this->assertSame([], $modules['optional']);
    }
}
