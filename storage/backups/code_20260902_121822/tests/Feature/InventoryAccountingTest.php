<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Country;
use App\Models\Institute;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryWarehouse;
use App\Models\Party;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Accounting\FinancialReportService;
use App\Services\Accounting\InvoiceService;
use App\Services\Accounting\PartyService;
use App\Services\Inventory\InventoryItemService;
use App\Services\Inventory\InventoryStockService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * STEP 16 — Inventory accounting integration.
 *
 * Inventory events reuse the existing double-entry engine unchanged:
 * purchase receipts post Dr Inventory / Cr AP (or cash), sales issues post
 * Dr COGS / Cr Inventory, adjustments post against the adjustment income /
 * expense accounts. The invoice hook issues stock + COGS when items carry an
 * inventory_item_id while leaving non-inventory invoices untouched, and
 * cancelling such an invoice restocks the warehouse and reverses the COGS
 * journal.
 */
class InventoryAccountingTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        parent::tearDown();
    }

    private function stock(): InventoryStockService
    {
        return app(InventoryStockService::class);
    }

    private function country(): Country
    {
        return Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BDR', 'phone_code' => '880', 'status' => true]
        );
    }

    private function institute(string $industry = 'retail'): Institute
    {
        $country = $this->country();

        return Institute::create([
            'name' => 'Acc Inst',
            'slug' => str()->slug('Acc Inst-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'industry' => $industry,
            'status' => 'active',
        ]);
    }

    private function branch(Institute $institute, string $name): Branch
    {
        return Branch::create([
            'institute_id' => $institute->id,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function setupAccounting(Institute $institute, ?Branch $branch = null): void
    {
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branch?->id);
    }

    private function coaId(Institute $institute, string $code): int
    {
        $account = app(ChartOfAccountService::class)->accountByCode($institute->id, $code);

        return (int) $account->id;
    }

    private function warehouse(Institute $institute, string $code = 'WH1'): InventoryWarehouse
    {
        return app(InventoryItemService::class)->createWarehouse($institute->id, null, [
            'name' => 'Warehouse '.$code,
            'code' => $code,
        ]);
    }

    private function item(Institute $institute, array $overrides = []): InventoryItem
    {
        return app(InventoryItemService::class)->createItem($institute->id, null, array_merge([
            'item_type' => 'stock_item',
            'sku' => 'SKU-'.strtoupper(uniqid()),
            'name' => 'Acc Item',
        ], $overrides));
    }

    private function supplier(Institute $institute): Party
    {
        return app(PartyService::class)->create($institute->id, null, [
            'type' => 'supplier',
            'name' => 'Acc Supplier',
            'phone' => '01733'.rand(100000, 999999),
        ]);
    }

    private function customer(Institute $institute): Party
    {
        return app(PartyService::class)->create($institute->id, null, [
            'type' => 'customer',
            'name' => 'Acc Customer',
            'phone' => '01744'.rand(100000, 999999),
        ]);
    }

    // ------------------------------------------------------------ Journal shapes

    public function test_cash_purchase_credits_cash_instead_of_ap(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $warehouse = $this->warehouse($institute);
        $item = $this->item($institute);
        $supplier = $this->supplier($institute);

        ['journal' => $journal] = $this->stock()->receivePurchase(
            $institute->id,
            null,
            $supplier,
            $warehouse->id,
            [['item_id' => $item->id, 'quantity' => 5, 'unit_cost' => 40]],
            options: ['paid_immediately' => true, 'payment_method' => 'cash'],
        );

        $cashCredit = $journal->entries()->where('coa_id', $this->coaId($institute, '1001'))->sum('credit');
        $apCredit = $journal->entries()->where('coa_id', $this->coaId($institute, '2001'))->sum('credit');
        $this->assertSame(200.0, round((float) $cashCredit, 4));
        $this->assertSame(0.0, round((float) $apCredit, 4));
    }

    public function test_category_account_override_is_honoured(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $customInventory = ChartOfAccount::query()
            ->where('institute_id', $institute->id)
            ->where('code', '1200')
            ->firstOrFail();

        $category = app(InventoryItemService::class)->createCategory($institute->id, null, [
            'name' => 'Custom Cat',
            'inventory_account_id' => $customInventory->id,
        ]);
        $item = $this->item($institute, ['category_id' => $category->id]);
        $warehouse = $this->warehouse($institute, 'WH2');
        $supplier = $this->supplier($institute);

        ['journal' => $journal] = $this->stock()->receivePurchase(
            $institute->id,
            null,
            $supplier,
            $warehouse->id,
            [['item_id' => $item->id, 'quantity' => 10, 'unit_cost' => 10]],
        );

        $invDebit = $journal->entries()->where('coa_id', $customInventory->id)->sum('debit');
        $this->assertSame(100.0, round((float) $invDebit, 4));
    }

    public function test_gl_balances_after_receive_and_issue(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $warehouse = $this->warehouse($institute);
        $item = $this->item($institute);
        $supplier = $this->supplier($institute);

        $this->stock()->receivePurchase($institute->id, null, $supplier, $warehouse->id, [
            ['item_id' => $item->id, 'quantity' => 10, 'unit_cost' => 20],
        ]);
        $this->stock()->saleIssue($institute->id, null, $warehouse->id, 'invoice', 1, [
            ['item_id' => $item->id, 'quantity' => 4],
        ]);

        $rows = app(FinancialReportService::class)->trialBalance($institute->id, null);
        $this->assertSame(120.0, round((float) $rows->firstWhere('code', '1200')->balance, 4));
        $this->assertSame(80.0, round((float) $rows->firstWhere('code', '5007')->balance, 4));

        $sheet = app(FinancialReportService::class)->balanceSheet($institute->id, null);
        $this->assertSame(120.0, round((float) $sheet['assets']->firstWhere('code', '1200')->balance, 4));
    }

    // ------------------------------------------------------------ Invoice hook

    public function test_invoice_hook_issues_stock_and_posts_cogs(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        app(AccountingSetupService::class)->setSetting($institute->id, 'invoice_auto_post', true);
        $warehouse = $this->warehouse($institute);
        $item = $this->item($institute);
        $supplier = $this->supplier($institute);
        $customer = $this->customer($institute);

        $this->stock()->receivePurchase($institute->id, null, $supplier, $warehouse->id, [
            ['item_id' => $item->id, 'quantity' => 10, 'unit_cost' => 20],
        ]);

        $invoice = app(InvoiceService::class)->create($institute->id, null, [
            'party_id' => $customer->id,
            'invoice_type' => 'other',
            'warehouse_id' => $warehouse->id,
            'items' => [
                [
                    'description' => 'Sale of '.$item->name,
                    'amount' => 350,
                    'coa_id' => $this->coaId($institute, '4003'),
                    'inventory_item_id' => $item->id,
                    'quantity' => 5,
                ],
            ],
        ]);

        // Stock issued from the warehouse.
        $level = \App\Models\InventoryStockLevel::query()
            ->where('institute_id', $institute->id)
            ->where('item_id', $item->id)
            ->firstOrFail();
        $this->assertSame(5.0, (float) $level->quantity);

        // COGS journal posted against the invoice reference.
        $cogsJournal = DB::table('journals')
            ->where('institute_id', $institute->id)
            ->where('ref_type', 'invoice')
            ->where('ref_id', $invoice->id)
            ->where('type', 'adjustment')
            ->where('status', 'posted')
            ->first();
        $this->assertNotNull($cogsJournal);

        $cogsDebit = DB::table('journal_entries')
            ->where('journal_id', $cogsJournal->id)
            ->where('coa_id', $this->coaId($institute, '5007'))
            ->sum('debit');
        $this->assertSame(100.0, round((float) $cogsDebit, 4));

        // The sale journal is untouched by the inventory hook.
        $this->assertNotNull($invoice->journal_id);
    }

    public function test_non_inventory_invoice_has_no_cogs_journal(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        app(AccountingSetupService::class)->setSetting($institute->id, 'invoice_auto_post', true);
        $customer = $this->customer($institute);

        $invoice = app(InvoiceService::class)->create($institute->id, null, [
            'party_id' => $customer->id,
            'invoice_type' => 'other',
            'items' => [
                ['description' => 'Tuition', 'amount' => 500, 'coa_id' => $this->coaId($institute, '4001')],
            ],
        ]);

        $this->assertNotNull($invoice->journal_id);
        $cogsCount = DB::table('journals')
            ->where('institute_id', $institute->id)
            ->where('ref_type', 'invoice')
            ->where('ref_id', $invoice->id)
            ->where('type', 'adjustment')
            ->count();
        $this->assertSame(0, $cogsCount);

        $this->assertSame(0, \App\Models\InventoryMovement::query()->where('institute_id', $institute->id)->count());
    }

    public function test_cancelling_inventory_invoice_restocks_and_reverses_cogs(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        app(AccountingSetupService::class)->setSetting($institute->id, 'invoice_auto_post', true);
        $warehouse = $this->warehouse($institute);
        $item = $this->item($institute);
        $supplier = $this->supplier($institute);
        $customer = $this->customer($institute);

        $this->stock()->receivePurchase($institute->id, null, $supplier, $warehouse->id, [
            ['item_id' => $item->id, 'quantity' => 10, 'unit_cost' => 20],
        ]);

        $invoice = app(InvoiceService::class)->create($institute->id, null, [
            'party_id' => $customer->id,
            'invoice_type' => 'other',
            'warehouse_id' => $warehouse->id,
            'items' => [
                [
                    'description' => 'Sale of '.$item->name,
                    'amount' => 350,
                    'coa_id' => $this->coaId($institute, '4003'),
                    'inventory_item_id' => $item->id,
                    'quantity' => 5,
                ],
            ],
        ]);

        $level = \App\Models\InventoryStockLevel::query()
            ->where('institute_id', $institute->id)
            ->where('item_id', $item->id)
            ->firstOrFail();
        $this->assertSame(5.0, (float) $level->quantity);

        app(InvoiceService::class)->cancel($invoice, $institute->id);

        $this->assertSame('cancelled', $invoice->fresh()->status);
        $this->assertSame(10.0, (float) $level->fresh()->quantity);

        $reversed = DB::table('journals')
            ->where('institute_id', $institute->id)
            ->where('type', 'adjustment')
            ->whereNotNull('reversal_of')
            ->where('status', 'posted')
            ->count();
        $this->assertSame(1, $reversed);
    }

    public function test_invoice_hook_rejects_foreign_inventory_item(): void
    {
        $institute = $this->institute();
        $second = $this->institute();
        $this->setupAccounting($institute);
        $customer = $this->customer($institute);
        $foreignItem = $this->item($second);

        try {
            app(InvoiceService::class)->create($institute->id, null, [
                'party_id' => $customer->id,
                'invoice_type' => 'other',
                'items' => [
                    [
                        'description' => 'Foreign',
                        'amount' => 10,
                        'inventory_item_id' => $foreignItem->id,
                        'quantity' => 1,
                    ],
                ],
            ]);
            $this->fail('Expected foreign-item rejection.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.*.inventory_item_id', $e->errors());
        }
    }

    // ------------------------------------------------------------ Capability gating

    public function test_sales_issue_rejected_when_capability_disabled(): void
    {
        $institute = $this->institute('education');
        $this->setupAccounting($institute);
        $warehouse = $this->warehouse($institute);
        $item = $this->item($institute);

        try {
            $this->stock()->saleIssue($institute->id, null, $warehouse->id, 'invoice', 1, [
                ['item_id' => $item->id, 'quantity' => 1],
            ]);
            $this->fail('Expected capability rejection.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('not enabled', $e->errors()['capability'][0] ?? '');
        }
    }

    // ------------------------------------------------------------ Permissions

    public function test_inventory_permissions_granted_by_role(): void
    {
        $permissionIds = Permission::query()->where('module', 'inventory')->pluck('id');

        $owner = Role::query()->where('slug', 'institute-owner')->firstOrFail();
        $manager = Role::query()->where('slug', 'branch-manager')->firstOrFail();
        $receptionist = Role::query()->where('slug', 'receptionist')->firstOrFail();

        $granted = fn (Role $role) => DB::table('role_permissions')
            ->where('role_id', $role->id)
            ->whereIn('permission_id', $permissionIds)
            ->count();

        $this->assertSame(9, $granted($owner));

        // branch-manager: no approve/post
        $managerSlugs = Permission::query()
            ->whereIn('id', DB::table('role_permissions')->where('role_id', $manager->id)->whereIn('permission_id', $permissionIds)->pluck('permission_id'))
            ->pluck('slug')
            ->all();
        $this->assertNotContains('inventory.approve', $managerSlugs);
        $this->assertNotContains('inventory.post', $managerSlugs);
        $this->assertContains('inventory.adjust', $managerSlugs);

        // receptionist: view + create only
        $receptionistSlugs = Permission::query()
            ->whereIn('id', DB::table('role_permissions')->where('role_id', $receptionist->id)->whereIn('permission_id', $permissionIds)->pluck('permission_id'))
            ->pluck('slug')
            ->all();
        $this->assertSame(['inventory.view', 'inventory.create'], $receptionistSlugs);
    }
}