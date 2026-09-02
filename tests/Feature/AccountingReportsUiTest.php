<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\AccountingSetupService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class AccountingReportsUiTest extends \Tests\TestCase
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
            'name' => 'Report Owner',
            'first_name' => 'Report',
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

    public function test_trial_balance_renders(): void
    {
        $mawa = $this->institute('Reports TB');
        $this->setupAccounting($mawa);
        $owner = $this->owner('reports-tb@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.reports.trial-balance'))
            ->assertOk()
            ->assertSee('Trial Balance');
    }

    public function test_profit_loss_renders(): void
    {
        $mawa = $this->institute('Reports PnL');
        $this->setupAccounting($mawa);
        $owner = $this->owner('reports-pnl@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.reports.profit-loss'))
            ->assertOk()
            ->assertSee('Profit');
    }

    public function test_balance_sheet_renders(): void
    {
        $mawa = $this->institute('Reports BS');
        $this->setupAccounting($mawa);
        $owner = $this->owner('reports-bs@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.reports.balance-sheet'))
            ->assertOk()
            ->assertSee('Balance Sheet');
    }

    public function test_cash_flow_renders(): void
    {
        $mawa = $this->institute('Reports CF');
        $this->setupAccounting($mawa);
        $owner = $this->owner('reports-cf@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.reports.cash-flow'))
            ->assertOk()
            ->assertSee('Cash Flow');
    }

    public function test_general_ledger_renders(): void
    {
        $mawa = $this->institute('Reports GL');
        $this->setupAccounting($mawa);
        $owner = $this->owner('reports-gl@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.reports.general-ledger'))
            ->assertOk()
            ->assertSee('General Ledger');
    }

    public function test_account_ledger_renders(): void
    {
        $mawa = $this->institute('Reports AL');
        $this->setupAccounting($mawa);
        $owner = $this->owner('reports-al@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.reports.account-ledger'))
            ->assertOk()
            ->assertSee('Account Ledger');
    }

    public function test_cash_bank_renders(): void
    {
        $mawa = $this->institute('Reports CB');
        $this->setupAccounting($mawa);
        $owner = $this->owner('reports-cb@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.reports.cash-bank'))
            ->assertOk()
            ->assertSee('Cash');
    }

    public function test_receivables_report_renders(): void
    {
        $mawa = $this->institute('Reports AR');
        $this->setupAccounting($mawa);
        $owner = $this->owner('reports-ar@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.reports.receivables'))
            ->assertOk()
            ->assertSee('Receivable');
    }

    public function test_payables_report_renders(): void
    {
        $mawa = $this->institute('Reports AP');
        $this->setupAccounting($mawa);
        $owner = $this->owner('reports-ap@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.reports.payables'))
            ->assertOk()
            ->assertSee('Payable');
    }
}
