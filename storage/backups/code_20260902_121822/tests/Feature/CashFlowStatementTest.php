<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Institute;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\JournalPostingService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class CashFlowStatementTest extends \Tests\TestCase
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
            'name' => 'CF Test Owner',
            'first_name' => 'CF',
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

    // ─── Test 1: Empty returns zeros ───────────────────────────────
    public function test_empty_institute_returns_zeros(): void
    {
        $mawa = $this->institute('CF Empty');
        $this->setupAccounting($mawa);
        $owner = $this->owner('cf-empty@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $mawa->id)
            ->get(route('accounting.reports.cash-flow'))
            ->assertOk()
            ->assertViewHas('statement', function ($stmt) {
                return $stmt['operating'] === 0.0
                    && $stmt['investing'] === 0.0
                    && $stmt['financing'] === 0.0
                    && $stmt['net_change'] === 0.0
                    && $stmt['unclassified_amount'] === 0.0;
            });
    }

    // ─── Test 2: Operating inflow (cash received from customer) ────
    public function test_operating_inflow(): void
    {
        $mawa = $this->institute('CF Op In');
        $this->setupAccounting($mawa);
        $owner = $this->owner('cf-op-in@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $cash = $this->coa($mawa, '1001');
        $revenue = $this->coa($mawa, '4001');

        // Tag revenue as operating
        ChartOfAccount::withoutGlobalScopes()->where('id', $revenue->id)->update(['cash_flow_category' => 'operating']);

        $this->postJournal($mawa, null, '2026-03-15', [
            ['coa_id' => $cash->id, 'debit' => 5000, 'credit' => 0],
            ['coa_id' => $revenue->id, 'debit' => 0, 'credit' => 5000],
        ]);

        $this->asUser($owner, $mawa->id)
            ->get(route('accounting.reports.cash-flow', ['from' => '2026-03-01', 'to' => '2026-03-31']))
            ->assertOk()
            ->assertViewHas('statement', function ($stmt) {
                return $stmt['operating'] === 5000.0;
            });
    }

    // ─── Test 3: Operating outflow (cash paid for expense) ─────────
    public function test_operating_outflow(): void
    {
        $mawa = $this->institute('CF Op Out');
        $this->setupAccounting($mawa);
        $owner = $this->owner('cf-op-out@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $cash = $this->coa($mawa, '1001');
        $expense = $this->coa($mawa, '5001');

        ChartOfAccount::withoutGlobalScopes()->where('id', $expense->id)->update(['cash_flow_category' => 'operating']);

        $this->postJournal($mawa, null, '2026-03-16', [
            ['coa_id' => $expense->id, 'debit' => 2000, 'credit' => 0],
            ['coa_id' => $cash->id, 'debit' => 0, 'credit' => 2000],
        ]);

        $this->asUser($owner, $mawa->id)
            ->get(route('accounting.reports.cash-flow', ['from' => '2026-03-01', 'to' => '2026-03-31']))
            ->assertOk()
            ->assertViewHas('statement', function ($stmt) {
                // Cash paid: −2000
                return $stmt['operating'] === -2000.0;
            });
    }

    // ─── Test 4: Investing outflow (equipment purchase) ────────────
    public function test_investing_outflow(): void
    {
        $mawa = $this->institute('CF Inv Out');
        $this->setupAccounting($mawa);
        $owner = $this->owner('cf-inv-out@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $cash = $this->coa($mawa, '1001');
        $fixedAssets = $this->coa($mawa, '1300');

        ChartOfAccount::withoutGlobalScopes()->where('id', $fixedAssets->id)->update(['cash_flow_category' => 'investing']);

        $this->postJournal($mawa, null, '2026-04-01', [
            ['coa_id' => $fixedAssets->id, 'debit' => 10000, 'credit' => 0],
            ['coa_id' => $cash->id, 'debit' => 0, 'credit' => 10000],
        ]);

        $this->asUser($owner, $mawa->id)
            ->get(route('accounting.reports.cash-flow', ['from' => '2026-04-01', 'to' => '2026-04-30']))
            ->assertOk()
            ->assertViewHas('statement', function ($stmt) {
                return $stmt['investing'] === -10000.0;
            });
    }

    // ─── Test 5: Financing inflow (loan received) ──────────────────
    public function test_financing_inflow(): void
    {
        $mawa = $this->institute('CF Fin In');
        $this->setupAccounting($mawa);
        $owner = $this->owner('cf-fin-in@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $cash = $this->coa($mawa, '1001');
        $loan = $this->coa($mawa, '2003');

        ChartOfAccount::withoutGlobalScopes()->where('id', $loan->id)->update(['cash_flow_category' => 'financing']);

        $this->postJournal($mawa, null, '2026-05-01', [
            ['coa_id' => $cash->id, 'debit' => 50000, 'credit' => 0],
            ['coa_id' => $loan->id, 'debit' => 0, 'credit' => 50000],
        ]);

        $this->asUser($owner, $mawa->id)
            ->get(route('accounting.reports.cash-flow', ['from' => '2026-05-01', 'to' => '2026-05-31']))
            ->assertOk()
            ->assertViewHas('statement', function ($stmt) {
                return $stmt['financing'] === 50000.0;
            });
    }

    // ─── Test 6: Financing outflow (loan repayment) ────────────────
    public function test_financing_outflow(): void
    {
        $mawa = $this->institute('CF Fin Out');
        $this->setupAccounting($mawa);
        $owner = $this->owner('cf-fin-out@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $cash = $this->coa($mawa, '1001');
        $loan = $this->coa($mawa, '2003');

        ChartOfAccount::withoutGlobalScopes()->where('id', $loan->id)->update(['cash_flow_category' => 'financing']);

        $this->postJournal($mawa, null, '2026-05-15', [
            ['coa_id' => $loan->id, 'debit' => 10000, 'credit' => 0],
            ['coa_id' => $cash->id, 'debit' => 0, 'credit' => 10000],
        ]);

        $this->asUser($owner, $mawa->id)
            ->get(route('accounting.reports.cash-flow', ['from' => '2026-05-01', 'to' => '2026-05-31']))
            ->assertOk()
            ->assertViewHas('statement', function ($stmt) {
                return $stmt['financing'] === -10000.0;
            });
    }

    // ─── Test 7: Mixed categories ──────────────────────────────────
    public function test_mixed_categories(): void
    {
        $mawa = $this->institute('CF Mixed');
        $this->setupAccounting($mawa);
        $owner = $this->owner('cf-mixed@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $cash = $this->coa($mawa, '1001');
        $revenue = $this->coa($mawa, '4001');
        $expense = $this->coa($mawa, '5001');
        $fixedAssets = $this->coa($mawa, '1300');
        $loan = $this->coa($mawa, '2003');

        ChartOfAccount::withoutGlobalScopes()->where('id', $revenue->id)->update(['cash_flow_category' => 'operating']);
        ChartOfAccount::withoutGlobalScopes()->where('id', $expense->id)->update(['cash_flow_category' => 'operating']);
        ChartOfAccount::withoutGlobalScopes()->where('id', $fixedAssets->id)->update(['cash_flow_category' => 'investing']);
        ChartOfAccount::withoutGlobalScopes()->where('id', $loan->id)->update(['cash_flow_category' => 'financing']);

        // Operating: +8000 − 3000 = +5000
        $this->postJournal($mawa, null, '2026-06-01', [
            ['coa_id' => $cash->id, 'debit' => 8000, 'credit' => 0],
            ['coa_id' => $revenue->id, 'debit' => 0, 'credit' => 8000],
        ]);
        $this->postJournal($mawa, null, '2026-06-05', [
            ['coa_id' => $expense->id, 'debit' => 3000, 'credit' => 0],
            ['coa_id' => $cash->id, 'debit' => 0, 'credit' => 3000],
        ]);
        // Investing: −15000
        $this->postJournal($mawa, null, '2026-06-10', [
            ['coa_id' => $fixedAssets->id, 'debit' => 15000, 'credit' => 0],
            ['coa_id' => $cash->id, 'debit' => 0, 'credit' => 15000],
        ]);
        // Financing: +20000
        $this->postJournal($mawa, null, '2026-06-15', [
            ['coa_id' => $cash->id, 'debit' => 20000, 'credit' => 0],
            ['coa_id' => $loan->id, 'debit' => 0, 'credit' => 20000],
        ]);

        $this->asUser($owner, $mawa->id)
            ->get(route('accounting.reports.cash-flow', ['from' => '2026-06-01', 'to' => '2026-06-30']))
            ->assertOk()
            ->assertViewHas('statement', function ($stmt) {
                return $stmt['operating'] === 5000.0
                    && $stmt['investing'] === -15000.0
                    && $stmt['financing'] === 20000.0
                    && $stmt['net_change'] === 10000.0
                    && $stmt['unclassified_amount'] === 0.0;
            });
    }

    // ─── Test 8: Net change matches closing minus opening ──────────
    public function test_net_change_equals_closing_minus_opening(): void
    {
        $mawa = $this->institute('CF Net Check');
        $this->setupAccounting($mawa);
        $owner = $this->owner('cf-net@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $cash = $this->coa($mawa, '1001');
        $revenue = $this->coa($mawa, '4001');

        ChartOfAccount::withoutGlobalScopes()->where('id', $revenue->id)->update(['cash_flow_category' => 'operating']);

        $this->postJournal($mawa, null, '2026-07-01', [
            ['coa_id' => $cash->id, 'debit' => 7000, 'credit' => 0],
            ['coa_id' => $revenue->id, 'debit' => 0, 'credit' => 7000],
        ]);

        $this->asUser($owner, $mawa->id)
            ->get(route('accounting.reports.cash-flow', ['from' => '2026-07-01', 'to' => '2026-07-31']))
            ->assertOk()
            ->assertViewHas('statement', function ($stmt) {
                return $stmt['closing'] - $stmt['opening'] === $stmt['net_change'];
            });
    }

    // ─── Test 9: Date range filtering ──────────────────────────────
    public function test_date_range_filtering(): void
    {
        $mawa = $this->institute('CF Date Filter');
        $this->setupAccounting($mawa);
        $owner = $this->owner('cf-date@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $cash = $this->coa($mawa, '1001');
        $revenue = $this->coa($mawa, '4001');

        ChartOfAccount::withoutGlobalScopes()->where('id', $revenue->id)->update(['cash_flow_category' => 'operating']);

        // March entry: included
        $this->postJournal($mawa, null, '2026-03-15', [
            ['coa_id' => $cash->id, 'debit' => 4000, 'credit' => 0],
            ['coa_id' => $revenue->id, 'debit' => 0, 'credit' => 4000],
        ]);
        // April entry: excluded by date filter
        $this->postJournal($mawa, null, '2026-04-15', [
            ['coa_id' => $cash->id, 'debit' => 6000, 'credit' => 0],
            ['coa_id' => $revenue->id, 'debit' => 0, 'credit' => 6000],
        ]);

        $this->asUser($owner, $mawa->id)
            ->get(route('accounting.reports.cash-flow', ['from' => '2026-03-01', 'to' => '2026-03-31']))
            ->assertOk()
            ->assertViewHas('statement', function ($stmt) {
                return $stmt['operating'] === 4000.0;
            });
    }

    // ─── Test 10: Branch-scoped entries only ───────────────────────
    public function test_branch_scoping(): void
    {
        $mawa = $this->institute('CF Branch');
        $branch = Branch::create(['institute_id' => $mawa->id, 'name' => 'CF Branch 1', 'status' => 'active']);
        $this->setupAccounting($mawa, $branch->id);
        $owner = $this->owner('cf-branch@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'), ['branch_id' => $branch->id]);

        $cash = ChartOfAccount::withoutGlobalScopes()
            ->where('institute_id', $mawa->id)
            ->where('branch_id', $branch->id)
            ->where('code', '1001')
            ->firstOrFail();
        $revenue = ChartOfAccount::withoutGlobalScopes()
            ->where('institute_id', $mawa->id)
            ->where('branch_id', $branch->id)
            ->where('code', '4001')
            ->firstOrFail();

        ChartOfAccount::withoutGlobalScopes()->where('id', $revenue->id)->update(['cash_flow_category' => 'operating']);

        $this->postJournal($mawa, $branch->id, '2026-08-01', [
            ['coa_id' => $cash->id, 'debit' => 3000, 'credit' => 0],
            ['coa_id' => $revenue->id, 'debit' => 0, 'credit' => 3000],
        ]);

        $this->asUser($owner, $mawa->id)
            ->get(route('accounting.reports.cash-flow', ['from' => '2026-08-01', 'to' => '2026-08-31']))
            ->assertOk()
            ->assertViewHas('statement', function ($stmt) {
                return $stmt['operating'] === 3000.0;
            });
    }
}
