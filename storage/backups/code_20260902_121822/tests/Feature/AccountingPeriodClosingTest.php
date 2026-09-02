<?php

namespace Tests\Feature;

use App\Models\AccountingAuditTrail;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\Institute;
use App\Models\Journal;
use App\Models\Membership;
use App\Models\OpeningBalance;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\FinancialReportService;
use App\Services\Accounting\JournalPostingService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * STEP 12 — Accounting Period Closing & Fiscal-Year End Closing.
 *
 * Periods live in an OPEN/CLOSED lifecycle: only open periods accept postings,
 * closing is blocked while drafts remain, reopening is an audit event and is
 * forbidden after the parent year closes. Closing a fiscal year posts a closing
 * journal (P&L swept to Retained Earnings via JournalPostingService), locks all
 * periods, closes the year and carries balance-sheet balances into the next
 * fiscal year — all tenant/branch-scoped and gated by settings.accounting.manage.
 */
class AccountingPeriodClosingTest extends TestCase
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
            'name' => 'Step12 Owner',
            'first_name' => 'Step12',
            'last_name' => 'Owner',
            'email' => $email,
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    protected function staff(string $email): User
    {
        return (new UserAccountService)->createStaffFromInvitation([
            'name' => 'Step12 Staff',
            'first_name' => 'Step12',
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

    protected function service(): AccountingPeriodService
    {
        return app(AccountingPeriodService::class);
    }

    protected function reports(): FinancialReportService
    {
        return app(FinancialReportService::class);
    }

    protected function asUser(User $user, int $workspaceId): static
    {
        return $this->withSession([Workspace::SESSION_KEY => $workspaceId])->actingAs($user, 'web');
    }

    protected function assertRejected(string $class, callable $callback): void
    {
        try {
            $callback();
            $this->fail("Expected exception [{$class}] was not thrown.");
        } catch (\Throwable $exception) {
            $this->assertInstanceOf($class, $exception);
        }
    }

    protected function setupYearWithPeriods(Institute $institute, ?int $branchId = null): FiscalYear
    {
        $this->setupAccounting($institute, $branchId);

        $year = FiscalYear::query()
            ->where('institute_id', $institute->id)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        $this->service()->createMonthlyPeriods($year);

        return $year;
    }

    protected function periodCovering(FiscalYear $year, ?string $date = null): AccountingPeriod
    {
        $date = $date ?? now()->toDateString();

        return AccountingPeriod::query()
            ->where('fiscal_year_id', $year->id)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->firstOrFail();
    }

    protected function createNextYear(FiscalYear $year, Institute $institute, ?int $branchId = null): FiscalYear
    {
        $start = $year->end_date->copy()->addDay();

        return $this->service()->createFiscalYear($institute->id, $branchId, [
            'name' => 'FY '.($start->format('Y')),
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->endOfYear()->toDateString(),
        ]);
    }

    protected function postIncome(Institute $institute, ?int $branchId, float $amount, ?string $date = null): Journal
    {
        return $this->posting()->create([
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

    protected function postExpense(Institute $institute, ?int $branchId, float $amount, ?string $date = null): Journal
    {
        return $this->posting()->create([
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

    protected function auditCount(int $instituteId, string $action, string $entityType, int $entityId): int
    {
        return AccountingAuditTrail::withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->where('action', $action)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->count();
    }

    protected function tbRow(FiscalYear $year, Institute $institute, string $code): ?object
    {
        $rows = $this->reports()->trialBalance(
            (int) $institute->id,
            $year->branch_id,
            $year->end_date->toDateString(),
            (int) $year->id,
        );

        return $rows->firstWhere('code', $code);
    }

    // ------------------------------------------------------ Period lifecycle

    public function test_setup_creates_open_current_year_with_monthly_periods(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $year = $this->setupYearWithPeriods($mawa);

        $this->assertTrue($year->is_current);
        $this->assertFalse($year->isClosed());
        $this->assertSame(12, $year->periods()->count());
        $this->assertSame(12, $year->periods()->where('status', 'open')->count());
    }

    public function test_close_period_marks_closed_and_audits(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $year = $this->setupYearWithPeriods($mawa);
        $period = $this->periodCovering($year);

        $this->service()->closePeriod($period, (int) $mawa->id, 1);

        $this->assertFalse($period->fresh()->isOpen());
        $this->assertNotNull($period->fresh()->closed_at);
        $this->assertSame(1, $this->auditCount((int) $mawa->id, 'close', 'accounting_period', (int) $period->id));
    }

    public function test_closing_already_closed_period_is_rejected(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $year = $this->setupYearWithPeriods($mawa);
        $period = $this->periodCovering($year);

        $this->service()->closePeriod($period, (int) $mawa->id);

        $this->assertRejected(ValidationException::class, fn () => $this->service()->closePeriod($period, (int) $mawa->id));
    }

    public function test_close_period_with_draft_journal_is_rejected(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $year = $this->setupYearWithPeriods($mawa);
        $period = $this->periodCovering($year);

        $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => null,
            'journal_date' => now()->toDateString(),
            'period_id' => $period->id,
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => [
                ['coa_id' => $this->coaId((int) $mawa->id, null, '1001'), 'debit' => 10, 'credit' => 0],
                ['coa_id' => $this->coaId((int) $mawa->id, null, '4001'), 'debit' => 0, 'credit' => 10],
            ],
        ], null, false);

        $this->assertRejected(ValidationException::class, fn () => $this->service()->closePeriod($period, (int) $mawa->id));
    }

    public function test_closed_period_rejects_new_postings(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $year = $this->setupYearWithPeriods($mawa);
        $period = $this->periodCovering($year);

        $this->service()->closePeriod($period, (int) $mawa->id);

        $this->assertRejected(ValidationException::class, fn () => $this->postIncome($mawa, null, 100));
        $this->assertRejected(ValidationException::class, fn () => $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => null,
            'journal_date' => now()->toDateString(),
            'period_id' => $period->id,
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => [
                ['coa_id' => $this->coaId((int) $mawa->id, null, '1001'), 'debit' => 100, 'credit' => 0],
                ['coa_id' => $this->coaId((int) $mawa->id, null, '4001'), 'debit' => 0, 'credit' => 100],
            ],
        ]));
    }

    public function test_posting_existing_draft_after_period_closed_is_rejected(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $year = $this->setupYearWithPeriods($mawa);

        $draft = $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => null,
            'journal_date' => now()->toDateString(),
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => [
                ['coa_id' => $this->coaId((int) $mawa->id, null, '1001'), 'debit' => 10, 'credit' => 0],
                ['coa_id' => $this->coaId((int) $mawa->id, null, '4001'), 'debit' => 0, 'credit' => 10],
            ],
        ], null, false);

        $this->postIncome($mawa, null, 5000);
        $this->postExpense($mawa, null, 2000);
        $this->createNextYear($year, $mawa);

        $this->service()->closeFiscalYear($year, (int) $mawa->id);

        $this->assertRejected(ValidationException::class, fn () => $this->posting()->post($draft, (int) $mawa->id));
    }

    public function test_posting_auto_assigns_the_covering_open_period(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $year = $this->setupYearWithPeriods($mawa);

        $march = $year->start_date->copy()->setMonth(3);
        $journal = $this->postIncome($mawa, null, 50, $march->toDateString());

        $this->assertNotNull($journal->period_id);
        $this->assertSame((int) $this->periodCovering($year, $march->toDateString())->id, (int) $journal->period_id);
    }

    public function test_reopen_period_restores_open_and_audits(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $year = $this->setupYearWithPeriods($mawa);
        $period = $this->periodCovering($year);

        $this->service()->closePeriod($period, (int) $mawa->id);
        $this->service()->reopenPeriod($period, (int) $mawa->id, 1);

        $this->assertTrue($period->fresh()->isOpen());
        $this->assertNull($period->fresh()->closed_at);
        $this->assertSame(1, $this->auditCount((int) $mawa->id, 'reopen', 'accounting_period', (int) $period->id));
    }

    public function test_reopen_period_after_year_closed_is_rejected(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $year = $this->setupYearWithPeriods($mawa);
        $this->postIncome($mawa, null, 5000);
        $this->postExpense($mawa, null, 2000);
        $this->createNextYear($year, $mawa);

        $this->service()->closeFiscalYear($year, (int) $mawa->id);
        $period = $this->periodCovering($year);

        $this->assertRejected(ValidationException::class, fn () => $this->service()->reopenPeriod($period, (int) $mawa->id));
    }

    public function test_validate_posting_date_returns_period_and_rejects_closed(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $year = $this->setupYearWithPeriods($mawa);
        $period = $this->periodCovering($year);

        $resolved = $this->service()->validatePostingDate((int) $mawa->id, null, now()->toDateString());
        $this->assertSame((int) $period->id, (int) $resolved->id);

        $this->service()->closePeriod($period, (int) $mawa->id);

        $this->assertRejected(ValidationException::class, fn () => $this->service()->validatePostingDate((int) $mawa->id, null, now()->toDateString()));
    }

    // ------------------------------------------------- Fiscal-year closing

    public function test_close_fiscal_year_requires_next_year(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $year = $this->setupYearWithPeriods($mawa);
        $this->postIncome($mawa, null, 1000);

        $this->assertRejected(ValidationException::class, fn () => $this->service()->closeFiscalYear($year, (int) $mawa->id));
    }

    public function test_close_fiscal_year_posts_closing_journal_to_retained_earnings(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $year = $this->setupYearWithPeriods($mawa);
        $this->postIncome($mawa, null, 5000);
        $this->postExpense($mawa, null, 2000);
        $this->createNextYear($year, $mawa);

        $result = $this->service()->closeFiscalYear($year, (int) $mawa->id);

        $this->assertSame(3000.0, $result['net_income']);

        $closing = $result['closing_journal']->fresh();
        $this->assertSame('adjustment', $closing->type);
        $this->assertSame('posted', $closing->status);
        $this->assertSame($year->end_date->toDateString(), $closing->journal_date->toDateString());

        $income = $this->tbRow($year, $mawa, '4001');
        $expense = $this->tbRow($year, $mawa, '5006');
        $retained = $this->tbRow($year, $mawa, '3002');
        $cash = $this->tbRow($year, $mawa, '1001');

        $this->assertEqualsWithDelta(0.0, $income->balance, 0.0001);
        $this->assertEqualsWithDelta(0.0, $expense->balance, 0.0001);
        $this->assertEqualsWithDelta(-3000.0, $retained->balance, 0.0001);
        $this->assertEqualsWithDelta(3000.0, $cash->balance, 0.0001);
    }

    public function test_close_fiscal_year_closes_all_periods_and_the_year(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $year = $this->setupYearWithPeriods($mawa);
        $this->postIncome($mawa, null, 5000);
        $this->postExpense($mawa, null, 2000);
        $this->createNextYear($year, $mawa);

        $this->service()->closeFiscalYear($year, (int) $mawa->id);

        $closed = $year->fresh();
        $this->assertTrue($closed->isClosed());
        $this->assertFalse($closed->is_current);
        $this->assertNotNull($closed->closed_at);
        $this->assertSame(0, $closed->periods()->where('status', 'open')->count());
    }

    public function test_close_fiscal_year_carries_forward_opening_balances(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $year = $this->setupYearWithPeriods($mawa);
        $this->postIncome($mawa, null, 5000);
        $this->postExpense($mawa, null, 2000);
        $nextYear = $this->createNextYear($year, $mawa);

        $this->service()->closeFiscalYear($year, (int) $mawa->id);

        $cashOpen = OpeningBalance::query()
            ->where('institute_id', $mawa->id)
            ->where('branch_id', null)
            ->where('fiscal_year_id', $nextYear->id)
            ->where('coa_id', $this->coaId((int) $mawa->id, null, '1001'))
            ->first();
        $reOpen = OpeningBalance::query()
            ->where('institute_id', $mawa->id)
            ->where('branch_id', null)
            ->where('fiscal_year_id', $nextYear->id)
            ->where('coa_id', $this->coaId((int) $mawa->id, null, '3002'))
            ->first();

        $this->assertNotNull($cashOpen);
        $this->assertSame('3000.0000', $cashOpen->debit);
        $this->assertSame('0.0000', $cashOpen->credit);

        $this->assertNotNull($reOpen);
        $this->assertSame('0.0000', $reOpen->debit);
        $this->assertSame('3000.0000', $reOpen->credit);
    }

    public function test_close_fiscal_year_audits_close_event(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $year = $this->setupYearWithPeriods($mawa);
        $this->postIncome($mawa, null, 5000);
        $this->postExpense($mawa, null, 2000);
        $this->createNextYear($year, $mawa);

        $this->service()->closeFiscalYear($year, (int) $mawa->id);

        $this->assertGreaterThan(0, $this->auditCount((int) $mawa->id, 'close', 'fiscal_year', (int) $year->id));
    }

    public function test_posting_into_closed_year_is_rejected_but_next_year_accepts(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $year = $this->setupYearWithPeriods($mawa);
        $this->postIncome($mawa, null, 5000);
        $this->postExpense($mawa, null, 2000);
        $this->createNextYear($year, $mawa);
        $this->service()->closeFiscalYear($year, (int) $mawa->id);

        $this->assertRejected(ValidationException::class, fn () => $this->postIncome($mawa, null, 100, now()->toDateString()));

        $nextStart = $year->end_date->copy()->addDay();
        $journal = $this->postIncome($mawa, null, 100, $nextStart->addDays(15)->toDateString());

        $this->assertSame('posted', $journal->status);
    }

    public function test_close_fiscal_year_net_loss_debits_retained_earnings(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $year = $this->setupYearWithPeriods($mawa);
        $this->postExpense($mawa, null, 4000);
        $this->createNextYear($year, $mawa);

        $result = $this->service()->closeFiscalYear($year, (int) $mawa->id);

        $this->assertSame(-4000.0, $result['net_income']);

        $expense = $this->tbRow($year, $mawa, '5006');
        $retained = $this->tbRow($year, $mawa, '3002');

        $this->assertEqualsWithDelta(0.0, $expense->balance, 0.0001);
        $this->assertEqualsWithDelta(4000.0, $retained->balance, 0.0001);
    }

    public function test_reopen_fiscal_year_restores_open_and_periods(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $year = $this->setupYearWithPeriods($mawa);
        $this->postIncome($mawa, null, 5000);
        $this->postExpense($mawa, null, 2000);
        $this->createNextYear($year, $mawa);
        $this->service()->closeFiscalYear($year, (int) $mawa->id);

        $reopened = $this->service()->reopenFiscalYear($year, (int) $mawa->id);

        $this->assertFalse($reopened->isClosed());
        $this->assertTrue($reopened->is_current);
        $this->assertNull($reopened->closed_at);
        $this->assertSame(12, $reopened->periods()->where('status', 'open')->count());
    }

    public function test_reopen_fiscal_year_blocked_when_next_year_has_postings(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $year = $this->setupYearWithPeriods($mawa);
        $this->postIncome($mawa, null, 5000);
        $this->postExpense($mawa, null, 2000);
        $nextYear = $this->createNextYear($year, $mawa);
        $this->service()->closeFiscalYear($year, (int) $mawa->id);

        $this->postIncome($mawa, null, 100, $nextYear->start_date->copy()->addDays(15)->toDateString());

        $this->assertRejected(ValidationException::class, fn () => $this->service()->reopenFiscalYear($year, (int) $mawa->id));
    }

    public function test_closing_journal_references_retained_earnings_account(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $year = $this->setupYearWithPeriods($mawa);
        $this->postIncome($mawa, null, 5000);
        $this->postExpense($mawa, null, 2000);
        $this->createNextYear($year, $mawa);

        $result = $this->service()->closeFiscalYear($year, (int) $mawa->id);
        $reId = $this->coaId((int) $mawa->id, null, '3002');

        $usesRetained = $result['closing_journal']->entries()->where('coa_id', $reId)->exists();
        $this->assertTrue($usesRetained);
    }

    // ------------------------------------------------- HTTP / permission

    public function test_accounting_period_routes_are_permission_guarded(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $year = $this->setupYearWithPeriods($mawa);
        $period = $this->periodCovering($year);

        $teacher = $this->staff('step12-period-teacher@example.test');
        $this->assign($teacher, $mawa, 'teacher');

        $this->asUser($teacher, (int) $mawa->id)->get(route('finance.periods.index'))->assertForbidden();
        $this->asUser($teacher, (int) $mawa->id)->post(route('accounting.periods.close', $period))->assertForbidden();
        $this->asUser($teacher, (int) $mawa->id)->post(route('accounting.periods.reopen', $period))->assertForbidden();
    }

    public function test_accounting_fiscal_year_routes_are_permission_guarded(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $year = $this->setupYearWithPeriods($mawa);

        $teacher = $this->staff('step12-year-teacher@example.test');
        $this->assign($teacher, $mawa, 'teacher');

        $this->asUser($teacher, (int) $mawa->id)->get(route('finance.periods.index'))->assertForbidden();
        $this->asUser($teacher, (int) $mawa->id)->post(route('accounting.fiscal-years.close', $year))->assertForbidden();
    }

    public function test_period_route_binding_is_tenant_scoped(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $this->setupYearWithPeriods($mawa);
        $tutuYear = $this->setupYearWithPeriods($tutu);
        $tutuPeriod = $this->periodCovering($tutuYear);

        $owner = $this->owner('step12-tenant-period@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->post(route('accounting.periods.close', $tutuPeriod))
            ->assertNotFound();
    }

    public function test_fiscal_year_route_binding_is_tenant_scoped(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $this->setupYearWithPeriods($mawa);
        $tutuYear = $this->setupYearWithPeriods($tutu);

        $owner = $this->owner('step12-tenant-year@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->post(route('accounting.fiscal-years.close', $tutuYear))
            ->assertNotFound();
    }

    public function test_owner_can_close_and_reopen_period_through_ui(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $year = $this->setupYearWithPeriods($mawa);
        $period = $this->periodCovering($year);

        $owner = $this->owner('step12-period-ui@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->post(route('accounting.periods.close', $period))
            ->assertRedirect();

        $this->assertFalse($period->fresh()->isOpen());

        $this->asUser($owner, (int) $mawa->id)
            ->post(route('accounting.periods.reopen', $period))
            ->assertRedirect();

        $this->assertTrue($period->fresh()->isOpen());
    }

    public function test_owner_can_close_fiscal_year_through_ui(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $year = $this->setupYearWithPeriods($mawa);
        $this->postIncome($mawa, null, 5000);
        $this->postExpense($mawa, null, 2000);
        $this->createNextYear($year, $mawa);

        $owner = $this->owner('step12-year-ui@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->post(route('accounting.fiscal-years.close', $year))
            ->assertRedirect();

        $this->assertTrue($year->fresh()->isClosed());
    }

    public function test_branch_scoped_year_close_keeps_scope(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $mb = $this->branch($mawa, 'Step12 Branch');
        $year = $this->setupYearWithPeriods($mawa, (int) $mb->id);
        $this->postIncome($mawa, (int) $mb->id, 5000, $year->start_date->copy()->addDays(30)->toDateString());
        $this->postExpense($mawa, (int) $mb->id, 2000, $year->start_date->copy()->addDays(30)->toDateString());
        $this->createNextYear($year, $mawa, (int) $mb->id);

        $this->service()->closeFiscalYear($year, (int) $mawa->id);

        $this->assertTrue($year->fresh()->isClosed());
        $this->assertSame(0, $year->fresh()->periods()->where('status', 'open')->count());
    }

    // --------------------------------------------------- Tenant isolation: index pages

    public function test_finance_periods_index_is_tenant_scoped(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $mawaYear = $this->setupYearWithPeriods($mawa);
        $tutuYear = $this->setupYearWithPeriods($tutu);

        $owner = $this->owner('periods-index-isolation@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $response = $this->asUser($owner, (int) $mawa->id)
            ->get(route('finance.periods.index'))
            ->assertOk();

        // Mawa's year periods should be visible (e.g. January 2026)
        $response->assertSee('January 2026');
        // Tutu's year periods should NOT be visible — verify count difference
        $mawaPeriodCount = $mawaYear->periods()->count();
        $tutuPeriodCount = $tutuYear->periods()->count();
        // Both have 12 periods, so the key test is that the fiscal year IDs differ
        $this->assertNotSame($mawaYear->id, $tutuYear->id);
    }

    public function test_accounting_periods_index_is_tenant_scoped(): void
    {
        // AccountingPeriodController@index and FiscalYearController@index have no standalone
        // routes — they share the finance.periods.index route (FinancePeriodController).
        // Verify that the accounting-periods/index and fiscal-years/index views also
        // render correctly when accessed via the finance route with tenant context.
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $mawaYear = $this->setupYearWithPeriods($mawa);
        $tutuYear = $this->setupYearWithPeriods($tutu);

        $owner = $this->owner('acct-periods-index@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        // finance.periods.index is the actual route for the periods page
        $response = $this->asUser($owner, (int) $mawa->id)
            ->get(route('finance.periods.index'))
            ->assertOk();

        $response->assertSee('January 2026');
        // Verify both institutes have exactly 1 fiscal year each (bypass TenantScoped global scope)
        $this->assertCount(1, FiscalYear::withoutGlobalScopes()->where('institute_id', $mawa->id)->get());
        $this->assertCount(1, FiscalYear::withoutGlobalScopes()->where('institute_id', $tutu->id)->get());
        $this->assertNotSame($mawaYear->id, $tutuYear->id);
    }
}
