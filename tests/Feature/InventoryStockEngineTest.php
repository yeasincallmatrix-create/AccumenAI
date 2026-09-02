<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Country;
use App\Models\Institute;
use App\Models\InventoryBatch;
use App\Models\InventoryCount;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventorySerialNumber;
use App\Models\InventoryStockLevel;
use App\Models\InventoryWarehouse;
use App\Models\Party;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Accounting\PartyService;
use App\Services\Inventory\InventoryItemService;
use App\Services\Inventory\InventoryReconciliationService;
use App\Services\Inventory\InventoryStockService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * STEP 16 — Inventory stock engine.
 *
 * inventory_movements is the source of truth and inventory_stock_levels the
 * rebuildable cached balance; every mutation is transactional, row-locked and
 * weighed by weighted-average cost. The engine enforces negative-stock policy,
 * batch/serial tracking, transfers (no journal), controlled adjustments,
 * physical counts and returns (which reverse the financial journal).
 */
class InventoryStockEngineTest extends TestCase
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
            'name' => 'Stock Inst',
            'slug' => str()->slug('Stock Inst-'.uniqid()),
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

    private function warehouse(Institute $institute, string $code = 'WH1', ?Branch $branch = null): InventoryWarehouse
    {
        return app(InventoryItemService::class)->createWarehouse($institute->id, $branch?->id, [
            'name' => 'Warehouse '.$code,
            'code' => $code,
        ]);
    }

    private function item(Institute $institute, array $overrides = [], ?Branch $branch = null): InventoryItem
    {
        return app(InventoryItemService::class)->createItem($institute->id, $branch?->id, array_merge([
            'item_type' => 'stock_item',
            'sku' => 'SKU-'.strtoupper(uniqid()),
            'name' => 'Test Item',
        ], $overrides));
    }

    private function supplier(Institute $institute, ?Branch $branch = null): Party
    {
        return app(PartyService::class)->create($institute->id, $branch?->id, [
            'type' => 'supplier',
            'name' => 'Stock Supplier',
            'phone' => '01711'.rand(100000, 999999),
        ]);
    }

    private function customer(Institute $institute, ?Branch $branch = null): Party
    {
        return app(PartyService::class)->create($institute->id, $branch?->id, [
            'type' => 'customer',
            'name' => 'Stock Customer',
            'phone' => '01722'.rand(100000, 999999),
        ]);
    }

    private function level(InventoryWarehouse $warehouse, InventoryItem $item, ?int $batchId = null): InventoryStockLevel
    {
        return InventoryStockLevel::query()
            ->where('institute_id', $warehouse->institute_id)
            ->where('warehouse_id', $warehouse->id)
            ->where('item_id', $item->id)
            ->where('batch_id', $batchId)
            ->firstOrFail();
    }

    // ------------------------------------------------------------ Receive / Issue

    public function test_receive_increases_stock_and_posts_ap_journal(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $warehouse = $this->warehouse($institute);
        $item = $this->item($institute);
        $supplier = $this->supplier($institute);

        ['movements' => $movements, 'journal' => $journal] = $this->stock()->receivePurchase(
            $institute->id,
            null,
            $supplier,
            $warehouse->id,
            [['item_id' => $item->id, 'quantity' => 10, 'unit_cost' => 25]],
        );

        $this->assertCount(1, $movements);
        $this->assertSame('receipt', $movements[0]->movement_type);
        $this->assertSame(10.0, (float) $movements[0]->quantity);
        $this->assertSame(10.0, (float) $this->level($warehouse, $item)->quantity);
        $this->assertSame(25.0, (float) $this->level($warehouse, $item)->avg_cost);

        $invDebit = $journal->entries()->where('coa_id', $this->coaId($institute, '1200'))->sum('debit');
        $apCredit = $journal->entries()->where('coa_id', $this->coaId($institute, '2001'))->sum('credit');
        $this->assertSame(250.0, round((float) $invDebit, 4));
        $this->assertSame(250.0, round((float) $apCredit, 4));
    }

    public function test_weighted_average_cost_updates_across_receipts(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $warehouse = $this->warehouse($institute);
        $item = $this->item($institute);
        $supplier = $this->supplier($institute);

        $this->stock()->receivePurchase($institute->id, null, $supplier, $warehouse->id, [
            ['item_id' => $item->id, 'quantity' => 10, 'unit_cost' => 10],
        ]);

        $this->stock()->receivePurchase($institute->id, null, $supplier, $warehouse->id, [
            ['item_id' => $item->id, 'quantity' => 10, 'unit_cost' => 20],
        ]);

        $this->assertSame(20.0, (float) $this->level($warehouse, $item)->quantity);
        $this->assertSame(15.0, (float) $this->level($warehouse, $item)->avg_cost);
    }

    public function test_issue_decreases_stock_and_posts_cogs_journal(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $warehouse = $this->warehouse($institute);
        $item = $this->item($institute);
        $supplier = $this->supplier($institute);

        $this->stock()->receivePurchase($institute->id, null, $supplier, $warehouse->id, [
            ['item_id' => $item->id, 'quantity' => 10, 'unit_cost' => 20],
        ]);

        ['journal' => $journal] = $this->stock()->saleIssue(
            $institute->id,
            null,
            $warehouse->id,
            'invoice',
            1,
            [['item_id' => $item->id, 'quantity' => 4]],
        );

        $this->assertSame(6.0, (float) $this->level($warehouse, $item)->quantity);

        $cogsDebit = $journal->entries()->where('coa_id', $this->coaId($institute, '5007'))->sum('debit');
        $invCredit = $journal->entries()->where('coa_id', $this->coaId($institute, '1200'))->sum('credit');
        $this->assertSame(80.0, round((float) $cogsDebit, 4));
        $this->assertSame(80.0, round((float) $invCredit, 4));
    }

    public function test_insufficient_stock_rejected_by_default(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $warehouse = $this->warehouse($institute);
        $item = $this->item($institute);
        $supplier = $this->supplier($institute);

        $this->stock()->receivePurchase($institute->id, null, $supplier, $warehouse->id, [
            ['item_id' => $item->id, 'quantity' => 3, 'unit_cost' => 10],
        ]);

        try {
            $this->stock()->saleIssue($institute->id, null, $warehouse->id, 'invoice', 1, [
                ['item_id' => $item->id, 'quantity' => 5],
            ]);
            $this->fail('Expected insufficient-stock ValidationException.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Insufficient stock', $e->errors()['quantity'][0] ?? '');
        }
    }

    public function test_allow_negative_stock_override_permits_issue(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        app(\App\Services\Accounting\AccountingSetupService::class)->setSetting(
            $institute->id,
            'inventory.allow_negative_stock',
            true,
        );
        $warehouse = $this->warehouse($institute);
        $item = $this->item($institute);

        $this->stock()->saleIssue($institute->id, null, $warehouse->id, 'invoice', 1, [
            ['item_id' => $item->id, 'quantity' => 5, 'unit_cost' => 10],
        ]);

        $this->assertSame(-5.0, (float) $this->level($warehouse, $item)->quantity);
    }

    // ------------------------------------------------------------ Transfers

    public function test_transfer_moves_stock_between_warehouses_without_journal(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $source = $this->warehouse($institute, 'SRC');
        $destination = $this->warehouse($institute, 'DST');
        $item = $this->item($institute);
        $supplier = $this->supplier($institute);

        $this->stock()->receivePurchase($institute->id, null, $supplier, $source->id, [
            ['item_id' => $item->id, 'quantity' => 10, 'unit_cost' => 15],
        ]);

        $transfer = $this->stock()->transfer($institute->id, null, $source->id, $destination->id, [
            ['item_id' => $item->id, 'quantity' => 6],
        ]);

        $this->assertSame('posted', $transfer->status);
        $this->assertCount(1, $transfer->items);
        $this->assertSame(4.0, (float) $this->level($source, $item)->quantity);
        $this->assertSame(6.0, (float) $this->level($destination, $item)->quantity);
        $this->assertSame(15.0, (float) $this->level($destination, $item)->avg_cost);

        $journalLinked = InventoryMovement::query()
            ->where('reference_type', 'inventory_transfer')
            ->where('reference_id', $transfer->id)
            ->whereNotNull('journal_id')
            ->count();
        $this->assertSame(0, $journalLinked);
    }

    public function test_transfer_to_same_warehouse_rejected(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $warehouse = $this->warehouse($institute);

        try {
            $this->stock()->transfer($institute->id, null, $warehouse->id, $warehouse->id, [
                ['item_id' => 1, 'quantity' => 1],
            ]);
            $this->fail('Expected same-warehouse rejection.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('destination_warehouse_id', $e->errors());
        }
    }

    // ------------------------------------------------------------ Adjustments / Count

    public function test_adjustment_surplus_and_deficit_change_stock(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $warehouse = $this->warehouse($institute);
        $item = $this->item($institute);
        $supplier = $this->supplier($institute);

        $this->stock()->receivePurchase($institute->id, null, $supplier, $warehouse->id, [
            ['item_id' => $item->id, 'quantity' => 10, 'unit_cost' => 20],
        ]);

        // Physical count found 12 -> surplus of 2 (Dr Inventory / Cr 4005)
        ['adjustment' => $surplus, 'journal' => $surplusJournal] = $this->stock()->postAdjustment(
            $institute->id,
            null,
            $warehouse->id,
            'adjustment',
            'Count gain',
            [['item_id' => $item->id, 'system_qty' => 10, 'counted_qty' => 12]],
        );

        $this->assertSame(12.0, (float) $this->level($warehouse, $item)->quantity);
        $this->assertSame(2.0, (float) $surplus->items->first()->difference);
        $incomeCredit = $surplusJournal->entries()->where('coa_id', $this->coaId($institute, '4005'))->sum('credit');
        $this->assertSame(40.0, round((float) $incomeCredit, 4));

        // Physical count found 9 -> deficit of 3 (Dr 5008 / Cr Inventory)
        ['adjustment' => $deficit, 'journal' => $deficitJournal] = $this->stock()->postAdjustment(
            $institute->id,
            null,
            $warehouse->id,
            'adjustment',
            'Count loss',
            [['item_id' => $item->id, 'system_qty' => 12, 'counted_qty' => 9]],
        );

        $this->assertSame(9.0, (float) $this->level($warehouse, $item)->quantity);
        $this->assertSame(-3.0, (float) $deficit->items->first()->difference);
        $lossDebit = $deficitJournal->entries()->where('coa_id', $this->coaId($institute, '5008'))->sum('debit');
        $this->assertSame(60.0, round((float) $lossDebit, 4));
    }

    public function test_wastage_posts_to_wastage_account(): void
    {
        $institute = $this->institute('retail');
        $this->setupAccounting($institute);
        app(\App\Services\Inventory\InventoryCapabilityService::class)->set($institute->id, 'inventory.wastage', true);
        $warehouse = $this->warehouse($institute);
        $item = $this->item($institute);
        $supplier = $this->supplier($institute);

        $this->stock()->receivePurchase($institute->id, null, $supplier, $warehouse->id, [
            ['item_id' => $item->id, 'quantity' => 5, 'unit_cost' => 10],
        ]);

        ['journal' => $journal] = $this->stock()->postAdjustment(
            $institute->id,
            null,
            $warehouse->id,
            'wastage',
            'Damaged',
            [['item_id' => $item->id, 'system_qty' => 5, 'counted_qty' => 4]],
        );

        $this->assertSame(4.0, (float) $this->level($warehouse, $item)->quantity);
        $wastageDebit = $journal->entries()->where('coa_id', $this->coaId($institute, '5009'))->sum('debit');
        $this->assertSame(10.0, round((float) $wastageDebit, 4));
    }

    public function test_count_workflow_requires_approved_and_cannot_be_posted_twice(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $warehouse = $this->warehouse($institute);
        $item = $this->item($institute);
        $supplier = $this->supplier($institute);

        $this->stock()->receivePurchase($institute->id, null, $supplier, $warehouse->id, [
            ['item_id' => $item->id, 'quantity' => 10, 'unit_cost' => 10],
        ]);

        $draft = InventoryCount::create([
            'institute_id' => $institute->id,
            'branch_id' => null,
            'warehouse_id' => $warehouse->id,
            'count_no' => 'IVC-TEST-1',
            'status' => 'draft',
        ]);
        $draft->items()->create([
            'institute_id' => $institute->id,
            'branch_id' => null,
            'item_id' => $item->id,
            'system_qty' => 10,
            'counted_qty' => 8,
            'difference' => -2,
        ]);

        try {
            $this->stock()->postCount($institute->id, null, $draft->id);
            $this->fail('Expected draft-count rejection.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $draft->forceFill(['status' => 'approved'])->save();

        ['count' => $posted] = $this->stock()->postCount($institute->id, null, $draft->id);
        $this->assertSame('posted', $posted->status);
        $this->assertSame(8.0, (float) $this->level($warehouse, $item)->quantity);

        try {
            $this->stock()->postCount($institute->id, null, $draft->id);
            $this->fail('Expected double-post rejection.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
    }

    // ------------------------------------------------------------ Batches / Serials

    public function test_batch_created_on_receipt_for_medicine_item(): void
    {
        $institute = $this->institute('healthcare');
        $this->setupAccounting($institute);
        $warehouse = $this->warehouse($institute);
        $item = $this->item($institute, ['item_type' => 'medicine', 'sku' => 'MED-'.uniqid()]);
        $supplier = $this->supplier($institute);

        $this->stock()->receivePurchase($institute->id, null, $supplier, $warehouse->id, [
            ['item_id' => $item->id, 'quantity' => 50, 'unit_cost' => 2, 'batch_number' => 'BATCH-01', 'expiry_date' => '2027-12-31'],
        ]);

        $batch = InventoryBatch::query()
            ->where('institute_id', $institute->id)
            ->where('item_id', $item->id)
            ->where('batch_number', 'BATCH-01')
            ->firstOrFail();

        $this->assertSame(50.0, (float) $batch->quantity);
        $this->assertSame(50.0, (float) $this->level($warehouse, $item, $batch->id)->quantity);
    }

    public function test_serial_numbers_flow_through_receipt_and_issue(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $warehouse = $this->warehouse($institute);
        $item = $this->item($institute);
        $supplier = $this->supplier($institute);

        $this->stock()->receivePurchase($institute->id, null, $supplier, $warehouse->id, [
            ['item_id' => $item->id, 'quantity' => 2, 'unit_cost' => 100, 'serials' => ['SN-1', 'SN-2']],
        ]);

        $serial = InventorySerialNumber::query()->where('serial_number', 'SN-1')->firstOrFail();
        $this->assertSame('in_stock', $serial->status);

        $this->stock()->saleIssue($institute->id, null, $warehouse->id, 'invoice', 1, [
            ['item_id' => $item->id, 'quantity' => 1, 'serials' => ['SN-1']],
        ]);

        $this->assertSame('sold', $serial->fresh()->status);

        try {
            $this->stock()->saleIssue($institute->id, null, $warehouse->id, 'invoice', 2, [
                ['item_id' => $item->id, 'quantity' => 1, 'serials' => ['SN-1']],
            ]);
            $this->fail('Expected sold-serial rejection.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('serials', $e->errors());
        }
    }

    // ------------------------------------------------------------ Returns

    public function test_return_restocks_and_reverses_cogs_journal(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $warehouse = $this->warehouse($institute);
        $item = $this->item($institute);
        $supplier = $this->supplier($institute);

        $this->stock()->receivePurchase($institute->id, null, $supplier, $warehouse->id, [
            ['item_id' => $item->id, 'quantity' => 10, 'unit_cost' => 20],
        ]);

        $this->stock()->saleIssue($institute->id, null, $warehouse->id, 'invoice', 55, [
            ['item_id' => $item->id, 'quantity' => 4],
        ]);

        $this->assertSame(6.0, (float) $this->level($warehouse, $item)->quantity);

        ['journal' => $reversal] = $this->stock()->returnStock(
            $institute->id,
            null,
            $warehouse->id,
            'in',
            'invoice',
            55,
            [['item_id' => $item->id, 'quantity' => 4]],
        );

        $this->assertNotNull($reversal);
        $this->assertNotNull($reversal->reversal_of);
        $this->assertSame('posted', $reversal->status);
        $this->assertSame(10.0, (float) $this->level($warehouse, $item)->quantity);

        // A second return of the same reference is a no-op (idempotent).
        ['movements' => $second] = $this->stock()->returnForReference(
            $institute->id,
            null,
            'in',
            'invoice',
            55,
        );
        $this->assertSame([], $second);
        $this->assertSame(10.0, (float) $this->level($warehouse, $item)->quantity);
    }

    // ------------------------------------------------------------ Reconciliation

    public function test_reconciliation_rebuilds_tampered_stock_levels(): void
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
            ['item_id' => $item->id, 'quantity' => 3],
        ]);

        $this->assertSame(7.0, (float) $this->level($warehouse, $item)->quantity);

        $this->level($warehouse, $item)->forceFill(['quantity' => 99])->save();

        $report = app(InventoryReconciliationService::class)->reconcile($institute->id, null);

        $this->assertSame(1, $report['checked']);
        $this->assertSame(1, $report['discrepancies']);
        $this->assertSame(1, $report['rebuilt']);
        $this->assertSame(7.0, (float) $this->level($warehouse, $item)->fresh()->quantity);
    }

    public function test_clean_ledger_reconciles_without_drift(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $warehouse = $this->warehouse($institute);
        $item = $this->item($institute);
        $supplier = $this->supplier($institute);

        $this->stock()->receivePurchase($institute->id, null, $supplier, $warehouse->id, [
            ['item_id' => $item->id, 'quantity' => 10, 'unit_cost' => 20],
        ]);

        $report = app(InventoryReconciliationService::class)->reconcile($institute->id, null);

        $this->assertSame(1, $report['checked']);
        $this->assertSame(0, $report['discrepancies']);
        $this->assertSame(0, $report['rebuilt']);
    }
}