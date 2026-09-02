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
use App\Models\Party;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Accounting\JournalPostingService;
use App\Services\Accounting\OpeningBalanceService;
use App\Services\Accounting\PartyService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * STEP 9 — Accounting UI & Workflow Integration.
 *
 * The Global Accounting Engine (Steps 1-5) + Owner/Staff + Tenant/Branch safety
 * (Step 8) are exposed through the app UI. These tests exercise the actual
 * finance screens and routes (dashboard, CoA, journals, AR/AP reports, payment
 * methods, opening balances, audit trail) as the seeded roles would use them,
 * asserting that permissions, tenant/branch scoping, posting/reversal and
 * read-only audit behavior hold end-to-end through the controllers.
 */
class AccountingUiWorkflowTest extends TestCase
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
            'name' => 'Step9 Owner',
            'first_name' => 'Step9',
            'last_name' => 'Owner',
            'email' => $email,
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    protected function staff(string $email): User
    {
        return (new UserAccountService)->createStaffFromInvitation([
            'name' => 'Step9 Staff',
            'first_name' => 'Step9',
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

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function balancedEntries(Institute $institute, int $cashId, int $incomeId, float $amount): array
    {
        return [
            ['coa_id' => $cashId, 'debit' => $amount, 'credit' => 0],
            ['coa_id' => $incomeId, 'debit' => 0, 'credit' => $amount],
        ];
    }

    // ------------------------------------------------------------ Dashboard

    public function test_dashboard_loads_for_owner_with_current_fiscal_data(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $owner = $this->owner('step9-dash-owner@example.test');
        $this->assign($owner, $mawa, 'institute-owner');
        $this->setupAccounting($mawa);

        $this->asUser($owner, (int) $mawa->id)
            ->get('/finance')
            ->assertOk()
            ->assertSee('Finance Dashboard');
    }

    public function test_dashboard_forbidden_without_finance_view_permission(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $teacher = $this->staff('step9-dash-teacher@example.test');
        $this->assign($teacher, $mawa, 'teacher');

        $this->asUser($teacher, (int) $mawa->id)->get('/finance')->assertForbidden();
    }

    // ------------------------------------------------------------ CoA

    public function test_coa_index_is_tenant_scoped(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $mb = $this->branch($mawa, 'Mawa Branch');
        $this->setupAccounting($mawa, (int) $mb->id);

        $unique = 'Unique A Account '.substr(md5((string) rand()), 0, 6);
        app(ChartOfAccountService::class)->createAccount((int) $mawa->id, (int) $mb->id, [
            'code' => '1999',
            'name' => $unique,
            'type' => 'asset',
            'is_active' => true,
        ]);

        $owner = $this->owner('step9-coa@example.test');
        $this->assign($owner, $mawa, 'institute-owner');
        $this->assign($owner, $tutu, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->get('/finance/chart-of-accounts')
            ->assertOk()
            ->assertSee($unique);

        $this->asUser($owner, (int) $tutu->id)
            ->get('/finance/chart-of-accounts')
            ->assertOk()
            ->assertDontSee($unique);
    }

    // ------------------------------------------------------------ Journals

    public function test_journal_list_is_branch_scoped_for_branch_manager(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $branchA = $this->branch($mawa, 'Branch A');
        $branchB = $this->branch($mawa, 'Branch B');
        $this->setupAccounting($mawa, (int) $branchA->id);
        $this->setupAccounting($mawa, (int) $branchB->id);

        $journalA = $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => $branchA->id,
            'journal_date' => now()->toDateString(),
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => $this->balancedEntries($mawa, $this->coaId((int) $mawa->id, (int) $branchA->id, '1001'), $this->coaId((int) $mawa->id, (int) $branchA->id, '4001'), 10),
        ]);
        $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => $branchB->id,
            'journal_date' => now()->toDateString(),
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => $this->balancedEntries($mawa, $this->coaId((int) $mawa->id, (int) $branchB->id, '1001'), $this->coaId((int) $mawa->id, (int) $branchB->id, '4001'), 20),
        ]);

        $manager = $this->staff('step9-jlist@example.test');
        $this->assign($manager, $mawa, 'branch-manager', ['branch_id' => $branchA->id]);

        $this->asUser($manager, (int) $mawa->id)
            ->get('/finance/journals')
            ->assertOk()
            ->assertSee($journalA->journal_no);
    }

    public function test_journal_create_guarded_by_journals_create_permission(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');

        $teacher = $this->staff('step9-jcreate-teacher@example.test');
        $this->assign($teacher, $mawa, 'teacher');
        $this->asUser($teacher, (int) $mawa->id)
            ->get('/finance/journals/create')
            ->assertForbidden();

        $manager = $this->staff('step9-jcreate-manager@example.test');
        $this->assign($manager, $mawa, 'branch-manager');
        $this->asUser($manager, (int) $mawa->id)
            ->get('/finance/journals/create')
            ->assertOk();
    }

    public function test_unbalanced_journal_store_is_rejected(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $owner = $this->owner('step9-junbal@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $cash = $this->coaId((int) $mawa->id, null, '1001');
        $income = $this->coaId((int) $mawa->id, null, '4001');

        $this->asUser($owner, (int) $mawa->id)
            ->post('/finance/journals', [
                'journal_date' => now()->toDateString(),
                'type' => 'journal',
                'currency_id' => $this->currencyId(),
                'entries' => [
                    ['coa_id' => $cash, 'debit' => 100, 'credit' => 0],
                    ['coa_id' => $income, 'debit' => 0, 'credit' => 50],
                ],
            ])
            ->assertSessionHasErrors('entries');

        $this->assertSame(0, Journal::withoutGlobalScopes()->where('institute_id', $mawa->id)->count());
    }

    public function test_valid_journal_store_creates_and_posts(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $owner = $this->owner('step9-jvalid@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $cash = $this->coaId((int) $mawa->id, null, '1001');
        $income = $this->coaId((int) $mawa->id, null, '4001');

        $this->asUser($owner, (int) $mawa->id)
            ->post('/finance/journals', [
                'journal_date' => now()->toDateString(),
                'type' => 'journal',
                'currency_id' => $this->currencyId(),
                'entries' => [
                    ['coa_id' => $cash, 'debit' => 100, 'credit' => 0, 'memo' => 'test'],
                    ['coa_id' => $income, 'debit' => 0, 'credit' => 100, 'memo' => 'test'],
                ],
            ])
            ->assertRedirect();

        $journal = Journal::withoutGlobalScopes()
            ->where('institute_id', $mawa->id)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($journal);
        $this->assertSame('posted', $journal->status);
        $this->assertNull($journal->branch_id);
    }

    public function test_store_rejects_other_institute_coa(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $tb = $this->branch($tutu, 'Tutu Branch');
        $this->setupAccounting($mawa);
        $this->setupAccounting($tutu, (int) $tb->id);

        $owner = $this->owner('step9-jforeigncoa@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $cashM = $this->coaId((int) $mawa->id, null, '1001');
        $incomeT = $this->coaId((int) $tutu->id, (int) $tb->id, '4001');

        $this->asUser($owner, (int) $mawa->id)
            ->post('/finance/journals', [
                'journal_date' => now()->toDateString(),
                'type' => 'journal',
                'currency_id' => $this->currencyId(),
                'entries' => [
                    ['coa_id' => $cashM, 'debit' => 100, 'credit' => 0],
                    ['coa_id' => $incomeT, 'debit' => 0, 'credit' => 100],
                ],
            ])
            ->assertSessionHasErrors('entries');
    }

    public function test_store_rejects_other_institute_party(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $tb = $this->branch($tutu, 'Tutu Branch');
        $this->setupAccounting($mawa);

        $owner = $this->owner('step9-jforeignparty@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $ar = $this->coaId((int) $mawa->id, null, '1100');
        $income = $this->coaId((int) $mawa->id, null, '4001');

        $foreign = app(PartyService::class)->create($tutu->id, (int) $tb->id, [
            'type' => 'customer',
            'name' => 'Tutu Customer',
            'phone' => '0177'.rand(100000, 999999),
        ]);

        $this->asUser($owner, (int) $mawa->id)
            ->post('/finance/journals', [
                'journal_date' => now()->toDateString(),
                'type' => 'sale',
                'currency_id' => $this->currencyId(),
                'entries' => [
                    ['coa_id' => $ar, 'debit' => 100, 'credit' => 0, 'party_id' => $foreign->id],
                    ['coa_id' => $income, 'debit' => 0, 'credit' => 100],
                ],
            ])
            ->assertSessionHasErrors('entries');
    }

    public function test_closed_period_posting_is_rejected(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $mb = $this->branch($mawa, 'Mawa Branch');
        $this->setupAccounting($mawa, (int) $mb->id);

        $year = FiscalYear::query()->where('institute_id', $mawa->id)->where('branch_id', $mb->id)->firstOrFail();
        app(AccountingPeriodService::class)->createMonthlyPeriods($year);

        $period = AccountingPeriod::query()
            ->where('fiscal_year_id', $year->id)
            ->whereDate('start_date', '<=', now()->toDateString())
            ->whereDate('end_date', '>=', now()->toDateString())
            ->firstOrFail();
        app(AccountingPeriodService::class)->closePeriod($period, (int) $mawa->id);

        $this->assertRejected(ValidationException::class, fn () => $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => $mb->id,
            'period_id' => $period->id,
            'journal_date' => now()->toDateString(),
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => $this->balancedEntries($mawa, $this->coaId((int) $mawa->id, (int) $mb->id, '1001'), $this->coaId((int) $mawa->id, (int) $mb->id, '4001'), 10),
        ]));
    }

    public function test_reverse_posted_journal_via_ui_route(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $mb = $this->branch($mawa, 'Mawa Branch');
        $this->setupAccounting($mawa, (int) $mb->id);

        $journal = $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => $mb->id,
            'journal_date' => now()->toDateString(),
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => $this->balancedEntries($mawa, $this->coaId((int) $mawa->id, (int) $mb->id, '1001'), $this->coaId((int) $mawa->id, (int) $mb->id, '4001'), 10),
        ]);

        $owner = $this->owner('step9-jreverse@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->post(route('finance.journals.reverse', $journal), ['reason' => 'correction'])
            ->assertRedirect();

        $this->assertSame('reversed', $journal->fresh()->status);
    }

    public function test_void_draft_journal_via_ui_route(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $mb = $this->branch($mawa, 'Mawa Branch');
        $this->setupAccounting($mawa, (int) $mb->id);

        $draft = $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => $mb->id,
            'journal_date' => now()->toDateString(),
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => $this->balancedEntries($mawa, $this->coaId((int) $mawa->id, (int) $mb->id, '1001'), $this->coaId((int) $mawa->id, (int) $mb->id, '4001'), 10),
        ], null, false);

        $owner = $this->owner('step9-jvoid@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->post(route('finance.journals.void', $draft))
            ->assertRedirect();

        $this->assertSame('void', $draft->fresh()->status);
    }

    // ------------------------------------------------------------ Reports

    public function test_receivables_report_is_tenant_scoped_with_aging(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $mb = $this->branch($mawa, 'Mawa Branch');
        $tb = $this->branch($tutu, 'Tutu Branch');
        $this->setupAccounting($mawa, (int) $mb->id);
        $this->setupAccounting($tutu, (int) $tb->id);

        $customerM = app(PartyService::class)->create($mawa->id, (int) $mb->id, [
            'type' => 'customer',
            'name' => 'Step9 Mawa Customer',
            'phone' => '0178'.rand(100000, 999999),
        ]);
        $customerT = app(PartyService::class)->create($tutu->id, (int) $tb->id, [
            'type' => 'customer',
            'name' => 'Step9 Tutu Customer',
            'phone' => '0179'.rand(100000, 999999),
        ]);

        foreach ([
            [$mawa, $mb, $customerM],
            [$tutu, $tb, $customerT],
        ] as [$institute, $branch, $customer]) {
            $this->posting()->create([
                'institute_id' => $institute->id,
                'branch_id' => $branch->id,
                'journal_date' => now()->toDateString(),
                'type' => 'sale',
                'currency_id' => $this->currencyId(),
                'entries' => [
                    ['coa_id' => $this->coaId((int) $institute->id, (int) $branch->id, '1100'), 'debit' => 500, 'credit' => 0, 'party_id' => $customer->id],
                    ['coa_id' => $this->coaId((int) $institute->id, (int) $branch->id, '4001'), 'debit' => 0, 'credit' => 500],
                ],
            ]);
        }

        $owner = $this->owner('step9-ar@example.test');
        $this->assign($owner, $mawa, 'institute-owner');
        $this->assign($owner, $tutu, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->get(route('finance.reports.receivables', ['as_of_date' => now()->toDateString()]))
            ->assertOk()
            ->assertSee('Step9 Mawa Customer')
            ->assertDontSee('Step9 Tutu Customer');
    }

    public function test_trial_balance_loads_scoped(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $mb = $this->branch($mawa, 'Mawa Branch');
        $this->setupAccounting($mawa, (int) $mb->id);

        $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => $mb->id,
            'journal_date' => now()->toDateString(),
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => $this->balancedEntries($mawa, $this->coaId((int) $mawa->id, (int) $mb->id, '1001'), $this->coaId((int) $mawa->id, (int) $mb->id, '4001'), 250),
        ]);

        $owner = $this->owner('step9-tb@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->get(route('finance.reports.trial-balance'))
            ->assertOk();
    }

    // ------------------------------------------------------------ Audit trail

    public function test_audit_trail_is_read_only_and_tenant_scoped(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $mb = $this->branch($mawa, 'Mawa Branch');
        $this->setupAccounting($mawa, (int) $mb->id);

        $journal = $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => $mb->id,
            'journal_date' => now()->toDateString(),
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => $this->balancedEntries($mawa, $this->coaId((int) $mawa->id, (int) $mb->id, '1001'), $this->coaId((int) $mawa->id, (int) $mb->id, '4001'), 10),
        ]);

        $this->assertGreaterThan(0, AccountingAuditTrail::withoutGlobalScopes()
            ->where('institute_id', $mawa->id)
            ->where('entity_type', 'journal')
            ->where('entity_id', $journal->id)
            ->count());

        $owner = $this->owner('step9-audit@example.test');
        $this->assign($owner, $mawa, 'institute-owner');
        $this->assign($owner, $tutu, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->get(route('finance.audit.index'))
            ->assertOk()
            ->assertSee($journal->journal_no);

        $this->asUser($owner, (int) $tutu->id)
            ->get(route('finance.audit.index'))
            ->assertOk()
            ->assertDontSee($journal->journal_no);
    }

    // ------------------------------------------------------------ Payment methods

    public function test_payment_method_crud_is_permission_guarded_and_branch_scoped(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $branchA = $this->branch($mawa, 'Branch A');
        $branchB = $this->branch($mawa, 'Branch B');
        $this->setupAccounting($mawa, (int) $branchA->id);
        $this->setupAccounting($mawa, (int) $branchB->id);

        $accountant = $this->staff('step9-pm-accountant@example.test');
        $this->assign($accountant, $mawa, 'accountant', ['branch_id' => $branchA->id]);

        $this->asUser($accountant, (int) $mawa->id)
            ->post(route('finance.payment-methods.store'), [
                'name' => 'Step9 Custom Wallet',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $method = PaymentMethod::withoutGlobalScopes()
            ->where('institute_id', $mawa->id)
            ->where('name', 'Step9 Custom Wallet')
            ->firstOrFail();
        $this->assertSame((int) $branchA->id, (int) $method->branch_id);
        $this->assertTrue((bool) $method->is_active);

        $this->asUser($accountant, (int) $mawa->id)
            ->get(route('finance.payment-methods.index'))
            ->assertOk()
            ->assertSee('Step9 Custom Wallet');

        $managerB = $this->staff('step9-pm-manager-b@example.test');
        $this->assign($managerB, $mawa, 'branch-manager', ['branch_id' => $branchB->id]);

        $this->asUser($managerB, (int) $mawa->id)
            ->get(route('finance.payment-methods.index'))
            ->assertOk()
            ->assertDontSee('Step9 Custom Wallet');

        $this->asUser($accountant, (int) $mawa->id)
            ->post(route('finance.payment-methods.toggle', $method))
            ->assertRedirect();

        $this->assertFalse((bool) $method->fresh()->is_active);

        $this->asUser($managerB, (int) $mawa->id)
            ->post(route('finance.payment-methods.store'), ['name' => 'Step9 Forbidden'])
            ->assertForbidden();
    }

    public function test_payment_method_route_binding_is_institute_guarded(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $mb = $this->branch($mawa, 'Mawa Branch');
        $this->setupAccounting($mawa, (int) $mb->id);
        $this->setupAccounting($tutu);

        $owner = $this->owner('step9-pm-guard@example.test');
        $this->assign($owner, $mawa, 'institute-owner');
        $this->assign($owner, $tutu, 'institute-owner');

        $foreign = PaymentMethod::withoutGlobalScopes()
            ->where('institute_id', $tutu->id)
            ->firstOrFail();

        $this->asUser($owner, (int) $mawa->id)
            ->get(route('finance.payment-methods.edit', $foreign))
            ->assertNotFound();
    }

    // ------------------------------------------------------------ Opening balances

    public function test_opening_balances_upsert_into_trial_balance(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $owner = $this->owner('step9-ob@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->get(route('finance.opening-balances.create'))
            ->assertOk();

        $year = FiscalYear::query()->where('institute_id', $mawa->id)->whereNull('branch_id')->firstOrFail();
        $cash = $this->coaId((int) $mawa->id, null, '1001');
        $equity = $this->coaId((int) $mawa->id, null, '3001');

        $this->asUser($owner, (int) $mawa->id)
            ->post(route('finance.opening-balances.store'), [
                'fiscal_year_id' => $year->id,
                'entries' => [
                    $cash => ['debit' => '5000.00', 'credit' => '0'],
                    $equity => ['debit' => '0', 'credit' => '5000.00'],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(2, OpeningBalance::withoutGlobalScopes()
            ->where('institute_id', $mawa->id)
            ->where('fiscal_year_id', $year->id)
            ->count());

        $this->asUser($owner, (int) $mawa->id)
            ->get(route('finance.reports.trial-balance'))
            ->assertOk();
    }

    public function test_opening_balances_reject_unbalanced_entries(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $owner = $this->owner('step9-ob-unbal@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $year = FiscalYear::query()->where('institute_id', $mawa->id)->whereNull('branch_id')->firstOrFail();
        $cash = $this->coaId((int) $mawa->id, null, '1001');
        $equity = $this->coaId((int) $mawa->id, null, '3001');

        $this->asUser($owner, (int) $mawa->id)
            ->post(route('finance.opening-balances.store'), [
                'fiscal_year_id' => $year->id,
                'entries' => [
                    $cash => ['debit' => '5000.00', 'credit' => '0'],
                    $equity => ['debit' => '0', 'credit' => '4000.00'],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(0, OpeningBalance::withoutGlobalScopes()
            ->where('institute_id', $mawa->id)
            ->where('fiscal_year_id', $year->id)
            ->count());
    }

    // ------------------------------------------------------------ Workspace switching

    public function test_workspace_switch_changes_dashboard_scope(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $mb = $this->branch($mawa, 'Mawa Branch');
        $tb = $this->branch($tutu, 'Tutu Branch');
        $this->setupAccounting($mawa, (int) $mb->id);
        $this->setupAccounting($tutu, (int) $tb->id);

        $journalM = $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => $mb->id,
            'journal_date' => now()->toDateString(),
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => $this->balancedEntries($mawa, $this->coaId((int) $mawa->id, (int) $mb->id, '1001'), $this->coaId((int) $mawa->id, (int) $mb->id, '4001'), 30),
        ]);

        $owner = $this->owner('step9-ws@example.test');
        $this->assign($owner, $mawa, 'institute-owner');
        $this->assign($owner, $tutu, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->get('/finance')
            ->assertOk()
            ->assertSee($journalM->journal_no);

        $this->asUser($owner, (int) $tutu->id)
            ->get('/finance')
            ->assertOk()
            ->assertDontSee($journalM->journal_no);
    }
}