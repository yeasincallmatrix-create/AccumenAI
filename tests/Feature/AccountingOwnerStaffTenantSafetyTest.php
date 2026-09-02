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
use App\Models\StatementSnapshot;
use App\Models\User;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\InvoiceService;
use App\Services\Accounting\JournalPostingService;
use App\Services\Accounting\PartyService;
use App\Services\Accounting\PaymentService;
use App\Services\Accounting\ReceivablesPayablesService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * STEP 8 — Accounting Engine Integration: Owner/Staff + Tenant/Branch Safety.
 *
 * The Global Accounting Engine (Steps 1-5) must respect the STEP 7 Owner/Staff
 * account architecture and the Tenant/Branch context:
 *
 * - Owner and staff (global) accounts reach finance through the verified
 *   workspace membership; teachers are denied.
 * - The acting branch of a global staff account comes from the ACTIVE
 *   membership's branch (never from request input), so branch-scoped staff
 *   cannot accidentally write institute-wide rows or read other branches.
 * - The posting engine re-verifies institute AND branch ownership of every
 *   referenced CoA account, party, payment method, fiscal year/period, and
 *   rejects cross-institute reverse/post/void at the service boundary.
 * - Every accounting read path stays tenant-safe: journals, AR/AP, audit
 *   trail, opening balances and statement snapshots.
 */
