<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\Institute;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\AccountingDashboardService;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Accounting\AccountingReportService;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\FinancialReportService;
use App\Services\Accounting\JournalPostingService;
use App\Services\Accounting\PartyService;
use App\Services\Accounting\ReceivablesPayablesService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * STEP 13 — Accounting Dashboard & Financial Overview.
 *
 * The dashboard is a read/analytics layer. Every value must match the
 * authoritative reports (P&L, receivables/payables, cash/bank) and stay
 * tenant/branch scoped with reports.financial.view gating.
 */
class AccountingDashboardTest extends TestCase
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
            'name' => 'Step13 Owner',
            'first_name' => 'Step13',
            'last_name' => 'Owner',
            'email' => $email,
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    protected function staff(string $email): User
    {
        return (new UserAccountService)->createStaffFromInvitation([
            'name' => 'Step13 Staff',
            'first_name' => 'Step13',
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

    protected function dashboard(): AccountingDashboardService
    {
        return app(AccountingDashboardService::class);
    }

    protected function asUser(User $user, int $workspaceId): static
    {
        return $this->withSession([Workspace::SESSION_KEY => $workspaceId])->actingAs($user, 'web');
    }

    protected function postIncome(Institute $institute, ?int $branchId, float $amount, ?string $date = null): void
    {
        $this->posting()->create([
            'institute_id' => $institute->id,
            'branch_id' => $branchId,
            'journal_date' => $date ?? now()->toDateString(),
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => [
                ['coa_id' => $this->coaId((int) $institute->id, $branchId, '1001'), 'debit' => $amount, 'credit' => 0],
                ['coa_id' => $this->coaId((int) $institute->id, $branchId, '4001'), 'debit' => 0, 'credit' => $amount],
            ],
        ]);
    }

    protected function postExpense(Institute $institute, ?int $branchId, float $amount, ?string $date = null): void
    {
        $this->posting()->create([
            'institute_id' => $institute->id,
            'branch_id' => $branchId,
            'journal_date' => $date ?? now()->toDateString(),
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => [
                ['coa_id' => $this->coaId((int) $institute->id, $branchId, '5006'), 'debit' => $amount, 'credit' => 0],
                ['coa_id' => $this->coaId((int) $institute->id, $branchId, '1001'), 'debit' => 0, 'credit' => $amount],
            ],
        ]);
    }

    protected function postSaleToCustomer(Institute $institute, ?int $branchId, float $amount, ?string $date = null): int
    {
        $customer = app(PartyService::class)->create($institute->id, $branchId, [
            'type' => 'customer',
            'name' => 'Step13 Dashboard Customer',
            'phone' => '0170'.rand(100000, 999999),
        ]);

        return $this->posting()->create([
            'institute_id' => $institute->id,
            'branch_id' => $branchId,
            'journal_date' => $date ?? now()->toDateString(),
            'type' => 'sale',
            'currency_id' => $this->currencyId(),
            'entries' => [
                ['coa_id' => $this->coaId((int) $institute->id, $branchId, '1100'), 'debit' => $amount, 'credit' => 0, 'party_id' => $customer->id],
                ['coa_id' => $this->coaId((int) $institute->id, $branchId, '4001'), 'debit' => 0, 'credit' => $amount],
            ],
        ])->id;
    }

    protected function postPurchaseFromSupplier(Institute $institute, ?int $branchId, float $amount, ?string $date = null): int
    {
        $supplier = app(PartyService::class)->create($institute->id, $branchId, [
            'type' => 'supplier',
            'name' => 'Step13 Dashboard Supplier',
            'phone' => '0171'.rand(100000, 999999),
        ]);

        return $this->posting()->create([
            'institute_id' => $institute->id,
            'branch_id' => $branchId,
            'journal_date' => $date ?? now()->toDateString(),
            'type' => 'purchase',
            'currency_id' => $this->currencyId(),
            'entries' => [
                ['coa_id' => $this->coaId((int) $institute->id, $branchId, '5006'), 'debit' => $amount, 'credit' => 0],
                ['coa_id' => $this->coaId((int) $institute->id, $branchId, '2001'), 'debit' => 0, 'credit' => $amount, 'party_id' => $supplier->id],
            ],
        ])->id;
    }

    protected function currentYearId(Institute $institute, ?int $branchId = null): int
    {
        return (int) FiscalYear::query()
            ->where('institute_id', $institute->id)
            ->where('branch_id', $branchId)
            ->firstOrFail()
            ->id;
    }

    // ------------------------------------------------------------ Access

    public function test_authorized_owner_can_open_accounting_dashboard(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $owner = $this->owner('step13-dash-owner@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->get(route('accounting.dashboard'))
            ->assertOk()
            ->assertSee('Accounting Dashboard');
    }

    public function test_unauthorized_user_receives_403(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $teacher = $this->staff('step13-dash-teacher@example.test');
        $this->assign($teacher, $mawa, 'teacher');

        $receptionist = $this->staff('step13-dash-receptionist@example.test');
        $this->assign($receptionist, $mawa, 'receptionist');

        $this->asUser($teacher, (int) $mawa->id)
            ->get(route('accounting.dashboard'))
            ->assertForbidden();

        $this->asUser($receptionist, (int) $mawa->id)
            ->get(route('accounting.dashboard'))
            ->assertForbidden();
    }

    public function test_staff_with_reports_permission_can_open_dashboard(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $accountant = $this->staff('step13-dash-accountant@example.test');
        $this->assign($accountant, $mawa, 'accountant');

        $this->asUser($accountant, (int) $mawa->id)
            ->get(route('accounting.dashboard'))
            ->assertOk();
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('accounting.dashboard'))
            ->assertRedirect(route('admin.login'));
    }

    // ------------------------------------------------- Cross-report consistency

    public function test_revenue_expense_and_net_match_profit_and_loss(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $this->postIncome($mawa, null, 5000);
        $this->postExpense($mawa, null, 2000);

        $from = '2026-01-01';
        $to = now()->toDateString();

        $data = $this->dashboard()->summary((int) $mawa->id, null, $from, $to);
        $pnl = $this->reports()->profitAndLoss((int) $mawa->id, null, $from, $to);

        $this->assertSame($pnl['total_income'], $data['summary']['revenue']);
        $this->assertSame($pnl['total_expense'], $data['summary']['expenses']);
        $this->assertSame($pnl['net'], $data['summary']['net']);
        $this->assertSame(5000.0, $data['summary']['revenue']);
        $this->assertSame(2000.0, $data['summary']['expenses']);
        $this->assertSame(3000.0, $data['summary']['net']);
    }

    public function test_receivable_and_payable_match_receivables_payables_service(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $this->postSaleToCustomer($mawa, null, 5000);
        $this->postPurchaseFromSupplier($mawa, null, 3000);

        $to = now()->toDateString();

        $data = $this->dashboard()->summary((int) $mawa->id, null, '2026-01-01', $to);
        $arp = app(ReceivablesPayablesService::class)->totals((int) $mawa->id, null, $to);

        $this->assertSame($arp['receivable'], $data['receivables']);
        $this->assertSame($arp['payable'], $data['payables']);
        $this->assertSame(5000.0, $data['receivables']);
        $this->assertSame(3000.0, $data['payables']);
        $this->assertSame(5000.0, $data['arp_aging']['customers']['total']);
        $this->assertSame(3000.0, $data['arp_aging']['suppliers']['total']);
    }

    public function test_cash_balance_matches_cash_bank_report(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $this->postIncome($mawa, null, 8000);
        $this->postExpense($mawa, null, 3000);

        $to = now()->toDateString();

        $data = $this->dashboard()->summary((int) $mawa->id, null, '2026-01-01', $to);
        $cashReport = app(FinancialReportService::class)->cashBankSummary((int) $mawa->id, null, $to);

        $this->assertSame(round($cashReport->sum('balance'), 4), $data['cash']['total_closing']);
        $this->assertSame(5000.0, $data['cash']['total_closing']);
    }

    public function test_cash_bank_flows_open_plus_inflow_minus_outflow_equals_closing(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        // July: income 1000 (inflow), expense 400 (outflow) → closing 600
        $this->postIncome($mawa, null, 1000, '2026-07-15');
        $this->postExpense($mawa, null, 400, '2026-07-20');

        // August: income 300 (inflow)
        $this->postIncome($mawa, null, 300, '2026-08-05');

        $data = $this->dashboard()->summary((int) $mawa->id, null, '2026-08-01', '2026-08-20');

        $this->assertSame(600.0, $data['cash']['total_opening']);
        $this->assertSame(300.0, $data['cash']['total_inflow']);
        $this->assertSame(0.0, $data['cash']['total_outflow']);
        $this->assertSame(900.0, $data['cash']['total_closing']);
    }

    public function test_reversed_journals_do_not_inflate_totals(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $journal = $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => null,
            'journal_date' => now()->toDateString(),
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => [
                ['coa_id' => $this->coaId((int) $mawa->id, null, '1001'), 'debit' => 7000, 'credit' => 0],
                ['coa_id' => $this->coaId((int) $mawa->id, null, '4001'), 'debit' => 0, 'credit' => 7000],
            ],
        ]);

        $this->posting()->reverse($journal, (int) $mawa->id);
        $this->postIncome($mawa, null, 2500);

        $data = $this->dashboard()->summary((int) $mawa->id, null, '2026-01-01', now()->toDateString());

        $this->assertSame(2500.0, $data['summary']['revenue']);
        $this->assertSame(2500.0, $data['cash']['total_closing']);
        $this->assertSame(0, $data['recent_journals']->where('status', 'reversed')->count());
    }

    // ------------------------------------------------- Tenant / branch isolation

    public function test_tenant_isolation_dashboard_exposes_no_other_institute_data(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $this->setupAccounting($mawa);
        $this->setupAccounting($tutu);

        $this->postIncome($mawa, null, 2500);
        $this->postIncome($tutu, null, 7500);

        $owner = $this->owner('step13-tenant@example.test');
        $this->assign($owner, $mawa, 'institute-owner');
        $this->assign($owner, $tutu, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->get(route('accounting.dashboard'))
            ->assertOk()
            ->assertSee('2,500.00')
            ->assertDontSee('7,500.00');

        $this->asUser($owner, (int) $tutu->id)
            ->get(route('accounting.dashboard'))
            ->assertOk()
            ->assertSee('7,500.00')
            ->assertDontSee('2,500.00');
    }

    public function test_branch_manager_dashboard_is_branch_scoped(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $branchA = $this->branch($mawa, 'Step13 Branch A');
        $branchB = $this->branch($mawa, 'Step13 Branch B');
        $this->setupAccounting($mawa, (int) $branchA->id);
        $this->setupAccounting($mawa, (int) $branchB->id);

        $this->postIncome($mawa, (int) $branchA->id, 4000);
        $this->postIncome($mawa, (int) $branchB->id, 6000);

        $manager = $this->staff('step13-branch@example.test');
        $this->assign($manager, $mawa, 'branch-manager', ['branch_id' => $branchA->id]);

        $this->asUser($manager, (int) $mawa->id)
            ->get(route('accounting.dashboard'))
            ->assertOk()
            ->assertSee('4,000.00')
            ->assertDontSee('6,000.00');
    }

    public function test_owner_branch_filter_is_validated_against_institute_branches(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $branchA = $this->branch($mawa, 'Step13 Branch A');
        $this->setupAccounting($mawa, (int) $branchA->id);
        $this->setupAccounting($mawa);

        $this->postIncome($mawa, (int) $branchA->id, 4000);
        $this->postIncome($mawa, null, 2000);

        $owner = $this->owner('step13-branchfilter@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        // Valid branch filter: only branch A amounts.
        $this->asUser($owner, (int) $mawa->id)
            ->get(route('accounting.dashboard', ['branch_id' => $branchA->id]))
            ->assertOk()
            ->assertSee('4,000.00')
            ->assertDontSee('2,000.00');

        // Forged/foreign branch id falls back to the acting scope (all branches).
        $this->asUser($owner, (int) $mawa->id)
            ->get(route('accounting.dashboard', ['branch_id' => 999999]))
            ->assertOk()
            ->assertSee('4,000.00')
            ->assertSee('2,000.00');
    }

    public function test_recent_journals_only_show_posted_records_in_scope(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $this->postIncome($mawa, null, 1000);

        // A draft that must never appear.
        $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => null,
            'journal_date' => now()->toDateString(),
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => [
                ['coa_id' => $this->coaId((int) $mawa->id, null, '1001'), 'debit' => 555, 'credit' => 0],
                ['coa_id' => $this->coaId((int) $mawa->id, null, '4001'), 'debit' => 0, 'credit' => 555],
            ],
        ], null, false);

        $data = $this->dashboard()->summary((int) $mawa->id, null, '2026-01-01', now()->toDateString());

        $this->assertTrue($data['recent_journals']->isNotEmpty());
        $this->assertSame(0, $data['recent_journals']->where('status', 'draft')->count());
        $this->assertSame(0, $data['recent_journals']->where('status', 'reversed')->count());
        $this->assertGreaterThanOrEqual(1, $data['recent_journals']->where('status', 'posted')->count());
        $this->assertSame(1000.0, $data['recent_journals']->first()->debit);
    }

    // ------------------------------------------------- Filters

    public function test_custom_date_range_filter_works(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $this->postIncome($mawa, null, 700, '2026-07-01');
        $this->postIncome($mawa, null, 300, '2026-08-01');

        $july = $this->dashboard()->summary((int) $mawa->id, null, '2026-07-01', '2026-07-31');
        $august = $this->dashboard()->summary((int) $mawa->id, null, '2026-08-01', '2026-08-20');

        $this->assertSame(700.0, $july['summary']['revenue']);
        $this->assertSame(300.0, $august['summary']['revenue']);

        $owner = $this->owner('step13-customrange@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->get(route('accounting.dashboard', ['range' => 'custom', 'from' => '2026-08-01', 'to' => '2026-08-20']))
            ->assertOk()
            ->assertSee('300.00');
    }

    public function test_invalid_custom_range_falls_back_to_fiscal_year(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $this->postIncome($mawa, null, 4200, '2026-03-01');

        $owner = $this->owner('step13-badrange@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        // to < from → ignored, falls back to current fiscal year to date.
        $this->asUser($owner, (int) $mawa->id)
            ->get(route('accounting.dashboard', ['range' => 'custom', 'from' => '2026-08-20', 'to' => '2026-08-01']))
            ->assertOk()
            ->assertSee('4,200.00');
    }

    public function test_fiscal_year_filter_uses_selected_year_start(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $service = app(AccountingPeriodService::class);
        $fy2026 = FiscalYear::query()->where('institute_id', $mawa->id)->where('branch_id', null)->firstOrFail();
        $fy2025 = $service->createFiscalYear($mawa->id, null, [
            'name' => 'FY 2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ]);

        $this->postIncome($mawa, null, 4000, '2025-05-01');

        FiscalYear::query()->where('institute_id', $mawa->id)->update(['is_current' => false]);
        FiscalYear::query()->where('id', $fy2026->id)->update(['is_current' => true]);

        $this->postIncome($mawa, null, 5000, '2026-06-01');

        // Current fiscal year (2026) → only 2026 income.
        $data2026 = $this->dashboard()->summary((int) $mawa->id, null, '2026-01-01', now()->toDateString());
        $this->assertSame(5000.0, $data2026['summary']['revenue']);

        // Selected 2025 year → 2025 income included.
        $data2025 = $this->dashboard()->summary((int) $mawa->id, null, '2025-01-01', now()->toDateString());
        $this->assertSame(9000.0, $data2025['summary']['revenue']);

        $owner = $this->owner('step13-fyfilter@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->get(route('accounting.dashboard', ['range' => 'fiscal_year', 'fiscal_year_id' => $fy2025->id]))
            ->assertOk()
            ->assertSee('9,000.00');
    }

    public function test_this_month_preset_resolves_to_current_month(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $this->postIncome($mawa, null, 600, now()->format('Y-m-05'));
        $this->postIncome($mawa, null, 999, '2026-01-05');

        $data = $this->dashboard()->summary(
            (int) $mawa->id,
            null,
            now()->copy()->startOfMonth()->toDateString(),
            now()->toDateString(),
        );

        $this->assertSame(600.0, $data['summary']['revenue']);
    }

    // ------------------------------------------------- Edge cases

    public function test_empty_dataset_renders_zero_state(): void
    {
        $tutu = $this->institute('Tutu Center');

        $owner = $this->owner('step13-empty@example.test');
        $this->assign($owner, $tutu, 'institute-owner');

        $this->asUser($owner, (int) $tutu->id)
            ->get(route('accounting.dashboard'))
            ->assertOk()
            ->assertSee('0.00')
            ->assertSee('No open/current fiscal year exists.');
    }

    public function test_closed_period_status_displays_closed_badge(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $year = $this->setupCurrentYearWithPeriods($mawa);

        $covering = AccountingPeriod::query()
            ->where('fiscal_year_id', $year->id)
            ->whereDate('start_date', '<=', now()->toDateString())
            ->whereDate('end_date', '>=', now()->toDateString())
            ->firstOrFail();

        app(AccountingPeriodService::class)->closePeriod($covering, (int) $mawa->id);

        $owner = $this->owner('step13-closed@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->get(route('accounting.dashboard'))
            ->assertOk()
            ->assertSee('CLOSED')
            ->assertSee($covering->name)
            ->assertSee('1 / 12');
    }

    public function test_period_status_warns_when_no_periods_cover_today(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $owner = $this->owner('step13-noperiod@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->get(route('accounting.dashboard'))
            ->assertOk()
            ->assertSee('No open period');
    }

    // ------------------------------------------------- Period status helpers

    protected function setupCurrentYearWithPeriods(Institute $institute, ?int $branchId = null): FiscalYear
    {
        $this->setupAccounting($institute, $branchId);

        $year = FiscalYear::query()
            ->where('institute_id', $institute->id)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        app(AccountingPeriodService::class)->createMonthlyPeriods($year);

        return $year;
    }
}
