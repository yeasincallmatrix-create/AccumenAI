<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Accounting Phase A — the 5 aging report modules.
 *
 * Pins: registry shape (parent/type/icon/sort), the 268 → 273 and
 * 28 → 33 totals, the config/config aging block, activation parity with the
 * existing accounting.* children, and their render under the Accounting
 * parent on /admin/module-config (B2: no new module_groups entry).
 */
class AccountingAgingModulesTest extends TestCase
{
    use DatabaseTransactions;

    private const AGING_KEYS = [
        'accounting.ar_aging',
        'accounting.ap_aging',
        'accounting.invoice_aging',
        'accounting.aging_summary',
        'accounting.aging_config',
    ];

    private function platformAdmin(): PlatformAdmin
    {
        TenantContext::clear();

        return PlatformAdmin::firstOrReuseForTests([
            'email' => 'aging-'.uniqid().'@example.test',
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    public function test_five_aging_modules_are_registered_under_accounting(): void
    {
        $parent = DB::table('module_registry')->where('key', 'accounting')->first();

        $this->assertNotNull($parent, 'Accounting parent must exist');
        $this->assertNull($parent->parent_key);

        $rows = DB::table('module_registry')
            ->whereIn('key', self::AGING_KEYS)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('key');

        $this->assertCount(5, $rows, 'All 5 aging modules must be registered');

        $expectedOrder = [
            'accounting.ar_aging' => 100,
            'accounting.ap_aging' => 101,
            'accounting.invoice_aging' => 102,
            'accounting.aging_summary' => 103,
            'accounting.aging_config' => 104,
        ];

        foreach (self::AGING_KEYS as $key) {
            $row = $rows->get($key);
            $this->assertNotNull($row, "{$key} must exist");
            $this->assertSame('accounting', $row->parent_key, "{$key} must nest under accounting");
            $this->assertSame('active', $row->status, "{$key} must be active");
            $this->assertSame($parent->type, $row->type, "{$key} must inherit the parent type");
            $this->assertSame((int) $parent->is_core, (int) $row->is_core, "{$key} must inherit is_core");
            $this->assertFalse((bool) $row->coming_soon, "{$key} must not be coming_soon");
            $this->assertNotEmpty($row->name, "{$key} must have a name");
            $this->assertNotEmpty($row->icon, "{$key} must have an icon");
            $this->assertSame($expectedOrder[$key], (int) $row->sort_order, "{$key} sort_order");
        }
    }

    public function test_aging_does_not_collide_with_existing_keys(): void
    {
        $foreign = DB::table('module_registry')
            ->where('key', 'like', '%aging%')
            ->whereNotIn('key', self::AGING_KEYS)
            ->pluck('key');

        // real_estate.aging_report predates Phase A and must stay distinct.
        $this->assertContains('real_estate.aging_report', $foreign->all());

        foreach (self::AGING_KEYS as $key) {
            $this->assertFalse($foreign->contains($key), "{$key} must not shadow an existing key");
        }
    }

    public function test_registry_totals_are_279_with_33_accounting_children(): void
    {
        $total = DB::table('module_registry')->where('status', 'active')->count();
        $children = DB::table('module_registry')
            ->where('parent_key', 'accounting')
            ->where('status', 'active')
            ->count();

        $this->assertSame(279, $total, 'Registry must be 273 + 6 (dealership phase 1) active modules');
        $this->assertSame(33, $children, 'Accounting must own 28 + 5 children');
    }

    public function test_config_declares_the_aging_block(): void
    {
        $aging = config('accounting.aging');

        $this->assertIsArray($aging, 'config/accounting.php must expose a flat aging block');
        $this->assertSame('Aging Reports', $aging['name']);
        $this->assertFalse($aging['required']);
        $this->assertSame('invoices', $aging['source']['ar']);
        $this->assertSame('purchase_invoices', $aging['source']['ap']);

        $this->assertSame(
            array_values(self::AGING_KEYS),
            array_keys($aging['modules']),
            'config aging.modules must list the 5 registry keys in order'
        );

        $buckets = array_column($aging['buckets'], 'key');
        $this->assertSame(['current', 'd31_60', 'd61_90', 'd91_120', 'd121_plus'], $buckets);
        $this->assertSame(0, $aging['buckets'][0]['min']);
        $this->assertSame(30, $aging['buckets'][0]['max']);
        $this->assertNull($aging['buckets'][4]['max'], 'The top bucket must be open-ended');
    }

    public function test_activation_parity_with_existing_accounting_children(): void
    {
        $packagePairs = DB::table('package_industry_modules')
            ->where('module_key', 'like', 'accounting.%')
            ->groupBy('package_id', 'industry_key')
            ->select('package_id', 'industry_key')
            ->get();

        $packageAgingRows = DB::table('package_industry_modules')
            ->whereIn('module_key', self::AGING_KEYS)
            ->count();

        // monetix_test carries no accounting.* activation rows at all, so this
        // stays 0 === 0 there; on the seeded DB it pins 3 pairs × 5 = 15.
        $this->assertSame(
            $packagePairs->count() * 5,
            $packageAgingRows,
            'Aging activation rows must be exactly 5 per accounting pair'
        );

        foreach ($packagePairs as $pair) {
            $present = DB::table('package_industry_modules')
                ->where('package_id', $pair->package_id)
                ->where('industry_key', $pair->industry_key)
                ->whereIn('module_key', self::AGING_KEYS)
                ->count();

            $this->assertSame(5, $present, "({$pair->package_id}, {$pair->industry_key}) must carry all 5 aging keys");
            $this->assertSame(0, $missing);

            $rootEnabled = (int) DB::table('package_industry_modules')
                ->where('package_id', $pair->package_id)
                ->where('industry_key', $pair->industry_key)
                ->where('module_key', 'accounting')
                ->value('enabled');

            $enabled = DB::table('package_industry_modules')
                ->where('package_id', $pair->package_id)
                ->where('industry_key', $pair->industry_key)
                ->whereIn('module_key', self::AGING_KEYS)
                ->pluck('enabled');

            foreach ($enabled as $flag) {
                $this->assertSame($rootEnabled, (int) $flag, 'Aging rows must inherit the accounting root flag');
            }
        }

        $subcategoryIds = DB::table('subcategory_default_modules')
            ->where('module_key', 'like', 'accounting.%')
            ->distinct()
            ->pluck('subcategory_id');

        $subcategoryAgingRows = DB::table('subcategory_default_modules')
            ->whereIn('module_key', self::AGING_KEYS)
            ->count();

        $this->assertSame(
            $subcategoryIds->count() * 5,
            $subcategoryAgingRows,
            'Aging subcategory rows must be exactly 5 per accounting subcategory'
        );

        foreach ($subcategoryIds as $subcategoryId) {
            $present = DB::table('subcategory_default_modules')
                ->where('subcategory_id', $subcategoryId)
                ->whereIn('module_key', self::AGING_KEYS)
                ->count();

            $this->assertSame(5, $present, "Subcategory {$subcategoryId} must carry all 5 aging keys");

            $rootCategory = DB::table('subcategory_default_modules')
                ->where('subcategory_id', $subcategoryId)
                ->where('module_key', 'accounting')
                ->value('category');

            $categories = DB::table('subcategory_default_modules')
                ->where('subcategory_id', $subcategoryId)
                ->whereIn('module_key', self::AGING_KEYS)
                ->pluck('category');

            foreach ($categories as $category) {
                $this->assertSame((string) $rootCategory, (string) $category, 'Aging rows must inherit the accounting category');
            }
        }
    }

    public function test_module_config_renders_aging_children_under_accounting(): void
    {
        $html = $this->actingAs($this->platformAdmin(), 'platform_admin')
            ->get(route('admin.module-config.index', [
                'industry' => 'healthcare',
                'subcategory' => 'pharmacy',
            ]))
            ->assertOk()
            ->getContent();

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        $accountingRows = $xpath->query('//tr[@data-child-of="accounting"]');
        $this->assertSame(33, $accountingRows->length, 'All 33 accounting children must render under the parent');

        foreach (self::AGING_KEYS as $key) {
            $nodes = $xpath->query('//tr[@data-child-key="'.$key.'"]');
            $this->assertSame(1, $nodes->length, "{$key} must render exactly one row");
        }

        // B2: aging nests under the existing Accounting parent — no new group.
        $this->assertStringContainsString('data-module-toggle="accounting"', $html);
        $this->assertStringNotContainsString('data-module-toggle="aging"', $html);
    }
}
