<?php

namespace Tests\Feature;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
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

class ApprovalWorkflowUiTest extends \Tests\TestCase
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
            'name' => 'Approval UI Owner',
            'first_name' => 'Approval',
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

    // ─── Tests ─────────────────────────────────────────────────

    public function test_index_renders(): void
    {
        $mawa = $this->institute('Approval UI Index');
        $this->setupAccounting($mawa);
        $owner = $this->owner('approval-ui-index@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.approvals.index'))
            ->assertOk()
            ->assertSee('Approval Workflows');
    }

    public function test_create_renders(): void
    {
        $mawa = $this->institute('Approval UI Create');
        $this->setupAccounting($mawa);
        $owner = $this->owner('approval-ui-create@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.approvals.create'))
            ->assertOk()
            ->assertSee('Create Approval Workflow');
    }

    public function test_store_creates_workflow(): void
    {
        $mawa = $this->institute('Approval UI Store');
        $this->setupAccounting($mawa);
        $owner = $this->owner('approval-ui-store@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $roleId = $this->roleId('institute-owner');

        $this->asUser($owner, $membership->institution_id)
            ->post(route('accounting.approvals.store'), [
                'name' => 'Journal Approval',
                'module' => 'payment',
                'amount_from' => 0,
                'amount_to' => 100000,
                'approver_role_ids' => [$roleId],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('approval_workflows', [
            'institute_id' => $mawa->id,
            'name' => 'Journal Approval',
            'module' => 'payment',
        ]);
    }

    public function test_show_renders(): void
    {
        $mawa = $this->institute('Approval UI Show');
        $this->setupAccounting($mawa);
        $owner = $this->owner('approval-ui-show@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $roleId = $this->roleId('institute-owner');

        $wf = ApprovalWorkflow::create([
            'institute_id' => $mawa->id,
            'created_by' => $owner->id,
            'name' => 'Show Test Workflow',
            'module' => 'expense',
            'amount_from' => 0,
            'amount_to' => 50000,
            'is_active' => true,
        ]);

        ApprovalStep::create([
            'workflow_id' => $wf->id,
            'institute_id' => $mawa->id,
            'step_order' => 1,
            'approver_role_id' => $roleId,
        ]);

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.approvals.show', $wf->id))
            ->assertOk()
            ->assertSee('Show Test Workflow');
    }

    public function test_inbox_renders(): void
    {
        $mawa = $this->institute('Approval UI Inbox');
        $this->setupAccounting($mawa);
        $owner = $this->owner('approval-ui-inbox@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.approvals.inbox'))
            ->assertOk()
            ->assertSee('Approval Inbox');
    }
}
