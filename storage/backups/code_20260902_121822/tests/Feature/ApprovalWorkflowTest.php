<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\Institute;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\ApprovalWorkflowService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ApprovalWorkflowTest extends \Tests\TestCase
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
            'name' => 'WF Owner',
            'first_name' => 'WF',
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

    public function test_create_workflow_with_steps(): void
    {
        $mawa = $this->institute('WF Create');
        $this->setupAccounting($mawa);
        $owner = $this->owner('wf-create@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $svc = app(ApprovalWorkflowService::class);
        $workflow = $svc->createWorkflow($mawa->id, [
            'name' => 'Expense Approval > 50000',
            'module' => 'expense',
            'amount_from' => 50000,
            'amount_to' => 999999,
        ], [
            ['approver_role_id' => $this->roleId('accountant')],
            ['approver_role_id' => $this->roleId('institute-owner')],
        ], $owner->id);

        $this->assertNotNull($workflow);
        $this->assertSame('expense', $workflow->module);
        $this->assertSame(2, $workflow->steps()->count());
    }

    public function test_submit_request(): void
    {
        $mawa = $this->institute('WF Submit');
        $this->setupAccounting($mawa);
        $owner = $this->owner('wf-submit@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $svc = app(ApprovalWorkflowService::class);
        $workflow = $svc->createWorkflow($mawa->id, [
            'name' => 'Payment Approval',
            'module' => 'payment',
            'amount_from' => 0,
            'amount_to' => 999999,
        ], [
            ['approver_role_id' => $this->roleId('institute-owner')],
        ], $owner->id);

        $request = $svc->submitRequest($mawa->id, $workflow, 'Payment', 1, 75000, $owner->id);

        $this->assertSame('pending_approval', $request->status);
        $this->assertSame(1, $request->current_step);
    }

    public function test_single_step_approve(): void
    {
        $mawa = $this->institute('WF Approve');
        $this->setupAccounting($mawa);
        $owner = $this->owner('wf-approve@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $svc = app(ApprovalWorkflowService::class);
        $workflow = $svc->createWorkflow($mawa->id, [
            'name' => 'Journal Approval',
            'module' => 'journal_adjustment',
            'amount_from' => 0,
            'amount_to' => 999999,
        ], [
            ['approver_role_id' => $this->roleId('institute-owner')],
        ], $owner->id);

        $request = $svc->submitRequest($mawa->id, $workflow, 'Journal', 1, 5000, $owner->id);
        $approved = $svc->approve($request, $owner->id, 'Looks good');

        $this->assertSame('approved', $approved->status);
        $this->assertNotNull($approved->resolved_at);
    }

    public function test_multi_step_approve(): void
    {
        $mawa = $this->institute('WF Multi');
        $this->setupAccounting($mawa);
        $owner = $this->owner('wf-multi@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $svc = app(ApprovalWorkflowService::class);
        $workflow = $svc->createWorkflow($mawa->id, [
            'name' => 'Expense 2-Step',
            'module' => 'expense',
            'amount_from' => 0,
            'amount_to' => 999999,
        ], [
            ['approver_role_id' => $this->roleId('accountant')],
            ['approver_role_id' => $this->roleId('institute-owner')],
        ], $owner->id);

        $request = $svc->submitRequest($mawa->id, $workflow, 'Expense', 1, 100000, $owner->id);

        // Step 1 approve
        $afterStep1 = $svc->approve($request, $owner->id);
        $this->assertSame('pending_approval', $afterStep1->status);
        $this->assertSame(2, $afterStep1->current_step);

        // Step 2 approve
        $afterStep2 = $svc->approve($afterStep1, $owner->id);
        $this->assertSame('approved', $afterStep2->status);
    }

    public function test_reject_stops_workflow(): void
    {
        $mawa = $this->institute('WF Reject');
        $this->setupAccounting($mawa);
        $owner = $this->owner('wf-reject@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $svc = app(ApprovalWorkflowService::class);
        $workflow = $svc->createWorkflow($mawa->id, [
            'name' => 'Rejectable',
            'module' => 'payment',
            'amount_from' => 0,
            'amount_to' => 999999,
        ], [
            ['approver_role_id' => $this->roleId('institute-owner')],
        ], $owner->id);

        $request = $svc->submitRequest($mawa->id, $workflow, 'Payment', 1, 25000, $owner->id);
        $rejected = $svc->reject($request, $owner->id, 'Too expensive');

        $this->assertSame('rejected', $rejected->status);
        $this->assertNotNull($rejected->resolved_at);
    }

    public function test_tenant_isolation(): void
    {
        $mawa = $this->institute('WF Tenant A');
        $other = $this->institute('WF Tenant B');
        $this->setupAccounting($mawa);
        $this->setupAccounting($other);

        $ownerA = $this->owner('wf-tenanta@example.test');
        $ownerB = $this->owner('wf-tenantb@example.test');
        (new MembershipService)->assign($ownerA, $mawa->id, $this->roleId('institute-owner'));
        (new MembershipService)->assign($ownerB, $other->id, $this->roleId('institute-owner'));

        $svc = app(ApprovalWorkflowService::class);

        $wfA = $svc->createWorkflow($mawa->id, [
            'name' => 'Tenant A WF',
            'module' => 'expense',
            'amount_from' => 0,
            'amount_to' => 999999,
        ], [['approver_role_id' => $this->roleId('institute-owner')]], $ownerA->id);

        $wfB = $svc->createWorkflow($other->id, [
            'name' => 'Tenant B WF',
            'module' => 'expense',
            'amount_from' => 0,
            'amount_to' => 999999,
        ], [['approver_role_id' => $this->roleId('institute-owner')]], $ownerB->id);

        $reqA = $svc->submitRequest($mawa->id, $wfA, 'Expense', 1, 50000, $ownerA->id);
        $reqB = $svc->submitRequest($other->id, $wfB, 'Expense', 1, 30000, $ownerB->id);

        $svc->approve($reqA, $ownerA->id);

        $reqAFresh = ApprovalRequest::find($reqA->id);
        $reqBFresh = ApprovalRequest::find($reqB->id);

        $this->assertSame('approved', $reqAFresh->status);
        $this->assertSame('pending_approval', $reqBFresh->status);
    }
}
