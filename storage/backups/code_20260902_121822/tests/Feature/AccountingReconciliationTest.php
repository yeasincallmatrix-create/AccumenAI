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
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * STEP 69E — Accounting reconciliation tests.
 *
 * Verifies cross-report consistency: cash flow ↔ cash/bank, P&L ↔ equity,
 * accrual transaction flow, and branch isolation.
 */
class AccountingReconciliationTest extends \Tests\TestCase
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
            'name' => 'Recon Owner',
            'first_name' => 'Recon',
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

    // ─── Test 1: Cash flow closing == cash/bank balance ────────────
    public function test_cash_flow_closing_matches_cash_bank_balance(): void
    {
        $mawa = $this->institute('Recon CF');
        $this->setupAccounting($mawa);
        $owner = $this->owner('recon-cf@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $cash = $this->coa($mawa, '1001');
        $revenue = $this->coa($mawa, '4001');

        // DR Cash, CR Revenue — fully classified
        $this->postJournal($mawa, null, '2026-09-01', [
            ['coa_id' => $cash->id, 'debit' => 7500, 'credit' => 0],
            ['coa_id' => $revenue->id, 'debit' => 0, 'credit' => 7500],
        ]);

        $reports = app(AccountingReportService::class);

        $cf = $reports->cashFlowStatement($mawa->id, null, '2026-09-01', '2026-09-30');
        $cb = $reports->cashBankSummary($mawa->id, null, '2026-09-30');

        $cashBankTotal = round((float) $cb->sum('balance'), 4);

        $this->assertSame(0.0, $cf['unclassified_amount'], 'No unclassified amounts when all accounts tagged');
        $this->assertSame($cashBankTotal, $cf['closing'], "Cash flow closing must equal cash/bank summary balance");
        $this->assertSame(7500.0, $cf['operating']);
    }

    // ─── Test 2: Accrual transaction flow (AR → Cash) ─────────────
    public function test_accrual_transaction_appears_in_operating(): void
    {
        $mawa = $this->institute('Recon Accrual');
        $this->setupAccounting($mawa);
        $owner = $this->owner('recon-accrual@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $cash = $this->coa($mawa, '1001');
        $ar = $this->coa($mawa, '1100');
        $revenue = $this->coa($mawa, '4001');

        // Step 1: Invoice sale — DR AR, CR Revenue (no cash movement)
        $this->postJournal($mawa, null, '2026-09-01', [
            ['coa_id' => $ar->id, 'debit' => 5000, 'credit' => 0],
            ['coa_id' => $revenue->id, 'debit' => 0, 'credit' => 5000],
        ]);

        // Step 2: Payment received — DR Cash, CR AR (operating cash inflow)
        $this->postJournal($mawa, null, '2026-09-15', [
            ['coa_id' => $cash->id, 'debit' => 5000, 'credit' => 0],
            ['coa_id' => $ar->id, 'debit' => 0, 'credit' => 5000],
        ]);

        $reports = app(AccountingReportService::class);
        $cf = $reports->cashFlowStatement($mawa->id, null, '2026-09-01', '2026-09-30');

        // AR is now classified as operating, so the payment should appear
        $this->assertSame(5000.0, $cf['operating'], 'AR payment classified as operating inflow');
        $this->assertSame(0.0, $cf['unclassified_amount']);
    }

    // ─── Test 3: P&L net income == BS equity increase ─────────────
    public function test_pl_net_income_reflected_in_balance_sheet_equity(): void
    {
        $mawa = $this->institute('Recon PLBS');
        $this->setupAccounting($mawa);
        $owner = $this->owner('recon-plbs@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $cash = $this->coa($mawa, '1001');
        $revenue = $this->coa($mawa, '4001');
        $expense = $this->coa($mawa, '5001');

        // Income: +8000
        $this->postJournal($mawa, null, '2026-09-01', [
            ['coa_id' => $cash->id, 'debit' => 8000, 'credit' => 0],
            ['coa_id' => $revenue->id, 'debit' => 0, 'credit' => 8000],
        ]);
        // Expense: −3000
        $this->postJournal($mawa, null, '2026-09-05', [
            ['coa_id' => $expense->id, 'debit' => 3000, 'credit' => 0],
            ['coa_id' => $cash->id, 'debit' => 0, 'credit' => 3000],
        ]);

        $reports = app(AccountingReportService::class);

        $pl = $reports->profitAndLoss($mawa->id, null, '2026-09-01', '2026-09-30');
        $bs = $reports->balanceSheet($mawa->id, null, '2026-09-30');

        $this->assertSame(5000.0, $pl['net'], 'P&L net = 8000 − 3000');
        $this->assertSame(5000.0, $bs['net_income'], 'BS net_income matches P&L net');
        // A = L + E must hold
        $this->assertEqualsWithDelta(
            $bs['total_assets'],
            $bs['total_liabilities'] + $bs['total_equity'],
            0.001,
            'Balance sheet equation must hold',
        );
    }

    // ─── Test 4: Multi-branch isolation ────────────────────────────
    public function test_branch_a_transactions_excluded_from_branch_b(): void
    {
        $mawa = $this->institute('Recon Branch');
        $branchA = Branch::create(['institute_id' => $mawa->id, 'name' => 'Branch A', 'status' => 'active']);
        $branchB = Branch::create(['institute_id' => $mawa->id, 'name' => 'Branch B', 'status' => 'active']);
        $this->setupAccounting($mawa, $branchA->id);
        $this->setupAccounting($mawa, $branchB->id);
        $owner = $this->owner('recon-branch@example.test');
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

        // Branch A transaction
        $this->postJournal($mawa, $branchA->id, '2026-09-01', [
            ['coa_id' => $cashA->id, 'debit' => 10000, 'credit' => 0],
            ['coa_id' => $revenueA->id, 'debit' => 0, 'credit' => 10000],
        ]);

        $reports = app(AccountingReportService::class);

        // Branch A sees the transaction
        $cfA = $reports->cashFlowStatement($mawa->id, $branchA->id, '2026-09-01', '2026-09-30');
        $this->assertSame(10000.0, $cfA['operating']);

        // Branch B sees nothing
        $cfB = $reports->cashFlowStatement($mawa->id, $branchB->id, '2026-09-01', '2026-09-30');
        $this->assertSame(0.0, $cfB['operating']);
        $this->assertSame(0.0, $cfB['net_change']);
    }
}
