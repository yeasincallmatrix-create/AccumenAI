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
use App\Services\Accounting\ReceivableService;
use App\Services\MembershipService;
use App\Services\Accounting\PartyService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * STEP 72 — Receivable Aging Tests.
 */
class ReceivableAgingTest extends \Tests\TestCase
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
            'name' => 'AR Owner',
            'first_name' => 'AR',
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
            'type' => 'sale',
            'currency_id' => $this->currencyId(),
            'entries' => $entries,
        ]);
    }

    protected function createCustomer(Institute $institute, string $name, ?int $branchId = null): Party
    {
        return app(PartyService::class)->create($institute->id, $branchId, [
            'name' => $name,
            'type' => 'customer',
        ]);
    }

    // ─── Test 1: Customer with outstanding balance ────────────────
    public function test_customer_statement_shows_balance(): void
    {
        $mawa = $this->institute('AR Balance');
        $this->setupAccounting($mawa);
        $owner = $this->owner('ar-balance@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $customer = $this->createCustomer($mawa, 'Student Alpha');
        $ar = $this->coa($mawa, '1100');
        $revenue = $this->coa($mawa, '4001');

        $this->postJournal($mawa, null, '2026-12-01', [
            ['coa_id' => $ar->id, 'debit' => 10000, 'credit' => 0, 'party_id' => $customer->id],
            ['coa_id' => $revenue->id, 'debit' => 0, 'credit' => 10000],
        ]);

        $svc = app(ReceivableService::class);
        $stmt = $svc->customerStatement($mawa->id, $customer->id, '2026-12-31');

        $this->assertSame(10000.0, $stmt['balance']);
        $this->assertTrue($stmt['transactions']->isNotEmpty());
    }

    // ─── Test 2: Aging buckets ───────────────────────────────────
    public function test_receivables_aging_buckets(): void
    {
        $mawa = $this->institute('AR Aging');
        $this->setupAccounting($mawa);
        $owner = $this->owner('ar-aging@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $customer = $this->createCustomer($mawa, 'Student Beta');
        $ar = $this->coa($mawa, '1100');
        $revenue = $this->coa($mawa, '4001');

        // Invoice 40 days old
        $this->postJournal($mawa, null, '2026-11-01', [
            ['coa_id' => $ar->id, 'debit' => 5000, 'credit' => 0, 'party_id' => $customer->id],
            ['coa_id' => $revenue->id, 'debit' => 0, 'credit' => 5000],
        ]);

        $svc = app(ReceivableService::class);
        $result = $svc->receivablesAging($mawa->id, null, '2026-12-11');

        $this->assertArrayHasKey('customers', $result);
        $this->assertArrayHasKey('totals', $result);
    }

    // ─── Test 3: Empty customer has zero balance ──────────────────
    public function test_empty_customer_zero_balance(): void
    {
        $mawa = $this->institute('AR Empty');
        $this->setupAccounting($mawa);
        $owner = $this->owner('ar-empty@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $customer = $this->createCustomer($mawa, 'Student Gamma');

        $svc = app(ReceivableService::class);
        $stmt = $svc->customerStatement($mawa->id, $customer->id);

        $this->assertSame(0.0, $stmt['balance']);
        $this->assertTrue($stmt['transactions']->isEmpty());
    }

    // ─── Test 4: Branch isolation ─────────────────────────────────
    public function test_branch_isolation(): void
    {
        $mawa = $this->institute('AR Branch');
        $branchA = Branch::create(['institute_id' => $mawa->id, 'name' => 'BA', 'status' => 'active']);
        $this->setupAccounting($mawa, $branchA->id);
        $owner = $this->owner('ar-branch@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $customer = $this->createCustomer($mawa, 'Student Delta', $branchA->id);
        $ar = ChartOfAccount::withoutGlobalScopes()
            ->where('institute_id', $mawa->id)
            ->where('branch_id', $branchA->id)
            ->where('code', '1100')
            ->firstOrFail();
        $revenue = ChartOfAccount::withoutGlobalScopes()
            ->where('institute_id', $mawa->id)
            ->where('branch_id', $branchA->id)
            ->where('code', '4001')
            ->firstOrFail();

        $this->postJournal($mawa, $branchA->id, '2026-12-01', [
            ['coa_id' => $ar->id, 'debit' => 7500, 'credit' => 0, 'party_id' => $customer->id],
            ['coa_id' => $revenue->id, 'debit' => 0, 'credit' => 7500],
        ]);

        $svc = app(ReceivableService::class);
        $stmt = $svc->customerStatement($mawa->id, $customer->id, '2026-12-31');
        $this->assertSame(7500.0, $stmt['balance']);
    }

    // ─── Test 5: Tenant isolation ─────────────────────────────────
    public function test_tenant_isolation(): void
    {
        $mawa = $this->institute('AR Tenant A');
        $other = $this->institute('AR Tenant B');
        $this->setupAccounting($mawa);
        $this->setupAccounting($other);

        $ownerA = $this->owner('ar-tenanta@example.test');
        $ownerB = $this->owner('ar-tenantb@example.test');
        (new MembershipService)->assign($ownerA, $mawa->id, $this->roleId('institute-owner'));
        (new MembershipService)->assign($ownerB, $other->id, $this->roleId('institute-owner'));

        $customerA = $this->createCustomer($mawa, 'Student Epsilon');
        $ar = $this->coa($mawa, '1100');
        $revenue = $this->coa($mawa, '4001');

        $this->postJournal($mawa, null, '2026-12-01', [
            ['coa_id' => $ar->id, 'debit' => 3000, 'credit' => 0, 'party_id' => $customerA->id],
            ['coa_id' => $revenue->id, 'debit' => 0, 'credit' => 3000],
        ]);

        $customerB = $this->createCustomer($other, 'Student Zeta');

        $svc = app(ReceivableService::class);

        $stmtA = $svc->customerStatement($mawa->id, $customerA->id, '2026-12-31');
        $this->assertSame(3000.0, $stmtA['balance']);

        $stmtB = $svc->customerStatement($other->id, $customerB->id, '2026-12-31');
        $this->assertSame(0.0, $stmtB['balance']);
    }
}
