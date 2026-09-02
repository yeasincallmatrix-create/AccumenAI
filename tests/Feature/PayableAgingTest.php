<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Institute;
use App\Models\Party;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\JournalPostingService;
use App\Services\Accounting\PartyService;
use App\Services\Accounting\PayableService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * STEP 73 — Payable Aging Tests.
 */
class PayableAgingTest extends \Tests\TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();
        BranchContext::clear();
        Workspace::clear();
    }

    protected function owner(string $email): User
    {
        return (new UserAccountService)->registerOwner([
            'name' => 'AP Owner',
            'first_name' => 'AP',
            'last_name' => 'Owner',
            'email' => $email,
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);
    }

    protected function institute(string $name): Institute
    {
        return Institute::create([
            'name' => $name.' '.uniqid(),
            'slug' => \Illuminate\Support\Str::slug($name.' '.uniqid()),
            'status' => 'active',
        ]);
    }

    protected function roleId(string $slug): int
    {
        return Role::where('slug', $slug)->firstOrFail()->id;
    }

    protected function setupAccounting(Institute $institute, ?int $branchId = null): void
    {
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branchId);
    }

    protected function asUser(User $user, int $workspaceId): static
    {
        return $this->withSession([Workspace::SESSION_KEY => $workspaceId])->actingAs($user, 'web');
    }

    protected function coa(Institute $institute, string $code): ChartOfAccount
    {
        return ChartOfAccount::withoutGlobalScopes()
            ->where('institute_id', $institute->id)
            ->where('code', $code)
            ->firstOrFail();
    }

    protected function currencyId(): int
    {
        return (int) (Currency::query()->where('code', 'BDT')->value('id') ?? Currency::query()->orderBy('code')->value('id'));
    }

    protected function postJournal(Institute $institute, ?int $branchId, string $date, array $entries): void
    {
        app(JournalPostingService::class)->create([
            'institute_id' => $institute->id,
            'branch_id' => $branchId,
            'journal_date' => $date,
            'type' => 'purchase',
            'currency_id' => $this->currencyId(),
            'entries' => $entries,
        ]);
    }

    protected function createSupplier(Institute $institute, string $name, ?int $branchId = null): Party
    {
        return app(PartyService::class)->create($institute->id, $branchId, [
            'name' => $name,
            'type' => 'supplier',
        ]);
    }

    // ─── Test 1: Supplier with outstanding balance ────────────────
    public function test_supplier_statement_shows_balance(): void
    {
        $mawa = $this->institute('AP Balance');
        $this->setupAccounting($mawa);
        $owner = $this->owner('ap-balance@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $supplier = $this->createSupplier($mawa, 'Vendor Alpha');
        $ap = $this->coa($mawa, '2001');
        $expense = $this->coa($mawa, '5001');

        $this->postJournal($mawa, null, '2026-12-01', [
            ['coa_id' => $expense->id, 'debit' => 15000, 'credit' => 0],
            ['coa_id' => $ap->id, 'debit' => 0, 'credit' => 15000, 'party_id' => $supplier->id],
        ]);

        $svc = app(PayableService::class);
        $stmt = $svc->supplierStatement($mawa->id, $supplier->id, '2026-12-31');

        $this->assertSame(15000.0, $stmt['balance']);
        $this->assertTrue($stmt['transactions']->isNotEmpty());
    }

    // ─── Test 2: Aging buckets ───────────────────────────────────
    public function test_payables_aging_buckets(): void
    {
        $mawa = $this->institute('AP Aging');
        $this->setupAccounting($mawa);
        $owner = $this->owner('ap-aging@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $supplier = $this->createSupplier($mawa, 'Vendor Beta');
        $ap = $this->coa($mawa, '2001');
        $expense = $this->coa($mawa, '5001');

        $this->postJournal($mawa, null, '2026-11-01', [
            ['coa_id' => $expense->id, 'debit' => 8000, 'credit' => 0],
            ['coa_id' => $ap->id, 'debit' => 0, 'credit' => 8000, 'party_id' => $supplier->id],
        ]);

        $svc = app(PayableService::class);
        $result = $svc->payablesAging($mawa->id, null, '2026-12-11');

        $this->assertArrayHasKey('suppliers', $result);
        $this->assertArrayHasKey('totals', $result);
    }

    // ─── Test 3: Empty supplier has zero balance ──────────────────
    public function test_empty_supplier_zero_balance(): void
    {
        $mawa = $this->institute('AP Empty');
        $this->setupAccounting($mawa);
        $owner = $this->owner('ap-empty@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $supplier = $this->createSupplier($mawa, 'Vendor Gamma');

        $svc = app(PayableService::class);
        $stmt = $svc->supplierStatement($mawa->id, $supplier->id);

        $this->assertSame(0.0, $stmt['balance']);
        $this->assertTrue($stmt['transactions']->isEmpty());
    }

    // ─── Test 4: Branch isolation ─────────────────────────────────
    public function test_branch_isolation(): void
    {
        $mawa = $this->institute('AP Branch');
        $branchA = Branch::create(['institute_id' => $mawa->id, 'name' => 'BA', 'status' => 'active']);
        $this->setupAccounting($mawa, $branchA->id);
        $owner = $this->owner('ap-branch@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $supplier = $this->createSupplier($mawa, 'Vendor Delta', $branchA->id);
        $ap = ChartOfAccount::withoutGlobalScopes()
            ->where('institute_id', $mawa->id)
            ->where('branch_id', $branchA->id)
            ->where('code', '2001')
            ->firstOrFail();
        $expense = ChartOfAccount::withoutGlobalScopes()
            ->where('institute_id', $mawa->id)
            ->where('branch_id', $branchA->id)
            ->where('code', '5001')
            ->firstOrFail();

        $this->postJournal($mawa, $branchA->id, '2026-12-01', [
            ['coa_id' => $expense->id, 'debit' => 7500, 'credit' => 0],
            ['coa_id' => $ap->id, 'debit' => 0, 'credit' => 7500, 'party_id' => $supplier->id],
        ]);

        $svc = app(PayableService::class);
        $stmt = $svc->supplierStatement($mawa->id, $supplier->id, '2026-12-31');
        $this->assertSame(7500.0, $stmt['balance']);
    }

    // ─── Test 5: Tenant isolation ─────────────────────────────────
    public function test_tenant_isolation(): void
    {
        $mawa = $this->institute('AP Tenant A');
        $other = $this->institute('AP Tenant B');
        $this->setupAccounting($mawa);
        $this->setupAccounting($other);

        $ownerA = $this->owner('ap-tenanta@example.test');
        $ownerB = $this->owner('ap-tenantb@example.test');
        (new MembershipService)->assign($ownerA, $mawa->id, $this->roleId('institute-owner'));
        (new MembershipService)->assign($ownerB, $other->id, $this->roleId('institute-owner'));

        $supplierA = $this->createSupplier($mawa, 'Vendor Epsilon');
        $ap = $this->coa($mawa, '2001');
        $expense = $this->coa($mawa, '5001');

        $this->postJournal($mawa, null, '2026-12-01', [
            ['coa_id' => $expense->id, 'debit' => 3000, 'credit' => 0],
            ['coa_id' => $ap->id, 'debit' => 0, 'credit' => 3000, 'party_id' => $supplierA->id],
        ]);

        $supplierB = $this->createSupplier($other, 'Vendor Zeta');

        $svc = app(PayableService::class);

        $stmtA = $svc->supplierStatement($mawa->id, $supplierA->id, '2026-12-31');
        $this->assertSame(3000.0, $stmtA['balance']);

        $stmtB = $svc->supplierStatement($other->id, $supplierB->id, '2026-12-31');
        $this->assertSame(0.0, $stmtB['balance']);
    }
}
