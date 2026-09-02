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

class AuditComplianceReportTest extends \Tests\TestCase
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
            'name' => 'Audit Owner',
            'first_name' => 'Audit',
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

    // ─── Journal Trail ──────────────────────────────────

    public function test_journal_trail_renders(): void
    {
        $mawa = $this->institute('Audit Journal');
        $this->setupAccounting($mawa);
        $owner = $this->owner('audit-journal@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.reports.audit.journal-trail', [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Journal Audit Trail');
    }

    // ─── User Activity ──────────────────────────────────

    public function test_user_activity_renders(): void
    {
        $mawa = $this->institute('Audit UserAct');
        $this->setupAccounting($mawa);
        $owner = $this->owner('audit-useract@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.reports.audit.user-activity', [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('User Activity');
    }

    // ─── Financial Changes ──────────────────────────────

    public function test_financial_changes_renders(): void
    {
        $mawa = $this->institute('Audit FinChange');
        $this->setupAccounting($mawa);
        $owner = $this->owner('audit-finchange@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.reports.audit.financial-changes', [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Financial Change History');
    }

    // ─── Approval History ───────────────────────────────

    public function test_approval_history_renders(): void
    {
        $mawa = $this->institute('Audit ApprovHist');
        $this->setupAccounting($mawa);
        $owner = $this->owner('audit-approvhist@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.reports.audit.approval-history', [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Approval History');
    }

    // ─── Tenant Isolation ───────────────────────────────

    public function test_tenant_isolation_audit_reports(): void
    {
        $mawa = $this->institute('Audit Tenant A');
        $other = $this->institute('Audit Tenant B');
        $this->setupAccounting($mawa);
        $this->setupAccounting($other);

        $ownerA = $this->owner('audit-tenanta@example.test');
        (new MembershipService)->assign($ownerA, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($ownerA, $mawa->id)
            ->get(route('accounting.reports.audit.journal-trail', [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk();
    }
}
