<?php

namespace Tests\Feature;

use App\Models\BankReconciliation;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Institute;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\BankReconciliationService;
use App\Services\Accounting\JournalPostingService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * STEP 71 — Bank Reconciliation Tests.
 */
class BankReconciliationTest extends \Tests\TestCase
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
            'name' => 'Bank Owner',
            'first_name' => 'Bank',
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

    protected function postJournal(Institute $institute, ?int $branchId, string $date, array $entries, ?string $journalNo = null): \App\Models\Journal
    {
        return app(JournalPostingService::class)->create([
            'institute_id' => $institute->id,
            'branch_id' => $branchId,
            'journal_date' => $date,
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => $entries,
        ]);
    }

    // ─── Test 1: Auto match by reference ──────────────────────────
    public function test_auto_match_by_reference(): void
    {
        $mawa = $this->institute('Bank Ref');
        $this->setupAccounting($mawa);
        $owner = $this->owner('bank-ref@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $bank = $this->coa($mawa, '1002');
        $revenue = $this->coa($mawa, '4001');

        $journal = $this->postJournal($mawa, null, '2026-11-01', [
            ['coa_id' => $bank->id, 'debit' => 5000, 'credit' => 0],
            ['coa_id' => $revenue->id, 'debit' => 0, 'credit' => 5000],
        ]);

        $statement = BankStatement::create([
            'institute_id' => $mawa->id,
            'bank_account_id' => $bank->id,
            'statement_date' => '2026-11-01',
            'status' => 'imported',
        ]);

        $line = BankStatementLine::create([
            'statement_id' => $statement->id,
            'institute_id' => $mawa->id,
            'transaction_date' => '2026-11-01',
            'description' => 'Revenue deposit',
            'reference' => $journal->journal_no,
            'amount' => 5000,
            'type' => 'deposit',
        ]);

        $svc = app(BankReconciliationService::class);
        $result = $svc->autoMatch($statement, $owner->id);

        $this->assertSame(1, $result['matched']);
        $this->assertSame(0, $result['unmatched']);

        $recon = BankReconciliation::where('statement_line_id', $line->id)->first();
        $this->assertNotNull($recon);
        $this->assertSame('matched', $recon->status);
        $this->assertSame($journal->id, $recon->journal_id);
    }

    // ─── Test 2: Auto match by amount ─────────────────────────────
    public function test_auto_match_by_amount(): void
    {
        $mawa = $this->institute('Bank Amt');
        $this->setupAccounting($mawa);
        $owner = $this->owner('bank-amt@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $bank = $this->coa($mawa, '1002');
        $revenue = $this->coa($mawa, '4001');

        $journal = $this->postJournal($mawa, null, '2026-11-01', [
            ['coa_id' => $bank->id, 'debit' => 7500, 'credit' => 0],
            ['coa_id' => $revenue->id, 'debit' => 0, 'credit' => 7500],
        ]);

        $statement = BankStatement::create([
            'institute_id' => $mawa->id,
            'bank_account_id' => $bank->id,
            'statement_date' => '2026-11-01',
            'status' => 'imported',
        ]);

        $line = BankStatementLine::create([
            'statement_id' => $statement->id,
            'institute_id' => $mawa->id,
            'transaction_date' => '2026-11-01',
            'description' => 'Deposit',
            'reference' => null,
            'amount' => 7500,
            'type' => 'deposit',
        ]);

        $svc = app(BankReconciliationService::class);
        $result = $svc->autoMatch($statement, $owner->id);

        $this->assertSame(1, $result['matched']);
    }

    // ─── Test 3: No match (unmatched) ────────────────────────────
    public function test_unmatched_line(): void
    {
        $mawa = $this->institute('Bank Unmatch');
        $this->setupAccounting($mawa);
        $owner = $this->owner('bank-unmatch@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $bank = $this->coa($mawa, '1002');

        $statement = BankStatement::create([
            'institute_id' => $mawa->id,
            'bank_account_id' => $bank->id,
            'statement_date' => '2026-11-01',
            'status' => 'imported',
        ]);

        $line = BankStatementLine::create([
            'statement_id' => $statement->id,
            'institute_id' => $mawa->id,
            'transaction_date' => '2026-11-01',
            'description' => 'Mystery deposit',
            'reference' => null,
            'amount' => 9999,
            'type' => 'deposit',
        ]);

        $svc = app(BankReconciliationService::class);
        $result = $svc->autoMatch($statement, $owner->id);

        $this->assertSame(0, $result['matched']);
        $this->assertSame(1, $result['unmatched']);
    }

    // ─── Test 4: Summary ─────────────────────────────────────────
    public function test_summary(): void
    {
        $mawa = $this->institute('Bank Sum');
        $this->setupAccounting($mawa);
        $owner = $this->owner('bank-sum@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $bank = $this->coa($mawa, '1002');
        $revenue = $this->coa($mawa, '4001');

        $journal = $this->postJournal($mawa, null, '2026-11-01', [
            ['coa_id' => $bank->id, 'debit' => 3000, 'credit' => 0],
            ['coa_id' => $revenue->id, 'debit' => 0, 'credit' => 3000],
        ]);

        $statement = BankStatement::create([
            'institute_id' => $mawa->id,
            'bank_account_id' => $bank->id,
            'statement_date' => '2026-11-01',
            'status' => 'imported',
        ]);

        BankStatementLine::create([
            'statement_id' => $statement->id,
            'institute_id' => $mawa->id,
            'transaction_date' => '2026-11-01',
            'description' => 'Matched',
            'reference' => $journal->journal_no,
            'amount' => 3000,
            'type' => 'deposit',
        ]);

        BankStatementLine::create([
            'statement_id' => $statement->id,
            'institute_id' => $mawa->id,
            'transaction_date' => '2026-11-01',
            'description' => 'Unmatched',
            'reference' => null,
            'amount' => 1000,
            'type' => 'deposit',
        ]);

        $svc = app(BankReconciliationService::class);
        $svc->autoMatch($statement, $owner->id);

        $summary = $svc->summary($statement);
        $this->assertSame(2, $summary['total']);
        $this->assertSame(1, $summary['matched']);
        $this->assertSame(1, $summary['unmatched']);
    }

    // ─── Test 5: Branch isolation ─────────────────────────────────
    public function test_branch_isolation(): void
    {
        $mawa = $this->institute('Bank Branch');
        $branchA = Branch::create(['institute_id' => $mawa->id, 'name' => 'BA', 'status' => 'active']);
        $branchB = Branch::create(['institute_id' => $mawa->id, 'name' => 'BB', 'status' => 'active']);
        $this->setupAccounting($mawa, $branchA->id);
        $this->setupAccounting($mawa, $branchB->id);
        $owner = $this->owner('bank-branch@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $bankA = ChartOfAccount::withoutGlobalScopes()
            ->where('institute_id', $mawa->id)
            ->where('branch_id', $branchA->id)
            ->where('code', '1002')
            ->firstOrFail();
        $revenueA = ChartOfAccount::withoutGlobalScopes()
            ->where('institute_id', $mawa->id)
            ->where('branch_id', $branchA->id)
            ->where('code', '4001')
            ->firstOrFail();

        $journal = $this->postJournal($mawa, $branchA->id, '2026-11-01', [
            ['coa_id' => $bankA->id, 'debit' => 4000, 'credit' => 0],
            ['coa_id' => $revenueA->id, 'debit' => 0, 'credit' => 4000],
        ]);

        $statement = BankStatement::create([
            'institute_id' => $mawa->id,
            'branch_id' => $branchA->id,
            'bank_account_id' => $bankA->id,
            'statement_date' => '2026-11-01',
            'status' => 'imported',
        ]);

        BankStatementLine::create([
            'statement_id' => $statement->id,
            'institute_id' => $mawa->id,
            'transaction_date' => '2026-11-01',
            'description' => 'Branch A deposit',
            'reference' => $journal->journal_no,
            'amount' => 4000,
            'type' => 'deposit',
        ]);

        $svc = app(BankReconciliationService::class);
        $result = $svc->autoMatch($statement, $owner->id);
        $this->assertSame(1, $result['matched']);

        // Branch B statement with no matching journal should not match
        $bankB = ChartOfAccount::withoutGlobalScopes()
            ->where('institute_id', $mawa->id)
            ->where('branch_id', $branchB->id)
            ->where('code', '1002')
            ->firstOrFail();

        $stmtB = BankStatement::create([
            'institute_id' => $mawa->id,
            'branch_id' => $branchB->id,
            'bank_account_id' => $bankB->id,
            'statement_date' => '2026-11-01',
            'status' => 'imported',
        ]);

        BankStatementLine::create([
            'statement_id' => $stmtB->id,
            'institute_id' => $mawa->id,
            'transaction_date' => '2026-11-01',
            'description' => 'Branch B deposit',
            'reference' => null,
            'amount' => 4000,
            'type' => 'deposit',
        ]);

        $resultB = $svc->autoMatch($stmtB, $owner->id);
        $this->assertSame(0, $resultB['matched'], 'Branch B should not match Branch A journal');
    }

    // ─── Test 6: Tenant isolation ─────────────────────────────────
    public function test_tenant_isolation(): void
    {
        $mawa = $this->institute('Bank Tenant A');
        $other = $this->institute('Bank Tenant B');
        $this->setupAccounting($mawa);
        $this->setupAccounting($other);

        $ownerA = $this->owner('bank-tenanta@example.test');
        $ownerB = $this->owner('bank-tenantb@example.test');
        (new MembershipService)->assign($ownerA, $mawa->id, $this->roleId('institute-owner'));
        (new MembershipService)->assign($ownerB, $other->id, $this->roleId('institute-owner'));

        $bank = $this->coa($mawa, '1002');
        $revenue = $this->coa($mawa, '4001');

        $journal = $this->postJournal($mawa, null, '2026-11-01', [
            ['coa_id' => $bank->id, 'debit' => 6000, 'credit' => 0],
            ['coa_id' => $revenue->id, 'debit' => 0, 'credit' => 6000],
        ]);

        // Tenant A statement
        $stmtA = BankStatement::create([
            'institute_id' => $mawa->id,
            'bank_account_id' => $bank->id,
            'statement_date' => '2026-11-01',
            'status' => 'imported',
        ]);

        BankStatementLine::create([
            'statement_id' => $stmtA->id,
            'institute_id' => $mawa->id,
            'transaction_date' => '2026-11-01',
            'description' => 'Tenant A deposit',
            'reference' => $journal->journal_no,
            'amount' => 6000,
            'type' => 'deposit',
        ]);

        // Tenant B statement with same reference should NOT match Tenant A journal
        $bankB = $this->coa($other, '1002');
        $stmtB = BankStatement::create([
            'institute_id' => $other->id,
            'bank_account_id' => $bankB->id,
            'statement_date' => '2026-11-01',
            'status' => 'imported',
        ]);

        BankStatementLine::create([
            'statement_id' => $stmtB->id,
            'institute_id' => $other->id,
            'transaction_date' => '2026-11-01',
            'description' => 'Tenant B deposit',
            'reference' => $journal->journal_no,
            'amount' => 6000,
            'type' => 'deposit',
        ]);

        $svc = app(BankReconciliationService::class);
        $resultA = $svc->autoMatch($stmtA, $ownerA->id);
        $resultB = $svc->autoMatch($stmtB, $ownerB->id);

        $this->assertSame(1, $resultA['matched']);
        $this->assertSame(0, $resultB['matched'], 'Tenant B should not match Tenant A journals');
    }
}