class AccountingOwnerStaffTenantSafetyTest extends TestCase
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
            'name' => 'Step8 Owner',
            'first_name' => 'Step8',
            'last_name' => 'Owner',
            'email' => $email,
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    protected function staff(string $email): User
    {
        return (new UserAccountService)->createStaffFromInvitation([
            'name' => 'Step8 Staff',
            'first_name' => 'Step8',
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

    protected function coaId(int $instituteId, int $branchId, string $code): int
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

    /**
     * @return array<string, mixed>
     */
    protected function journalPayload(Institute $institute, ?int $branchId, int $cashId, int $incomeId, float $amount): array
    {
        return [
            'institute_id' => $institute->id,
            'branch_id' => $branchId,
            'journal_date' => now()->toDateString(),
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => [
                ['coa_id' => $cashId, 'debit' => $amount, 'credit' => 0],
                ['coa_id' => $incomeId, 'debit' => 0, 'credit' => $amount],
            ],
        ];
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

    // ------------------------------------------------------------ Owner / staff access

    public function test_owner_has_full_finance_access(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $owner = $this->owner('step8-owner@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)->get('/finance')->assertOk();
        $this->asUser($owner, (int) $mawa->id)->get('/finance/journals')->assertOk();
        $this->asUser($owner, (int) $mawa->id)->get('/finance/reports/trial-balance')->assertOk();
    }

    public function test_accountant_staff_has_full_finance_access(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $accountant = $this->staff('step8-accountant@example.test');
        $this->assign($accountant, $mawa, 'accountant');

        $this->asUser($accountant, (int) $mawa->id)->get('/finance')->assertOk();
        $this->asUser($accountant, (int) $mawa->id)->get('/finance/reports/income-statement')->assertOk();
    }

    public function test_teacher_staff_is_forbidden_from_finance(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $teacher = $this->staff('step8-teacher@example.test');
        $this->assign($teacher, $mawa, 'teacher');

        $this->asUser($teacher, (int) $mawa->id)->get('/finance')->assertForbidden();
        $this->asUser($teacher, (int) $mawa->id)->get('/finance/reports/trial-balance')->assertForbidden();
    }

    public function test_owner_switching_memberships_changes_finance_context(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $owner = $this->owner('step8-owner-multi@example.test');
        $this->assign($owner, $mawa, 'institute-owner');
        $this->assign($owner, $tutu, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)->get('/finance')->assertOk();
        $this->assertSame((int) $mawa->id, TenantContext::id());

        $this->asUser($owner, (int) $tutu->id)->get('/finance')->assertOk();
        $this->assertSame((int) $tutu->id, TenantContext::id());
    }

    // ------------------------------------------------------------ Branch scoping (Gap A)

    public function test_accountant_membership_branch_scopes_created_journals(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $branchA = $this->branch($mawa, 'Branch A');
        $this->setupAccounting($mawa, (int) $branchA->id);

        $accountant = $this->staff('step8-accountant-branch@example.test');
        $this->assign($accountant, $mawa, 'accountant', ['branch_id' => $branchA->id]);

        $cash = $this->coaId((int) $mawa->id, (int) $branchA->id, '1001');
        $tuition = $this->coaId((int) $mawa->id, (int) $branchA->id, '4001');

        $this->asUser($accountant, (int) $mawa->id)
            ->post('/finance/journals', [
                'journal_date' => now()->toDateString(),
                'type' => 'journal',
                'currency_id' => $this->currencyId(),
                'entries' => [
                    ['coa_id' => $cash, 'debit' => 100, 'credit' => 0, 'memo' => 'test'],
                    ['coa_id' => $tuition, 'debit' => 0, 'credit' => 100, 'memo' => 'test'],
                ],
            ])
            ->assertRedirect();

        $journal = Journal::withoutGlobalScopes()
            ->where('institute_id', $mawa->id)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($journal);
        $this->assertSame((int) $branchA->id, (int) $journal->branch_id);
    }

    // ------------------------------------------------------------ Tenant isolation

    public function test_cross_institute_journal_is_not_visible(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $mb = $this->branch($mawa, 'Mawa Branch');
        $this->setupAccounting($mawa, (int) $mb->id);

        $owner = $this->owner('step8-cross-view@example.test');
        $this->assign($owner, $mawa, 'institute-owner');
        $this->assign($owner, $tutu, 'institute-owner');

        $journal = $this->posting()->create($this->journalPayload(
            $mawa,
            (int) $mb->id,
            $this->coaId((int) $mawa->id, (int) $mb->id, '1001'),
            $this->coaId((int) $mawa->id, (int) $mb->id, '4001'),
            10
        ));

        $this->asUser($owner, (int) $mawa->id)
            ->get(route('finance.journals.show', $journal))
            ->assertOk();

        $this->asUser($owner, (int) $tutu->id)
            ->get(route('finance.journals.show', $journal))
            ->assertNotFound();
    }

    public function test_forged_workspace_for_unrelated_institute_is_rejected(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $owner = $this->owner('step8-forge@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $tb = $this->branch($tutu, 'Tutu Branch');
        $this->setupAccounting($tutu, (int) $tb->id);

        $journal = $this->posting()->create($this->journalPayload(
            $tutu,
            (int) $tb->id,
            $this->coaId((int) $tutu->id, (int) $tb->id, '1001'),
            $this->coaId((int) $tutu->id, (int) $tb->id, '4001'),
            10
        ));

        $this->withSession([Workspace::SESSION_KEY => $tutu->id])
            ->actingAs($owner, 'web')
            ->get('/finance')
            ->assertForbidden();

        $this->withSession([Workspace::SESSION_KEY => $tutu->id])
            ->actingAs($owner, 'web')
            ->get(route('finance.journals.show', $journal))
            ->assertForbidden();

        $this->assertNull(Workspace::id());
    }

    // ------------------------------------------------------------ Service-level ownership

    public function test_cross_institute_account_is_rejected_in_journal_payload(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $mb = $this->branch($mawa, 'Mawa Branch');
        $tb = $this->branch($tutu, 'Tutu Branch');
        $this->setupAccounting($mawa, (int) $mb->id);
        $this->setupAccounting($tutu, (int) $tb->id);

        $cashM = $this->coaId((int) $mawa->id, (int) $mb->id, '1001');
        $tuitionT = $this->coaId((int) $tutu->id, (int) $tb->id, '4001');

        $this->assertRejected(ValidationException::class, fn () => $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => $mb->id,
            'journal_date' => now()->toDateString(),
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => [
                ['coa_id' => $cashM, 'debit' => 100, 'credit' => 0],
                ['coa_id' => $tuitionT, 'debit' => 0, 'credit' => 100],
            ],
        ]));
    }

    public function test_cross_institute_party_is_rejected_in_journal_payload(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $mb = $this->branch($mawa, 'Mawa Branch');
        $tb = $this->branch($tutu, 'Tutu Branch');
        $this->setupAccounting($mawa, (int) $mb->id);
        $this->setupAccounting($tutu, (int) $tb->id);

        $arM = $this->coaId((int) $mawa->id, (int) $mb->id, '1100');
        $tuitionM = $this->coaId((int) $mawa->id, (int) $mb->id, '4001');

        $partyT = app(PartyService::class)->create($tutu->id, (int) $tb->id, [
            'type' => 'customer',
            'name' => 'Tutu Customer',
            'phone' => '0172'.rand(100000, 999999),
        ]);

        $this->assertRejected(ValidationException::class, fn () => $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => $mb->id,
            'journal_date' => now()->toDateString(),
            'type' => 'sale',
            'currency_id' => $this->currencyId(),
            'entries' => [
                ['coa_id' => $arM, 'debit' => 100, 'credit' => 0, 'party_id' => $partyT->id],
                ['coa_id' => $tuitionM, 'debit' => 0, 'credit' => 100],
            ],
        ]));
    }

    public function test_cross_institute_and_cross_branch_payment_methods_are_rejected(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $mb = $this->branch($mawa, 'Branch A');
        $mb2 = $this->branch($mawa, 'Branch B');
        $tb = $this->branch($tutu, 'Tutu Branch');
        $this->setupAccounting($mawa, (int) $mb->id);
        $this->setupAccounting($mawa, (int) $mb2->id);
        $this->setupAccounting($tutu, (int) $tb->id);

        $party = app(PartyService::class)->create($mawa->id, (int) $mb->id, [
            'type' => 'customer',
            'name' => 'Branch A Customer',
            'phone' => '0173'.rand(100000, 999999),
        ]);

        $invoice = app(InvoiceService::class)->create($mawa->id, (int) $mb->id, [
            'party_id' => $party->id,
            'invoice_type' => 'other',
            'discount' => 0,
            'items' => [
                ['description' => 'Service', 'amount' => 150],
            ],
        ]);

        $tutuMethod = (int) PaymentMethod::withoutGlobalScopes()
            ->where('institute_id', $tutu->id)
            ->value('id');
        $branchBMethod = (int) PaymentMethod::withoutGlobalScopes()
            ->where('institute_id', $mawa->id)
            ->where('branch_id', $mb2->id)
            ->value('id');

        $this->assertRejected(ValidationException::class, fn () => app(PaymentService::class)->record($mawa->id, (int) $mb->id, [
            'invoice_id' => $invoice->id,
            'amount' => 100,
            'payment_method' => 'bank',
            'payment_method_id' => $tutuMethod,
        ]));

        $this->assertRejected(ValidationException::class, fn () => app(PaymentService::class)->record($mawa->id, (int) $mb->id, [
            'invoice_id' => $invoice->id,
            'amount' => 100,
            'payment_method' => 'bank',
            'payment_method_id' => $branchBMethod,
        ]));
    }

    public function test_cross_institute_and_cross_branch_periods_are_rejected(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $mb = $this->branch($mawa, 'Branch A');
        $mb2 = $this->branch($mawa, 'Branch B');
        $tb = $this->branch($tutu, 'Tutu Branch');
        $this->setupAccounting($mawa, (int) $mb->id);
        $this->setupAccounting($mawa, (int) $mb2->id);
        $this->setupAccounting($tutu, (int) $tb->id);

        $cashM = $this->coaId((int) $mawa->id, (int) $mb->id, '1001');
        $tuitionM = $this->coaId((int) $mawa->id, (int) $mb->id, '4001');

        foreach ([$mb, $mb2, $tb] as $branchRef) {
            $year = FiscalYear::query()->where('institute_id', $branchRef->institute_id)->where('branch_id', $branchRef->id)->firstOrFail();
            app(AccountingPeriodService::class)->createMonthlyPeriods($year);
        }

        $tutuPeriod = AccountingPeriod::withoutGlobalScopes()->where('institute_id', $tutu->id)->where('branch_id', $tb->id)->first();
        $branchBPeriod = AccountingPeriod::withoutGlobalScopes()->where('institute_id', $mawa->id)->where('branch_id', $mb2->id)->first();
        $this->assertNotNull($tutuPeriod);
        $this->assertNotNull($branchBPeriod);

        $payload = fn (int $periodId) => [
            'institute_id' => $mawa->id,
            'branch_id' => $mb->id,
            'period_id' => $periodId,
            'journal_date' => now()->toDateString(),
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => [
                ['coa_id' => $cashM, 'debit' => 100, 'credit' => 0],
                ['coa_id' => $tuitionM, 'debit' => 0, 'credit' => 100],
            ],
        ];

        $this->assertRejected(ModelNotFoundException::class, fn () => $this->posting()->create($payload((int) $tutuPeriod->id), null, false));
        $this->assertRejected(ModelNotFoundException::class, fn () => $this->posting()->create($payload((int) $branchBPeriod->id), null, false));
    }

    public function test_no_cross_tenant_fiscal_year_fallback(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $mb = $this->branch($mawa, 'Branch A');
        $tb = $this->branch($tutu, 'Tutu Branch');
        $this->setupAccounting($mawa, (int) $mb->id);
        $this->setupAccounting($tutu, (int) $tb->id);

        $cashM = $this->coaId((int) $mawa->id, (int) $mb->id, '1001');
        $tuitionM = $this->coaId((int) $mawa->id, (int) $mb->id, '4001');

        // A date outside MAWA's fiscal year is rejected even though Tutu has
        // an open fiscal year covering the same date: no cross-tenant fallback.
        $this->assertRejected(ValidationException::class, fn () => $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => $mb->id,
            'journal_date' => '2019-01-01',
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => [
                ['coa_id' => $cashM, 'debit' => 100, 'credit' => 0],
                ['coa_id' => $tuitionM, 'debit' => 0, 'credit' => 100],
            ],
        ], null, false));
    }

    public function test_cross_institute_reverse_post_and_void_are_rejected(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $mb = $this->branch($mawa, 'Branch A');
        $this->setupAccounting($mawa, (int) $mb->id);

        $cash = $this->coaId((int) $mawa->id, (int) $mb->id, '1001');
        $tuition = $this->coaId((int) $mawa->id, (int) $mb->id, '4001');

        $posted = $this->posting()->create($this->journalPayload($mawa, (int) $mb->id, $cash, $tuition, 10));
        $draft = $this->posting()->create($this->journalPayload($mawa, (int) $mb->id, $cash, $tuition, 10), null, false);

        $this->assertRejected(\LogicException::class, fn () => $this->posting()->reverse($posted, (int) $tutu->id));
        $this->assertSame('posted', $posted->fresh()->status);

        $this->assertRejected(\LogicException::class, fn () => $this->posting()->post($draft, (int) $tutu->id));
        $this->assertSame('draft', $draft->fresh()->status);

        $this->assertRejected(\LogicException::class, fn () => $this->posting()->void($draft, (int) $tutu->id));
        $this->assertSame('draft', $draft->fresh()->status);
    }

    public function test_branch_id_must_belong_to_institute(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $mb = $this->branch($mawa, 'Branch A');
        $tb = $this->branch($tutu, 'Tutu Branch');
        $this->setupAccounting($mawa, (int) $mb->id);

        $cash = $this->coaId((int) $mawa->id, (int) $mb->id, '1001');
        $tuition = $this->coaId((int) $mawa->id, (int) $mb->id, '4001');

        $this->assertRejected(ValidationException::class, fn () => $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => $tb->id,
            'journal_date' => now()->toDateString(),
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => [
                ['coa_id' => $cash, 'debit' => 100, 'credit' => 0],
                ['coa_id' => $tuition, 'debit' => 0, 'credit' => 100],
            ],
        ]));
    }

    public function test_branch_a_b_journals_are_isolated_and_cross_branch_ids_rejected(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $branchA = $this->branch($mawa, 'Branch A');
        $branchB = $this->branch($mawa, 'Branch B');
        $this->setupAccounting($mawa, (int) $branchA->id);
        $this->setupAccounting($mawa, (int) $branchB->id);

        $cashA = $this->coaId((int) $mawa->id, (int) $branchA->id, '1001');
        $tuitionA = $this->coaId((int) $mawa->id, (int) $branchA->id, '4001');
        $cashB = $this->coaId((int) $mawa->id, (int) $branchB->id, '1001');
        $tuitionB = $this->coaId((int) $mawa->id, (int) $branchB->id, '4001');

        $journalA = $this->posting()->create($this->journalPayload($mawa, (int) $branchA->id, $cashA, $tuitionA, 50));
        $journalB = $this->posting()->create($this->journalPayload($mawa, (int) $branchB->id, $cashB, $tuitionB, 75));

        $partyB = app(PartyService::class)->create($mawa->id, (int) $branchB->id, [
            'type' => 'customer',
            'name' => 'Branch B Customer',
            'phone' => '0174'.rand(100000, 999999),
        ]);

        BranchContext::set((int) $branchA->id);
        $visibleA = Journal::query()->where('institute_id', $mawa->id)->pluck('id');
        $this->assertTrue($visibleA->contains($journalA->id));
        $this->assertFalse($visibleA->contains($journalB->id));

        BranchContext::set((int) $branchB->id);
        $visibleB = Journal::query()->where('institute_id', $mawa->id)->pluck('id');
        $this->assertFalse($visibleB->contains($journalA->id));
        $this->assertTrue($visibleB->contains($journalB->id));

        BranchContext::clear();

        // Cross-branch CoA account in a journal payload is rejected.
        $this->assertRejected(ValidationException::class, fn () => $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => $branchA->id,
            'journal_date' => now()->toDateString(),
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => [
                ['coa_id' => $cashA, 'debit' => 100, 'credit' => 0],
                ['coa_id' => $tuitionB, 'debit' => 0, 'credit' => 100],
            ],
        ]));

        // Cross-branch party in a journal payload is rejected.
        $this->assertRejected(ValidationException::class, fn () => $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => $branchA->id,
            'journal_date' => now()->toDateString(),
            'type' => 'sale',
            'currency_id' => $this->currencyId(),
            'entries' => [
                ['coa_id' => $cashA, 'debit' => 100, 'credit' => 0, 'party_id' => $partyB->id],
                ['coa_id' => $tuitionA, 'debit' => 0, 'credit' => 100],
            ],
        ]));
    }

    // ------------------------------------------------------------ Listing scope

    public function test_workspace_switch_scopes_finance_journal_listings(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $mb = $this->branch($mawa, 'Mawa Branch');
        $tb = $this->branch($tutu, 'Tutu Branch');
        $this->setupAccounting($mawa, (int) $mb->id);
        $this->setupAccounting($tutu, (int) $tb->id);

        $owner = $this->owner('step8-list@example.test');
        $this->assign($owner, $mawa, 'institute-owner');
        $this->assign($owner, $tutu, 'institute-owner');

        $journalM = $this->posting()->create($this->journalPayload(
            $mawa,
            (int) $mb->id,
            $this->coaId((int) $mawa->id, (int) $mb->id, '1001'),
            $this->coaId((int) $mawa->id, (int) $mb->id, '4001'),
            10
        ));
        $journalT = $this->posting()->create($this->journalPayload(
            $tutu,
            (int) $tb->id,
            $this->coaId((int) $tutu->id, (int) $tb->id, '1001'),
            $this->coaId((int) $tutu->id, (int) $tb->id, '4001'),
            20
        ));

        $this->asUser($owner, (int) $mawa->id)
            ->get('/finance/journals')
            ->assertOk()
            ->assertSee($journalM->journal_no)
            ->assertDontSee($journalT->journal_no);

        $this->asUser($owner, (int) $tutu->id)
            ->get('/finance/journals')
            ->assertOk()
            ->assertSee($journalT->journal_no)
            ->assertDontSee($journalM->journal_no);
    }

    // ------------------------------------------------------------ Derived data safety

    public function test_receivables_payables_are_tenant_safe(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $mb = $this->branch($mawa, 'Mawa Branch');
        $tb = $this->branch($tutu, 'Tutu Branch');
        $this->setupAccounting($mawa, (int) $mb->id);
        $this->setupAccounting($tutu, (int) $tb->id);

        $customerM = app(PartyService::class)->create($mawa->id, (int) $mb->id, [
            'type' => 'customer',
            'name' => 'Mawa Customer',
            'phone' => '0175'.rand(100000, 999999),
        ]);
        $customerT = app(PartyService::class)->create($tutu->id, (int) $tb->id, [
            'type' => 'customer',
            'name' => 'Tutu Customer',
            'phone' => '0176'.rand(100000, 999999),
        ]);

        $this->posting()->create([
            'institute_id' => $mawa->id,
            'branch_id' => $mb->id,
            'journal_date' => now()->toDateString(),
            'type' => 'sale',
            'currency_id' => $this->currencyId(),
            'entries' => [
                ['coa_id' => $this->coaId((int) $mawa->id, (int) $mb->id, '1100'), 'debit' => 500, 'credit' => 0, 'party_id' => $customerM->id],
                ['coa_id' => $this->coaId((int) $mawa->id, (int) $mb->id, '4001'), 'debit' => 0, 'credit' => 500],
            ],
        ]);

        $this->posting()->create([
            'institute_id' => $tutu->id,
            'branch_id' => $tb->id,
            'journal_date' => now()->toDateString(),
            'type' => 'sale',
            'currency_id' => $this->currencyId(),
            'entries' => [
                ['coa_id' => $this->coaId((int) $tutu->id, (int) $tb->id, '1100'), 'debit' => 250, 'credit' => 0, 'party_id' => $customerT->id],
                ['coa_id' => $this->coaId((int) $tutu->id, (int) $tb->id, '4001'), 'debit' => 0, 'credit' => 250],
            ],
        ]);

        $rp = new ReceivablesPayablesService;

        $mawaBalances = $rp->customerBalances((int) $mawa->id, (int) $mb->id);
        $this->assertTrue($mawaBalances->contains(fn ($row) => (int) $row->id === (int) $customerM->id));
        $this->assertFalse($mawaBalances->contains(fn ($row) => (int) $row->id === (int) $customerT->id));

        $tutuBalances = $rp->customerBalances((int) $tutu->id, (int) $tb->id);
        $this->assertFalse($tutuBalances->contains(fn ($row) => (int) $row->id === (int) $customerM->id));
        $this->assertTrue($tutuBalances->contains(fn ($row) => (int) $row->id === (int) $customerT->id));

        $this->assertSame(500.0, round((float) $rp->totals((int) $mawa->id, (int) $mb->id)['net'], 4));
        $this->assertSame(250.0, round((float) $rp->totals((int) $tutu->id, (int) $tb->id)['net'], 4));
    }

    public function test_audit_trail_is_tenant_scoped(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $mb = $this->branch($mawa, 'Mawa Branch');
        $this->setupAccounting($mawa, (int) $mb->id);

        $journal = $this->posting()->create($this->journalPayload(
            $mawa,
            (int) $mb->id,
            $this->coaId((int) $mawa->id, (int) $mb->id, '1001'),
            $this->coaId((int) $mawa->id, (int) $mb->id, '4001'),
            10
        ));

        $this->assertGreaterThan(0, AccountingAuditTrail::withoutGlobalScopes()
            ->where('institute_id', $mawa->id)
            ->where('entity_type', 'journal')
            ->where('entity_id', $journal->id)
            ->count());

        TenantContext::set((int) $mawa->id);
        $this->assertTrue(AccountingAuditTrail::query()->where('entity_id', $journal->id)->exists());

        TenantContext::set((int) $tutu->id);
        $this->assertFalse(AccountingAuditTrail::query()->where('entity_id', $journal->id)->exists());
    }

    public function test_opening_balances_and_statement_snapshots_are_tenant_scoped(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $mb = $this->branch($mawa, 'Mawa Branch');
        $tb = $this->branch($tutu, 'Tutu Branch');
        $this->setupAccounting($mawa, (int) $mb->id);
        $this->setupAccounting($tutu, (int) $tb->id);

        $fyM = FiscalYear::query()->where('institute_id', $mawa->id)->firstOrFail();
        $fyT = FiscalYear::query()->where('institute_id', $tutu->id)->firstOrFail();

        $obM = OpeningBalance::create([
            'institute_id' => $mawa->id,
            'branch_id' => $mb->id,
            'fiscal_year_id' => $fyM->id,
            'coa_id' => $this->coaId((int) $mawa->id, (int) $mb->id, '1001'),
            'debit' => 100,
            'credit' => 0,
        ]);
        $obT = OpeningBalance::create([
            'institute_id' => $tutu->id,
            'branch_id' => $tb->id,
            'fiscal_year_id' => $fyT->id,
            'coa_id' => $this->coaId((int) $tutu->id, (int) $tb->id, '1001'),
            'debit' => 200,
            'credit' => 0,
        ]);

        $snapM = StatementSnapshot::create([
            'institute_id' => $mawa->id,
            'branch_id' => $mb->id,
            'fiscal_year_id' => $fyM->id,
            'statement_type' => 'income_statement',
            'as_of_date' => now()->toDateString(),
            'payload' => ['total_income' => 100],
            'checksum' => str_repeat('0', 64),
            'generated_at' => now(),
        ]);
        $snapT = StatementSnapshot::create([
            'institute_id' => $tutu->id,
            'branch_id' => $tb->id,
            'fiscal_year_id' => $fyT->id,
            'statement_type' => 'income_statement',
            'as_of_date' => now()->toDateString(),
            'payload' => ['total_income' => 200],
            'checksum' => str_repeat('0', 64),
            'generated_at' => now(),
        ]);

        TenantContext::set((int) $mawa->id);
        $this->assertTrue(OpeningBalance::query()->where('id', $obM->id)->exists());
        $this->assertFalse(OpeningBalance::query()->where('id', $obT->id)->exists());
        $this->assertTrue(StatementSnapshot::query()->where('id', $snapM->id)->exists());
        $this->assertFalse(StatementSnapshot::query()->where('id', $snapT->id)->exists());

        TenantContext::set((int) $tutu->id);
        $this->assertFalse(OpeningBalance::query()->where('id', $obM->id)->exists());
        $this->assertTrue(OpeningBalance::query()->where('id', $obT->id)->exists());
        $this->assertFalse(StatementSnapshot::query()->where('id', $snapM->id)->exists());
        $this->assertTrue(StatementSnapshot::query()->where('id', $snapT->id)->exists());
    }
}
