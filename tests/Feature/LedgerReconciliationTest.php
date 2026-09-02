<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Institute;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\AccountingReportService;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\JournalPostingService;
use App\Services\Accounting\LedgerReconciliationService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * STEP 70 — Ledger Reconciliation Tests.
 *
 * Validates: debit transaction, credit transaction, opening balance,
 * branch isolation, tenant isolation.
 */
class LedgerReconciliationTest extends \Tests\TestCase
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
            'name' => 'Ledger Owner',
            'first_name' => 'Ledger',
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
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => $entries,
        ]);
    }

    // ─── Test 1: Debit transaction ─────────────────────────────────
    public function test_debit_transaction_reconciles(): void
    {
        $mawa = $this->institute('Ledger Dr');
        $this->setupAccounting($mawa);
        $owner = $this->owner('ledger-dr@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $cash = $this->coa($mawa, '1001');
        $revenue = $this->coa($mawa, '4001');

        $this->postJournal($mawa, null, '2026-10-01', [
            ['coa_id' => $cash->id, 'debit' => 5000, 'credit' => 0],
            ['coa_id' => $revenue->id, 'debit' => 0, 'credit' => 5000],
        ]);

        $recon = app(LedgerReconciliationService::class);
        $mismatches = $recon->reconcile($mawa->id, null, '2026-10-31');

        $this->assertEmpty($mismatches, 'All accounts should reconcile after debit transaction');
    }

    // ─── Test 2: Credit transaction ────────────────────────────────
    public function test_credit_transaction_reconciles(): void
    {
        $mawa = $this->institute('Ledger Cr');
        $this->setupAccounting($mawa);
        $owner = $this->owner('ledger-cr@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $ar = $this->coa($mawa, '1100');
        $revenue = $this->coa($mawa, '4001');

        $this->postJournal($mawa, null, '2026-10-01', [
            ['coa_id' => $ar->id, 'debit' => 8000, 'credit' => 0],
            ['coa_id' => $revenue->id, 'debit' => 0, 'credit' => 8000],
        ]);

        $recon = app(LedgerReconciliationService::class);
        $mismatches = $recon->reconcile($mawa->id, null, '2026-10-31');

        $this->assertEmpty($mismatches, 'All accounts should reconcile after credit transaction');
    }

    // ─── Test 3: Opening balance ───────────────────────────────────
    public function test_opening_balance_reconciles(): void
    {
        $mawa = $this->institute('Ledger OB');
        $this->setupAccounting($mawa);
        $owner = $this->owner('ledger-ob@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $cash = $this->coa($mawa, '1001');
        $revenue = $this->coa($mawa, '4001');

        // Opening balance: DR Cash 10000, CR Revenue 10000
        $this->postJournal($mawa, null, '2026-09-30', [
            ['coa_id' => $cash->id, 'debit' => 10000, 'credit' => 0],
            ['coa_id' => $revenue->id, 'debit' => 0, 'credit' => 10000],
        ]);

        // Subsequent transaction
        $expense = $this->coa($mawa, '5001');
        $this->postJournal($mawa, null, '2026-10-05', [
            ['coa_id' => $expense->id, 'debit' => 2000, 'credit' => 0],
            ['coa_id' => $cash->id, 'debit' => 0, 'credit' => 2000],
        ]);

        $recon = app(LedgerReconciliationService::class);
        $mismatches = $recon->reconcile($mawa->id, null, '2026-10-31');

        $this->assertEmpty($mismatches, 'All accounts should reconcile with opening balance + transactions');
    }

    // ─── Test 4: Branch isolation ──────────────────────────────────
    public function test_branch_isolation(): void
    {
        $mawa = $this->institute('Ledger Branch');
        $branchA = Branch::create(['institute_id' => $mawa->id, 'name' => 'Branch A', 'status' => 'active']);
        $branchB = Branch::create(['institute_id' => $mawa->id, 'name' => 'Branch B', 'status' => 'active']);
        $this->setupAccounting($mawa, $branchA->id);
        $this->setupAccounting($mawa, $branchB->id);
        $owner = $this->owner('ledger-branch@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $cashA = ChartOfAccount::withoutGlobalScopes()
            ->where('institute_id', $mawa->id)
            ->where('branch_id', $branchA->id)
            ->where('code', '1001')
            ->firstOrFail();
        $revenueA = ChartOfAccount::withoutGlobalScopes()
            ->where('institute_id', $mawa->id)
            ->where('branch_id', $branchA->id)
            ->where('code', '4001')
            ->firstOrFail();

        $this->postJournal($mawa, $branchA->id, '2026-10-01', [
            ['coa_id' => $cashA->id, 'debit' => 5000, 'credit' => 0],
            ['coa_id' => $revenueA->id, 'debit' => 0, 'credit' => 5000],
        ]);

        $reports = app(AccountingReportService::class);

        // Branch A: cash balance = 5000
        $cfA = $reports->cashBankSummary($mawa->id, $branchA->id, '2026-10-31');
        $this->assertEqualsWithDelta(5000.0, (float) $cfA->sum('balance'), 0.001);

        // Branch B: no transactions, balance = 0
        $cfB = $reports->cashBankSummary($mawa->id, $branchB->id, '2026-10-31');
        $this->assertEqualsWithDelta(0.0, (float) $cfB->sum('balance'), 0.001);

        // Both branches reconcile independently
        $recon = app(LedgerReconciliationService::class);
        $this->assertEmpty($recon->reconcile($mawa->id, $branchA->id, '2026-10-31'));
        $this->assertEmpty($recon->reconcile($mawa->id, $branchB->id, '2026-10-31'));
    }

    // ─── Test 5: Tenant isolation ──────────────────────────────────
    public function test_tenant_isolation(): void
    {
        $mawa = $this->institute('Tenant A');
        $other = $this->institute('Tenant B');
        $this->setupAccounting($mawa);
        $this->setupAccounting($other);

        $ownerMawa = $this->owner('tenant-a@example.test');
        $ownerOther = $this->owner('tenant-b@example.test');
        (new MembershipService)->assign($ownerMawa, $mawa->id, $this->roleId('institute-owner'));
        (new MembershipService)->assign($ownerOther, $other->id, $this->roleId('institute-owner'));

        $cash = $this->coa($mawa, '1001');
        $revenue = $this->coa($mawa, '4001');

        // Transaction in Tenant A
        $this->postJournal($mawa, null, '2026-10-01', [
            ['coa_id' => $cash->id, 'debit' => 7500, 'credit' => 0],
            ['coa_id' => $revenue->id, 'debit' => 0, 'credit' => 7500],
        ]);

        // Tenant A reconciles
        $recon = app(LedgerReconciliationService::class);
        $this->assertEmpty($recon->reconcile($mawa->id, null, '2026-10-31'));

        // Tenant B has no transactions, should also reconcile (all zero)
        $this->assertEmpty($recon->reconcile($other->id, null, '2026-10-31'));

        // Verify Tenant B does NOT see Tenant A's transactions
        $reports = app(AccountingReportService::class);
        $tbB = $reports->trialBalance($other->id, null, '2026-10-31');
        $cashRowB = $tbB->firstWhere('code', '1001');
        if ($cashRowB !== null) {
            $this->assertEqualsWithDelta(0.0, (float) $cashRowB->debit, 0.001);
        }
        // Tenant B ledger should have no entries at all
        $glB = $reports->generalLedger($other->id, null, null, null, '2026-10-31');
        $this->assertTrue($glB->isEmpty(), 'Tenant B should have no general ledger entries');
    }
}
