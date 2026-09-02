<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\Institute;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\BudgetCalculationService;
use App\Services\Accounting\BudgetService;
use App\Services\Accounting\JournalPostingService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * STEP 74 — Budget Variance Tests.
 */
class BudgetVarianceTest extends \Tests\TestCase
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
            'name' => 'Budget Owner',
            'first_name' => 'Budget',
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

    protected function createFiscalYear(Institute $institute, string $start, string $end): FiscalYear
    {
        return FiscalYear::create([
            'institute_id' => $institute->id,
            'name' => 'FY '.date('Y', strtotime($start)),
            'start_date' => $start,
            'end_date' => $end,
            'status' => 'open',
        ]);
    }

    // ─── Test 1: Budget vs actual with matching amounts ───────────
    public function test_budget_vs_actual_matching(): void
    {
        $mawa = $this->institute('Budget Match');
        $this->setupAccounting($mawa);
        $owner = $this->owner('budget-match@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $fy = $this->createFiscalYear($mawa, '2026-01-01', '2026-12-31');
        $expense = $this->coa($mawa, '5001');
        $cash = $this->coa($mawa, '1001');

        // Budget: 60000 expense for the year
        $budgetSvc = app(BudgetService::class);
        $budget = $budgetSvc->create($mawa->id, null, [
            'fiscal_year_id' => $fy->id,
            'currency_id' => $this->currencyId(),
            'name' => 'Test Budget',
            'type' => 'expense',
            'lines' => [
                ['coa_id' => $expense->id, 'month' => 0, 'amount' => 60000],
            ],
        ], $owner->id);

        $budgetSvc->submit($budget, $owner->id);
        $budgetSvc->approve($budget, $owner->id);

        // Actual: 60000 expense
        $this->postJournal($mawa, null, '2026-06-15', [
            ['coa_id' => $expense->id, 'debit' => 60000, 'credit' => 0],
            ['coa_id' => $cash->id, 'debit' => 0, 'credit' => 60000],
        ]);

        $calc = app(BudgetCalculationService::class);
        $result = $calc->budgetVsActualForBudget($mawa->id, null, $budget->id);

        $this->assertSame(60000.0, $result['totals']['budget']);
        $this->assertSame(60000.0, $result['totals']['actual']);
        $this->assertSame(0.0, $result['totals']['variance']);
    }

    // ─── Test 2: Budget vs actual over budget ─────────────────────
    public function test_budget_vs_actual_over_budget(): void
    {
        $mawa = $this->institute('Budget Over');
        $this->setupAccounting($mawa);
        $owner = $this->owner('budget-over@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $fy = $this->createFiscalYear($mawa, '2026-01-01', '2026-12-31');
        $expense = $this->coa($mawa, '5001');
        $cash = $this->coa($mawa, '1001');

        $budgetSvc = app(BudgetService::class);
        $budget = $budgetSvc->create($mawa->id, null, [
            'fiscal_year_id' => $fy->id,
            'currency_id' => $this->currencyId(),
            'name' => 'Over Budget',
            'type' => 'expense',
            'lines' => [
                ['coa_id' => $expense->id, 'month' => 0, 'amount' => 50000],
            ],
        ], $owner->id);

        $budgetSvc->submit($budget, $owner->id);
        $budgetSvc->approve($budget, $owner->id);

        // Actual: 75000 (over budget)
        $this->postJournal($mawa, null, '2026-06-15', [
            ['coa_id' => $expense->id, 'debit' => 75000, 'credit' => 0],
            ['coa_id' => $cash->id, 'debit' => 0, 'credit' => 75000],
        ]);

        $calc = app(BudgetCalculationService::class);
        $result = $calc->budgetVsActualForBudget($mawa->id, null, $budget->id);

        $this->assertSame(50000.0, $result['totals']['budget']);
        $this->assertSame(75000.0, $result['totals']['actual']);
        $this->assertLessThan(0, $result['totals']['variance'], 'Should be negative (over budget)');
    }

    // ─── Test 3: Budget vs actual under budget ────────────────────
    public function test_budget_vs_actual_under_budget(): void
    {
        $mawa = $this->institute('Budget Under');
        $this->setupAccounting($mawa);
        $owner = $this->owner('budget-under@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $fy = $this->createFiscalYear($mawa, '2026-01-01', '2026-12-31');
        $expense = $this->coa($mawa, '5001');
        $cash = $this->coa($mawa, '1001');

        $budgetSvc = app(BudgetService::class);
        $budget = $budgetSvc->create($mawa->id, null, [
            'fiscal_year_id' => $fy->id,
            'currency_id' => $this->currencyId(),
            'name' => 'Under Budget',
            'type' => 'expense',
            'lines' => [
                ['coa_id' => $expense->id, 'month' => 0, 'amount' => 100000],
            ],
        ], $owner->id);

        $budgetSvc->submit($budget, $owner->id);
        $budgetSvc->approve($budget, $owner->id);

        // Actual: 30000 (under budget)
        $this->postJournal($mawa, null, '2026-06-15', [
            ['coa_id' => $expense->id, 'debit' => 30000, 'credit' => 0],
            ['coa_id' => $cash->id, 'debit' => 0, 'credit' => 30000],
        ]);

        $calc = app(BudgetCalculationService::class);
        $result = $calc->budgetVsActualForBudget($mawa->id, null, $budget->id);

        $this->assertSame(100000.0, $result['totals']['budget']);
        $this->assertSame(30000.0, $result['totals']['actual']);
        $this->assertGreaterThan(0, $result['totals']['variance'], 'Should be positive (under budget)');
    }

    // ─── Test 4: Branch isolation ─────────────────────────────────
    public function test_branch_isolation(): void
    {
        $mawa = $this->institute('Budget Branch');
        $branchA = Branch::create(['institute_id' => $mawa->id, 'name' => 'BA', 'status' => 'active']);
        $this->setupAccounting($mawa, $branchA->id);
        $owner = $this->owner('budget-branch@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $fy = $this->createFiscalYear($mawa, '2026-01-01', '2026-12-31');
        $expense = ChartOfAccount::withoutGlobalScopes()
            ->where('institute_id', $mawa->id)
            ->where('branch_id', $branchA->id)
            ->where('code', '5001')
            ->firstOrFail();
        $cash = ChartOfAccount::withoutGlobalScopes()
            ->where('institute_id', $mawa->id)
            ->where('branch_id', $branchA->id)
            ->where('code', '1001')
            ->firstOrFail();

        $budgetSvc = app(BudgetService::class);
        $budget = $budgetSvc->create($mawa->id, $branchA->id, [
            'fiscal_year_id' => $fy->id,
            'currency_id' => $this->currencyId(),
            'name' => 'Branch Budget',
            'type' => 'expense',
            'lines' => [
                ['coa_id' => $expense->id, 'month' => 0, 'amount' => 40000],
            ],
        ], $owner->id);

        $budgetSvc->submit($budget, $owner->id);
        $budgetSvc->approve($budget, $owner->id);

        $this->postJournal($mawa, $branchA->id, '2026-06-15', [
            ['coa_id' => $expense->id, 'debit' => 20000, 'credit' => 0],
            ['coa_id' => $cash->id, 'debit' => 0, 'credit' => 20000],
        ]);

        $calc = app(BudgetCalculationService::class);
        $result = $calc->budgetVsActualForBudget($mawa->id, $branchA->id, $budget->id);

        $this->assertSame(40000.0, $result['totals']['budget']);
        $this->assertSame(20000.0, $result['totals']['actual']);
    }

    // ─── Test 5: Tenant isolation ─────────────────────────────────
    public function test_tenant_isolation(): void
    {
        $mawa = $this->institute('Budget Tenant A');
        $other = $this->institute('Budget Tenant B');
        $this->setupAccounting($mawa);
        $this->setupAccounting($other);

        $ownerA = $this->owner('budget-tenanta@example.test');
        $ownerB = $this->owner('budget-tenantb@example.test');
        (new MembershipService)->assign($ownerA, $mawa->id, $this->roleId('institute-owner'));
        (new MembershipService)->assign($ownerB, $other->id, $this->roleId('institute-owner'));

        $fyA = $this->createFiscalYear($mawa, '2026-01-01', '2026-12-31');
        $fyB = $this->createFiscalYear($other, '2026-01-01', '2026-12-31');

        $expenseA = $this->coa($mawa, '5001');
        $cashA = $this->coa($mawa, '1001');
        $expenseB = $this->coa($other, '5001');

        $budgetSvc = app(BudgetService::class);

        // Tenant A budget
        $budgetA = $budgetSvc->create($mawa->id, null, [
            'fiscal_year_id' => $fyA->id,
            'currency_id' => $this->currencyId(),
            'name' => 'Tenant A Budget',
            'type' => 'expense',
            'lines' => [
                ['coa_id' => $expenseA->id, 'month' => 0, 'amount' => 25000],
            ],
        ], $ownerA->id);
        $budgetSvc->submit($budgetA, $ownerA->id);
        $budgetSvc->approve($budgetA, $ownerA->id);

        // Tenant A actual
        $this->postJournal($mawa, null, '2026-06-15', [
            ['coa_id' => $expenseA->id, 'debit' => 25000, 'credit' => 0],
            ['coa_id' => $cashA->id, 'debit' => 0, 'credit' => 25000],
        ]);

        // Tenant B budget (no actuals)
        $budgetB = $budgetSvc->create($other->id, null, [
            'fiscal_year_id' => $fyB->id,
            'currency_id' => $this->currencyId(),
            'name' => 'Tenant B Budget',
            'type' => 'expense',
            'lines' => [
                ['coa_id' => $expenseB->id, 'month' => 0, 'amount' => 10000],
            ],
        ], $ownerB->id);
        $budgetSvc->submit($budgetB, $ownerB->id);
        $budgetSvc->approve($budgetB, $ownerB->id);

        $calc = app(BudgetCalculationService::class);

        $resultA = $calc->budgetVsActualForBudget($mawa->id, null, $budgetA->id);
        $this->assertSame(25000.0, $resultA['totals']['actual']);

        $resultB = $calc->budgetVsActualForBudget($other->id, null, $budgetB->id);
        $this->assertSame(0.0, $resultB['totals']['actual'], 'Tenant B should have no actuals');
    }
}
