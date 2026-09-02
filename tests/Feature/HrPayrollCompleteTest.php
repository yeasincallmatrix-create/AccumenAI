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

/**
 * STEP 94 — HR Payroll Route Smoke Tests.
 */
class HrPayrollCompleteTest extends \Tests\TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();
        BranchContext::clear();
        Workspace::clear();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        Workspace::clear();
        parent::tearDown();
    }

    protected function owner(string $email): User
    {
        return (new UserAccountService)->registerOwner([
            'name' => 'HR94 Owner',
            'first_name' => 'HR94',
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

    private function createHrInst(string $name): array
    {
        $inst = $this->institute($name);
        $this->setupAccounting($inst);
        $email = 'hr94-'.uniqid().'@example.test';
        $o = $this->owner($email);
        (new MembershipService)->assign($o, $inst->id, $this->roleId('institute-owner'));

        return [$inst, $o];
    }

    // ─── Tests ─────────────────────────────────────────────

    public function test_hr_employees_index_renders(): void
    {
        [$inst, $owner] = $this->createHrInst('HR94 Employees');
        $this->asUser($owner, (int) $inst->id)
            ->get(route('hr.employees.index'))
            ->assertOk()
            ->assertSee('Employee');
    }

    public function test_hr_departments_index_renders(): void
    {
        [$inst, $owner] = $this->createHrInst('HR94 Departments');
        $this->asUser($owner, (int) $inst->id)
            ->get(route('hr.departments.index'))
            ->assertOk()
            ->assertSee('Department');
    }

    public function test_hr_attendance_index_renders(): void
    {
        [$inst, $owner] = $this->createHrInst('HR94 Attendance');
        $this->asUser($owner, (int) $inst->id)
            ->get(route('hr.attendance.daily'))
            ->assertOk()
            ->assertSee('Attendance');
    }

    public function test_hr_leave_index_renders(): void
    {
        [$inst, $owner] = $this->createHrInst('HR94 Leave');
        $this->asUser($owner, (int) $inst->id)
            ->get(route('hr.leave.applications'))
            ->assertOk()
            ->assertSee('Leave');
    }

    public function test_hr_payroll_index_renders(): void
    {
        [$inst, $owner] = $this->createHrInst('HR94 Payroll');
        $this->asUser($owner, (int) $inst->id)
            ->get(route('hr.payroll.periods.index'))
            ->assertOk()
            ->assertSee('Payroll');
    }

    public function test_hr_dashboard_renders(): void
    {
        [$inst, $owner] = $this->createHrInst('HR94 Dashboard');
        $this->asUser($owner, (int) $inst->id)
            ->get(route('hr.dashboard'))
            ->assertOk()
            ->assertSee('HR');
    }
}
