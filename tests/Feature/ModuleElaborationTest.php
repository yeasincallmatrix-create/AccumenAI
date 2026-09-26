<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Module registry elaboration — parents turned into parent → parent.child
 * hierarchies so /admin/module-config renders them as collapsible groups.
 *
 * manufacturing (22 children, 5 core engines) is elaborated too; pos owns its
 * 11 children (Phase 1: terminal, register, cart, checkout, receipt; Phase 2:
 * cash, card, mobile_payment, split_payment, shift, cash_drawer).
 */
class ModuleElaborationTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<string, int> */
    private const EXPECTED_CHILDREN = [
        'accounting' => 28,
        'hr' => 7,
        'ai' => 4,
        'reports' => 6,
        'vat' => 4,
        'tds' => 4,
        'crm' => 6,
        'finance' => 5,
        'inventory' => 9,
        'manufacturing' => 22,
        'pos' => 11,
    ];

    /** @var array<string, list<string>> */
    private const EXPECTED_KEYS = [
        'accounting' => [
            'accounting.chart_of_accounts', 'accounting.journal_entries', 'accounting.general_ledger',
            'accounting.periods', 'accounting.fiscal_years', 'accounting.invoices', 'accounting.bills',
            'accounting.payments', 'accounting.expenses', 'accounting.receipts', 'accounting.receivables',
            'accounting.payables', 'accounting.bank_feed', 'accounting.bank_reconciliation', 'accounting.bank_accounts',
            'accounting.reports', 'accounting.trial_balance', 'accounting.balance_sheet', 'accounting.profit_loss',
            'accounting.cash_flow', 'accounting.ratios', 'accounting.tax_reports', 'accounting.vat_input',
            'accounting.vat_output', 'accounting.vat_summary', 'accounting.executive_dashboard',
            'accounting.approvals', 'accounting.security_audit',
        ],
        'hr' => [
            'hr.employees', 'hr.attendance', 'hr.payroll', 'hr.leaves',
            'hr.departments', 'hr.designations', 'hr.recruitment',
        ],
        'ai' => ['ai.assistant', 'ai.analysis', 'ai.insights', 'ai.tools'],
        'reports' => ['reports.sales', 'reports.purchase', 'reports.financial', 'reports.inventory', 'reports.tax', 'reports.hr'],
        'vat' => ['vat.rates', 'vat.returns', 'vat.reports', 'vat.transactions'],
        'tds' => ['tds.rates', 'tds.deductions', 'tds.returns', 'tds.reports'],
        'crm' => ['crm.contacts', 'crm.leads', 'crm.organizations', 'crm.activities', 'crm.tasks', 'crm.reports'],
        'finance' => ['finance.invoices', 'finance.payments', 'finance.expenses', 'finance.parties', 'finance.progressive_contracts'],
        'inventory' => [
            'inventory.items', 'inventory.stock_ledger', 'inventory.warehouses', 'inventory.adjustments',
            'inventory.batches', 'inventory.transfers', 'inventory.barcode', 'inventory.counts', 'inventory.reports',
        ],
        'manufacturing' => [
            'manufacturing.bom', 'manufacturing.routing', 'manufacturing.work_centers',
            'manufacturing.production_orders', 'manufacturing.quality_control', 'manufacturing.quality_lab',
            'manufacturing.sample_management', 'manufacturing.regulatory_compliance',
            'manufacturing.batch_tracking', 'manufacturing.expiry_tracking', 'manufacturing.serial_number',
            'manufacturing.costing', 'manufacturing.assembly_line', 'manufacturing.mold_management',
            'manufacturing.recipe', 'manufacturing.cutting', 'manufacturing.welding', 'manufacturing.finishing',
            'manufacturing.printing', 'manufacturing.packaging', 'manufacturing.warranty', 'manufacturing.reports',
        ],
        'pos' => [
            'pos.terminal', 'pos.register', 'pos.cart', 'pos.checkout', 'pos.receipt',
            'pos.cash', 'pos.card', 'pos.mobile_payment', 'pos.split_payment',
            'pos.shift', 'pos.cash_drawer',
        ],
    ];

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

    /**
     * @return list<string>
     */
    private function childKeys(string $parent): array
    {
        return DB::table('module_registry')
            ->where('parent_key', $parent)
            ->orderBy('sort_order')
            ->orderBy('key')
            ->pluck('key')
            ->toArray();
    }

    /**
     * @param  list<string>  $expected
     */
    private function assertChildren(string $parent, array $expected): void
    {
        $parentRow = DB::table('module_registry')->where('key', $parent)->first();

        $this->assertNotNull($parentRow, "Parent {$parent} must exist in module_registry");
        $this->assertSame('active', $parentRow->status, "Parent {$parent} must be active");
        $this->assertNull($parentRow->parent_key, "Parent {$parent} must not be nested itself");

        $keys = $this->childKeys($parent);

        foreach ($expected as $key) {
            $this->assertContains($key, $keys, "Child {$key} must be registered under {$parent}");
        }

        $this->assertCount(count($expected), $keys, "Parent {$parent} must own exactly ".count($expected).' children');

        $rows = DB::table('module_registry')->whereIn('key', $expected)->get();

        foreach ($rows as $row) {
            $this->assertSame($parent, $row->parent_key, "{$row->key} must point at {$parent}");
            $this->assertSame('active', $row->status, "{$row->key} must be active");
            $this->assertFalse((bool) $row->coming_soon, "{$row->key} must not be coming_soon");
            $this->assertNotEmpty($row->icon, "{$row->key} must have an icon");
            $this->assertNotEmpty($row->name, "{$row->key} must have a name");
        }

        $parentType = DB::table('module_registry')->where('key', $parent)->value('type');
        $mismatched = DB::table('module_registry')
            ->whereIn('key', $expected)
            ->where('type', '!=', $parentType)
            ->pluck('key');

        $this->assertSame([], $mismatched->all(), 'Children must inherit the parent module type');
    }

    public function test_accounting_has_28_children(): void
    {
        $this->assertChildren('accounting', self::EXPECTED_KEYS['accounting']);
        $this->assertSame(28, self::EXPECTED_CHILDREN['accounting']);
    }

    public function test_hr_has_7_children(): void
    {
        $this->assertChildren('hr', self::EXPECTED_KEYS['hr']);
    }

    public function test_ai_has_4_children(): void
    {
        $this->assertChildren('ai', self::EXPECTED_KEYS['ai']);
    }

    public function test_reports_has_6_children(): void
    {
        $this->assertChildren('reports', self::EXPECTED_KEYS['reports']);
    }

    public function test_vat_has_4_children(): void
    {
        $this->assertChildren('vat', self::EXPECTED_KEYS['vat']);
    }

    public function test_tds_has_4_children(): void
    {
        $this->assertChildren('tds', self::EXPECTED_KEYS['tds']);
    }

    public function test_crm_has_6_children(): void
    {
        $this->assertChildren('crm', self::EXPECTED_KEYS['crm']);
    }

    public function test_finance_has_5_children(): void
    {
        $this->assertChildren('finance', self::EXPECTED_KEYS['finance']);
    }

    public function test_inventory_has_9_children(): void
    {
        $this->assertChildren('inventory', self::EXPECTED_KEYS['inventory']);
    }

    public function test_manufacturing_has_22_children(): void
    {
        $this->assertChildren('manufacturing', self::EXPECTED_KEYS['manufacturing']);
        $this->assertSame(22, self::EXPECTED_CHILDREN['manufacturing']);
    }

    public function test_pos_has_phase1_and_phase2_children(): void
    {
        $row = DB::table('module_registry')->where('key', 'pos')->first();

        $this->assertNotNull($row);
        $this->assertNull($row->parent_key);
        $this->assertSame(11, DB::table('module_registry')->where('parent_key', 'pos')->count(),
            'pos must own exactly its 11 children (Phase 1: 5, Phase 2: 6)');

        $this->assertChildren('pos', self::EXPECTED_KEYS['pos']);
    }

    public function test_all_elaborated_parents_collapsible(): void
    {
        $html = $this->actingAs($this->platformAdmin(), 'platform_admin')
            ->get(route('admin.module-config.index', [
                'industry' => 'healthcare',
                'subcategory' => 'pharmacy',
            ]))
            ->assertOk()
            ->getContent();

        $expectedToggles = array_merge(array_keys(self::EXPECTED_CHILDREN), ['education', 'medical', 'purchase', 'sales', 'training_center']);
        sort($expectedToggles);

        foreach ($expectedToggles as $parent) {
            $this->assertStringContainsString(
                'data-module-toggle="'.$parent.'"',
                $html,
                "Parent {$parent} must render as a collapsible row"
            );
            $this->assertStringContainsString(
                'data-child-of="'.$parent.'"',
                $html,
                "Parent {$parent} must render indented child rows"
            );
        }

        foreach (['notifications'] as $single) {
            $this->assertStringNotContainsString(
                'data-module-toggle="'.$single.'"',
                $html,
                "Childless module {$single} must stay a single row"
            );
        }

        $this->assertStringContainsString('data-module-toggle="accounting"', $html);
        $this->assertStringContainsString('accounting.chart_of_accounts', $html);
        $this->assertStringContainsString('inventory.stock_ledger', $html);
        $this->assertStringContainsString('tds.deductions', $html);

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        foreach (self::EXPECTED_CHILDREN as $parent => $count) {
            $this->assertSame(
                $count,
                $xpath->query('//tr[@data-child-of="'.$parent.'"]')->length,
                "Parent {$parent} must render exactly {$count} indented child rows"
            );
            $this->assertSame(
                1,
                $xpath->query('//*[@data-module-toggle="'.$parent.'"]')->length,
                "Parent {$parent} must render exactly one toggle"
            );
        }
    }

    public function test_inventory_children_save(): void
    {
        $id = (int) DB::table('industry_subcategories')
            ->where('industry_key', 'healthcare')
            ->where('subcategory_key', 'pharmacy')
            ->value('id');

        $this->assertGreaterThan(0, $id, 'healthcare/pharmacy subcategory must exist');

        $payload = [];
        foreach (['inventory', 'inventory.items', 'inventory.warehouses', 'inventory.stock_ledger'] as $i => $key) {
            $payload['row'.$i] = [
                'module_key' => $key,
                'category' => $key === 'inventory.items' ? 'mandatory' : 'default',
            ];
        }

        $this->actingAs($this->platformAdmin(), 'platform_admin')
            ->put(route('admin.module-config.update'), [
                'industry' => 'healthcare',
                'subcategory' => 'pharmacy',
                'modules' => $payload,
            ])
            ->assertRedirect(route('admin.module-config.index', [
                'industry' => 'healthcare',
                'subcategory' => 'pharmacy',
            ]));

        foreach ($payload as $row) {
            $this->assertDatabaseHas('subcategory_default_modules', [
                'subcategory_id' => $id,
                'module_key' => $row['module_key'],
                'category' => $row['category'],
            ]);
        }
    }

    public function test_accounting_children_save(): void
    {
        $id = (int) DB::table('industry_subcategories')
            ->where('industry_key', 'healthcare')
            ->where('subcategory_key', 'pharmacy')
            ->value('id');

        $this->assertGreaterThan(0, $id, 'healthcare/pharmacy subcategory must exist');

        $payload = [];
        foreach (['accounting.chart_of_accounts', 'accounting.general_ledger', 'accounting.trial_balance'] as $i => $key) {
            $payload['row'.$i] = [
                'module_key' => $key,
                'category' => $i === 0 ? 'mandatory' : 'default',
            ];
        }

        $this->actingAs($this->platformAdmin(), 'platform_admin')
            ->put(route('admin.module-config.update'), [
                'industry' => 'healthcare',
                'subcategory' => 'pharmacy',
                'modules' => $payload,
            ])
            ->assertRedirect(route('admin.module-config.index', [
                'industry' => 'healthcare',
                'subcategory' => 'pharmacy',
            ]));

        $saved = DB::table('subcategory_default_modules')
            ->where('subcategory_id', $id)
            ->where('module_key', 'like', 'accounting.%')
            ->pluck('module_key')
            ->sort()
            ->values()
            ->all();

        foreach (array_column($payload, 'module_key') as $key) {
            $this->assertContains($key, $saved, "Accounting child {$key} must persist to the matrix");
            $this->assertDatabaseHas('subcategory_default_modules', [
                'subcategory_id' => $id,
                'module_key' => $key,
            ]);
        }
    }
}
