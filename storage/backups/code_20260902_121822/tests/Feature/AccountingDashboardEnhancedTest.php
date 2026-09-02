<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\Institute;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\AccountingDashboardService;
use App\Services\Accounting\AccountingSetupService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class AccountingDashboardEnhancedTest extends \Tests\TestCase
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
            'name' => 'Dash Owner',
            'first_name' => 'Dash',
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

    public function test_dashboard_service_returns_budget_utilization(): void
    {
        $mawa = $this->institute('Dash Budget');
        $this->setupAccounting($mawa);
        $owner = $this->owner('dash-budget@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $svc = app(AccountingDashboardService::class);
        $today = now()->toDateString();
        $result = $svc->summary($mawa->id, null, $today, $today);

        $this->assertArrayHasKey('budget_utilization', $result);
        $this->assertArrayHasKey('total_budget', $result['budget_utilization']);
        $this->assertArrayHasKey('total_actual', $result['budget_utilization']);
        $this->assertArrayHasKey('utilization_pct', $result['budget_utilization']);
        $this->assertIsNumeric($result['budget_utilization']['utilization_pct']);
    }

    public function test_dashboard_service_returns_pending_approvals(): void
    {
        $mawa = $this->institute('Dash Approvals');
        $this->setupAccounting($mawa);
        $owner = $this->owner('dash-approvals@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $svc = app(AccountingDashboardService::class);
        $today = now()->toDateString();
        $result = $svc->summary($mawa->id, null, $today, $today);

        $this->assertArrayHasKey('pending_approvals', $result);
        $this->assertIsInt($result['pending_approvals']);
        $this->assertSame(0, $result['pending_approvals']);
    }

    public function test_pending_approvals_increments_with_request(): void
    {
        $mawa = $this->institute('Dash Count');
        $this->setupAccounting($mawa);
        $owner = $this->owner('dash-count@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $wfSvc = app(\App\Services\Accounting\ApprovalWorkflowService::class);
        $wf = $wfSvc->createWorkflow($mawa->id, [
            'name' => 'Test WF',
            'module' => 'expense',
            'amount_from' => 0,
            'amount_to' => 999999,
        ], [['approver_role_id' => $this->roleId('institute-owner')]], $owner->id);

        $wfSvc->submitRequest($mawa->id, $wf, 'Expense', 1, 50000, $owner->id);

        $svc = app(AccountingDashboardService::class);
        $today = now()->toDateString();
        $result = $svc->summary($mawa->id, null, $today, $today);

        $this->assertSame(1, $result['pending_approvals']);
    }

    public function test_budget_utilization_zero_when_no_budgets(): void
    {
        $mawa = $this->institute('Dash NoBudget');
        $this->setupAccounting($mawa);
        $owner = $this->owner('dash-nobudget@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $svc = app(AccountingDashboardService::class);
        $today = now()->toDateString();
        $result = $svc->summary($mawa->id, null, $today, $today);

        $this->assertSame(0, $result['budget_utilization']['total_budget']);
        $this->assertSame(0, $result['budget_utilization']['total_actual']);
        $this->assertSame(0, $result['budget_utilization']['utilization_pct']);
    }

    public function test_dashboard_includes_all_expected_keys(): void
    {
        $mawa = $this->institute('Dash Keys');
        $this->setupAccounting($mawa);
        $owner = $this->owner('dash-keys@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $svc = app(AccountingDashboardService::class);
        $today = now()->toDateString();
        $result = $svc->summary($mawa->id, null, $today, $today);

        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('receivables', $result);
        $this->assertArrayHasKey('payables', $result);
        $this->assertArrayHasKey('cash', $result);
        $this->assertArrayHasKey('arp_aging', $result);
        $this->assertArrayHasKey('monthly', $result);
        $this->assertArrayHasKey('top_accounts', $result);
        $this->assertArrayHasKey('recent_journals', $result);
        $this->assertArrayHasKey('period_status', $result);
        $this->assertArrayHasKey('budget_utilization', $result);
        $this->assertArrayHasKey('pending_approvals', $result);
    }
}
