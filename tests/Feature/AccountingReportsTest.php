<?php

namespace Tests\Feature;

use App\Models\AccountingAuditTrail;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\Institute;
use App\Models\Journal;
use App\Models\Membership;
use App\Models\OpeningBalance;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\AccountingReportService;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\JournalPostingService;
use App\Services\Accounting\PartyService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * STEP 11 — Accounting Reports & Financial Statements.
 *
 * Every report is a read-only derivation from posted journal entries
 * (journals.status = 'posted', reversal_of IS NULL, not soft-deleted) plus
 * per-fiscal-year opening balances. Drafts and voids never appear; reversals
 * net their originals automatically. Reports are tenant- and branch-scoped and
 * require the existing reports.financial.view permission.
 */
class AccountingReportsTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();
        BranchContext::clear();
        Workspace::clear();
    }

    // ------------------------------------------------------------ Fixtures

    protected function owner(string $email): User
    {
        return (new UserAccountService)->registerOwner([
            'name' => 'Step11 Owner',
            'first_name' => 'Step11',
            'last_name' => 'Owner',
            'email' => $email,
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    protected function staff(string $email): User
    {
        return (new UserAccountService)->createStaffFromInvitation([
            'name' => 'Step11 Staff',
            'first_name' => 'Step11',
            'last_name' => 'Staff',
            'email' => $email,
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    protected function institute(string $name): Institute
    {
        return Institute::where('name', $name)->firstOrFail();
    }

    protected function roleId(string $slug): int
    {
        return Role::where('slug', $slug)->firstOrFail()->id;
    }

    protected function assign(User $user, Institute $institute, string $roleSlug, array $attributes = []): Membership
    {
        return (new MembershipService)->assign($user, $institute->id, $this->roleId($roleSlug), $attributes);
    }

    protected function branch(Institute $institute, string $name): Branch
    {
        return Branch::create([
            'institute_id' => $institute->id,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    protected function setupAccounting(Institute $institute, ?int $branchId = null): void
    {
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branchId);
    }

    protected function coaId(int $instituteId, ?int $branchId, string $code): int
    {
        return (int) ChartOfAccount::withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->where('branch_id', $branchId)
            ->where('code', $code)
            ->value('id');
    }

    protected function currencyId(): int
    {
        return (int) (\DB::table('currencies')->where('code', 'BDT')->value('id') ?? \DB::table('currencies')->orderBy('code')->value('id'));
    }

    protected function posting(): JournalPostingService
    {
        return app(JournalPostingService::class);
    }

    protected function reports(): AccountingReportService
    {
        return app(AccountingReportService::class);
    }

    protected function asUser(User $user, int $workspaceId): static
    {
        return $this->withSession([Workspace::SESSION_KEY => $workspaceId])->actingAs($user, 'web');
    }

    protected function postCashToIncome(Institute $institute, ?int $branchId, float $amount): Journal
    {
        return $this->posting()->create([
            'institute_id' => $institute->id,
            'branch_id' => $branchId,
            'journal_date' => now()->toDateString(),
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => [
                ['coa_id' => $this->coaId((int) $institute->id, $branchId, '1001'), 'debit' => $amount, 'credit' => 0],
                ['coa_id' => $this->coaId((int) $institute->id, $branchId, '4001'), 'debit' => 0, 'credit' => $amount],
            ],
        ]);
    }

    // ------------------------------------------------------------ Trial balance

    public function test_trial_balance_shows_posted_journal_balances(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $this->postCashToIncome($mawa, null, 10000);

        $rows = $this->reports()->trialBalance((int) $mawa->id, null, now()->toDateString());

        $cash = $rows->firstWhere('code', '1001');
        $tuition = $rows->firstWhere('code', '4001');

        $this->assertNotNull($cash);
        $this->assertNotNull($tuition);
        $this->assertSame(10000.0, $cash->balance);
        $this->assertSame(-10000.0, $tuition->balance);
        $this->assertEqualsWithDelta(0.0, $rows->sum('debit') - $rows->sum('credit'), 0.0001);
    }

    public function test_trial_balance_is_tenant_scoped(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $this->setupAccounting($mawa);
        $this->setupAccounting($tutu);

        $this->postCashToIncome($mawa, null, 5000);

        $owner = $this->owner('step11-tb-tenant@example.test');
        $this->assign($owner, $mawa, 'institute-owner');
        $this->assign($owner, $tutu, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->get(route('accounting.reports.trial-balance'))
            ->assertOk()
            ->assertSee('5,000.00');

        $this->asUser($owner, (int) $tutu->id)
            ->get(route('accounting.reports.trial-balance'))
            ->assertOk()
            ->assertDontSee('5,000.00');
    }

    public function test_trial_balance_requires_reports_permission(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $teacher = $this->staff('step11-tb-perm@example.test');
        $this->assign($teacher, $mawa, 'teacher');

        $this->asUser($teacher, (int) $mawa->id)
            ->get(route('accounting.reports.trial-balance'))
            ->assertForbidden();
    }

    public function test_trial_balance_excludes_draft_and_void_journals(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => null,
            'journal_date' => now()->toDateString(),
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => [
                ['coa_id' => $this->coaId((int) $mawa->id, null, '1001'), 'debit' => 9000, 'credit' => 0],
                ['coa_id' => $this->coaId((int) $mawa->id, null, '4001'), 'debit' => 0, 'credit' => 9000],
            ],
        ], null, false);

        $this->postCashToIncome($mawa, null, 10000);

        $rows = $this->reports()->trialBalance((int) $mawa->id, null, now()->toDateString());

        $cash = $rows->firstWhere('code', '1001');
        $this->assertNotNull($cash);
        $this->assertSame(10000.0, $cash->balance);
    }

    public function test_trial_balance_excludes_reversals(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $journal = $this->postCashToIncome($mawa, null, 5000);

        $rows = $this->reports()->trialBalance((int) $mawa->id, null, now()->toDateString());
        $this->assertSame(5000.0, $rows->firstWhere('code', '1001')->balance);

        $this->posting()->reverse($journal, (int) $mawa->id, 1, 'cancel');

        $rows = $this->reports()->trialBalance((int) $mawa->id, null, now()->toDateString());
        $cash = $rows->firstWhere('code', '1001');
        $this->assertNull($cash);
        $this->assertTrue($rows->isEmpty());
    }

    public function test_trial_balance_includes_opening_balances(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $year = FiscalYear::query()->where('institute_id', $mawa->id)->whereNull('branch_id')->firstOrFail();

        OpeningBalance::create([
            'institute_id' => $mawa->id,
            'branch_id' => null,
            'fiscal_year_id' => $year->id,
            'coa_id' => $this->coaId((int) $mawa->id, null, '1001'),
            'debit' => 2000,
            'credit' => 0,
        ]);
        OpeningBalance::create([
            'institute_id' => $mawa->id,
            'branch_id' => null,
            'fiscal_year_id' => $year->id,
            'coa_id' => $this->coaId((int) $mawa->id, null, '3001'),
            'debit' => 0,
            'credit' => 2000,
        ]);

        $rows = $this->reports()->trialBalance((int) $mawa->id, null, now()->toDateString(), (int) $year->id);

        $this->assertSame(2000.0, $rows->firstWhere('code', '1001')->balance);
        $this->assertSame(-2000.0, $rows->firstWhere('code', '3001')->balance);
    }

    // ------------------------------------------------------------ General ledger

    public function test_general_ledger_shows_running_balances(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $this->postCashToIncome($mawa, null, 100);
        $this->postCashToIncome($mawa, null, 250);

        $cashId = $this->coaId((int) $mawa->id, null, '1001');
        $ledger = $this->reports()->generalLedger((int) $mawa->id, null, $cashId, null, null, null);

        $this->assertCount(2, $ledger);
        $this->assertSame(100.0, $ledger[0]->running_balance);
        $this->assertSame(350.0, $ledger[1]->running_balance);
    }

    public function test_general_ledger_filters_by_account(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $this->postCashToIncome($mawa, null, 100);
        $this->postCashToIncome($mawa, null, 250);

        $cashId = $this->coaId((int) $mawa->id, null, '1001');
        $ledger = $this->reports()->generalLedger((int) $mawa->id, null, $cashId, null, null);

        $this->assertTrue($ledger->isNotEmpty());
        $this->assertSame($cashId, (int) $ledger->first()->coa_id);
        $this->assertTrue($ledger->every(fn ($row) => (int) $row->coa_id === $cashId));
    }

    // ------------------------------------------------------------ Account ledger

    public function test_account_ledger_shows_opening_and_closing_balances(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $year = FiscalYear::query()->where('institute_id', $mawa->id)->whereNull('branch_id')->firstOrFail();
        $cashId = $this->coaId((int) $mawa->id, null, '1001');

        OpeningBalance::create([
            'institute_id' => $mawa->id,
            'branch_id' => null,
            'fiscal_year_id' => $year->id,
            'coa_id' => $cashId,
            'debit' => 1000,
            'credit' => 0,
        ]);

        $this->postCashToIncome($mawa, null, 500);
        $this->postCashToIncome($mawa, null, 150);

        $statement = $this->reports()->accountLedger((int) $mawa->id, null, $cashId, null, null, (int) $year->id);

        $this->assertSame(1000.0, $statement['opening']);
        $this->assertSame(650.0, $statement['debit']);
        $this->assertSame(0.0, $statement['credit']);
        $this->assertSame(1650.0, $statement['closing']);
        $this->assertSame(2, $statement['lines']->count());
        $this->assertSame(1650.0, $statement['lines']->last()->running_balance);
    }

    public function test_account_ledger_rejects_account_from_other_institute(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $this->setupAccounting($mawa);
        $this->setupAccounting($tutu);

        $foreignCash = $this->coaId((int) $tutu->id, null, '1001');

        $this->expectException(ModelNotFoundException::class);
        $this->reports()->accountLedger((int) $mawa->id, null, $foreignCash, null, null);
    }

    public function test_account_ledger_route_requires_account_selection(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $owner = $this->owner('step11-al-empty@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->get(route('accounting.reports.account-ledger'))
            ->assertOk()
            ->assertSee('Select an account');
    }

    // ------------------------------------------------------------ Profit & loss

    public function test_profit_and_loss_shows_income_expense_and_net(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $this->postCashToIncome($mawa, null, 10000);

        $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => null,
            'journal_date' => now()->toDateString(),
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => [
                ['coa_id' => $this->coaId((int) $mawa->id, null, '5006'), 'debit' => 4000, 'credit' => 0],
                ['coa_id' => $this->coaId((int) $mawa->id, null, '1001'), 'debit' => 0, 'credit' => 4000],
            ],
        ]);

        $statement = $this->reports()->profitAndLoss((int) $mawa->id, null, now()->startOfYear()->toDateString(), now()->toDateString());

        $this->assertSame(10000.0, $statement['total_income']);
        $this->assertSame(4000.0, $statement['total_expense']);
        $this->assertSame(6000.0, $statement['net']);
    }

    // ------------------------------------------------------------ Balance sheet

    public function test_balance_sheet_balances(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $this->postCashToIncome($mawa, null, 10000);

        $statement = $this->reports()->balanceSheet((int) $mawa->id, null, now()->toDateString());

        $this->assertSame(10000.0, $statement['total_assets']);
        $this->assertSame(0.0, $statement['total_liabilities']);
        $this->assertSame(10000.0, $statement['total_equity']);
        $this->assertSame(10000.0, $statement['net_income']);
        $this->assertEqualsWithDelta(
            $statement['total_assets'],
            $statement['total_liabilities'] + $statement['total_equity'],
            0.0001,
        );
    }

    public function test_balance_sheet_includes_net_income_in_equity(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $year = FiscalYear::query()->where('institute_id', $mawa->id)->whereNull('branch_id')->firstOrFail();

        OpeningBalance::create([
            'institute_id' => $mawa->id,
            'branch_id' => null,
            'fiscal_year_id' => $year->id,
            'coa_id' => $this->coaId((int) $mawa->id, null, '3002'),
            'debit' => 0,
            'credit' => 5000,
        ]);

        $this->postCashToIncome($mawa, null, 10000);

        $statement = $this->reports()->balanceSheet((int) $mawa->id, null, now()->toDateString(), (int) $year->id);

        $this->assertSame(10000.0, $statement['total_assets']);
        $this->assertSame(5000.0, $statement['total_equity'] - $statement['net_income']);
        $this->assertSame(15000.0, $statement['total_equity']);
    }

    // ------------------------------------------------------------ Cash & bank

    public function test_cash_bank_summary_shows_cash_and_bank_balances(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $this->postCashToIncome($mawa, null, 10000);

        $rows = $this->reports()->cashBankSummary((int) $mawa->id, null, now()->toDateString());

        $cash = $rows->firstWhere('code', '1001');
        $bank = $rows->firstWhere('code', '1002');

        $this->assertNotNull($cash);
        $this->assertNotNull($bank);
        $this->assertTrue($cash->is_cash);
        $this->assertTrue($bank->is_bank);
        $this->assertSame(10000.0, $cash->balance);
        $this->assertSame(0.0, $bank->balance);
    }

    // ------------------------------------------------------------ Receivables / payables

    public function test_receivables_report_shows_customer_balances_with_aging(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $customer = app(PartyService::class)->create($mawa->id, null, [
            'type' => 'customer',
            'name' => 'Step11 Receivable Customer',
            'phone' => '0161'.rand(100000, 999999),
        ]);

        $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => null,
            'journal_date' => now()->toDateString(),
            'type' => 'sale',
            'currency_id' => $this->currencyId(),
            'entries' => [
                ['coa_id' => $this->coaId((int) $mawa->id, null, '1100'), 'debit' => 5000, 'credit' => 0, 'party_id' => $customer->id],
                ['coa_id' => $this->coaId((int) $mawa->id, null, '4001'), 'debit' => 0, 'credit' => 5000],
            ],
        ]);

        $report = $this->reports()->receivablesReport((int) $mawa->id, null, now()->toDateString());

        $this->assertSame(5000.0, $report['totals']['receivable']);
        $this->assertCount(1, $report['customers']);
        $this->assertSame(5000.0, $report['customers']->first()->receivable);
        $this->assertSame(5000.0, $report['customers']->first()->aging['current']);
    }

    public function test_payables_report_shows_supplier_balances_with_aging(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $supplier = app(PartyService::class)->create($mawa->id, null, [
            'type' => 'supplier',
            'name' => 'Step11 Payable Supplier',
            'phone' => '0162'.rand(100000, 999999),
        ]);

        $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => null,
            'journal_date' => now()->toDateString(),
            'type' => 'purchase',
            'currency_id' => $this->currencyId(),
            'entries' => [
                ['coa_id' => $this->coaId((int) $mawa->id, null, '5006'), 'debit' => 3000, 'credit' => 0],
                ['coa_id' => $this->coaId((int) $mawa->id, null, '2001'), 'debit' => 0, 'credit' => 3000, 'party_id' => $supplier->id],
            ],
        ]);

        $report = $this->reports()->payablesReport((int) $mawa->id, null, now()->toDateString());

        $this->assertSame(3000.0, $report['totals']['payable']);
        $this->assertCount(1, $report['suppliers']);
        $this->assertSame(3000.0, $report['suppliers']->first()->payable);
        $this->assertSame(-3000.0, $report['suppliers']->first()->net);
        $this->assertArrayHasKey('current', $report['suppliers']->first()->aging);
        $this->assertArrayHasKey('31_60', $report['suppliers']->first()->aging);
        $this->assertArrayHasKey('61_90', $report['suppliers']->first()->aging);
        $this->assertArrayHasKey('91_plus', $report['suppliers']->first()->aging);
    }

    public function test_receivables_report_is_tenant_scoped(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $this->setupAccounting($mawa);
        $this->setupAccounting($tutu);

        $customerM = app(PartyService::class)->create($mawa->id, null, [
            'type' => 'customer',
            'name' => 'Step11 Mawa AR Customer',
            'phone' => '0163'.rand(100000, 999999),
        ]);
        $customerT = app(PartyService::class)->create($tutu->id, null, [
            'type' => 'customer',
            'name' => 'Step11 Tutu AR Customer',
            'phone' => '0164'.rand(100000, 999999),
        ]);

        foreach ([
            [$mawa, $customerM, '4001', 500],
            [$tutu, $customerT, '4001', 250],
        ] as [$institute, $customer, $incomeCode, $amount]) {
            $this->posting()->create([
                'institute_id' => $institute->id,
                'branch_id' => null,
                'journal_date' => now()->toDateString(),
                'type' => 'sale',
                'currency_id' => $this->currencyId(),
                'entries' => [
                    ['coa_id' => $this->coaId((int) $institute->id, null, '1100'), 'debit' => $amount, 'credit' => 0, 'party_id' => $customer->id],
                    ['coa_id' => $this->coaId((int) $institute->id, null, $incomeCode), 'debit' => 0, 'credit' => $amount],
                ],
            ]);
        }

        $owner = $this->owner('step11-ar-tenant@example.test');
        $this->assign($owner, $mawa, 'institute-owner');
        $this->assign($owner, $tutu, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->get(route('accounting.reports.receivables'))
            ->assertOk()
            ->assertSee('Step11 Mawa AR Customer')
            ->assertDontSee('Step11 Tutu AR Customer');

        $this->asUser($owner, (int) $tutu->id)
            ->get(route('accounting.reports.receivables'))
            ->assertOk()
            ->assertSee('Step11 Tutu AR Customer')
            ->assertDontSee('Step11 Mawa AR Customer');
    }

    // ------------------------------------------------------------ Route guard & scope

    public function test_all_report_routes_are_permission_guarded(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $teacher = $this->staff('step11-all-guard@example.test');
        $this->assign($teacher, $mawa, 'teacher');

        foreach ([
            'accounting.reports.trial-balance',
            'accounting.reports.general-ledger',
            'accounting.reports.account-ledger',
            'accounting.reports.profit-loss',
            'accounting.reports.balance-sheet',
            'accounting.reports.cash-bank',
            'accounting.reports.receivables',
            'accounting.reports.payables',
        ] as $routeName) {
            $this->asUser($teacher, (int) $mawa->id)->get(route($routeName))->assertForbidden();
        }
    }

    public function test_all_report_routes_load_for_owner(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);
        $owner = $this->owner('step11-all-owner@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        foreach ([
            'accounting.reports.trial-balance',
            'accounting.reports.general-ledger',
            'accounting.reports.profit-loss',
            'accounting.reports.balance-sheet',
            'accounting.reports.cash-bank',
            'accounting.reports.receivables',
            'accounting.reports.payables',
        ] as $routeName) {
            $this->asUser($owner, (int) $mawa->id)->get(route($routeName))->assertOk();
        }
    }

    public function test_reports_are_branch_scoped_for_branch_manager(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $branchA = $this->branch($mawa, 'Branch A');
        $branchB = $this->branch($mawa, 'Branch B');
        $this->setupAccounting($mawa, (int) $branchA->id);
        $this->setupAccounting($mawa, (int) $branchB->id);

        $this->postCashToIncome($mawa, (int) $branchA->id, 1000);
        $this->postCashToIncome($mawa, (int) $branchB->id, 9999);

        $manager = $this->staff('step11-branch-report@example.test');
        $this->assign($manager, $mawa, 'branch-manager', ['branch_id' => $branchA->id]);

        $this->asUser($manager, (int) $mawa->id)
            ->get(route('accounting.reports.trial-balance'))
            ->assertOk()
            ->assertSee('1,000.00')
            ->assertDontSee('9,999.00');
    }

    public function test_account_ledger_route_shows_selected_account_statement(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $this->postCashToIncome($mawa, null, 750);

        $owner = $this->owner('step11-al-route@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $cashId = $this->coaId((int) $mawa->id, null, '1001');

        $this->asUser($owner, (int) $mawa->id)
            ->get(route('accounting.reports.account-ledger', ['account_id' => $cashId]))
            ->assertOk()
            ->assertSee('Cash in Hand')
            ->assertSee('750.00')
            ->assertSee('Closing balance');
    }

    public function test_audit_trail_is_written_for_journals_that_feed_reports(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $journal = $this->postCashToIncome($mawa, null, 100);

        $this->assertGreaterThan(0, AccountingAuditTrail::withoutGlobalScopes()
            ->where('institute_id', $mawa->id)
            ->where('entity_type', 'journal')
            ->where('entity_id', $journal->id)
            ->count());
    }
}
