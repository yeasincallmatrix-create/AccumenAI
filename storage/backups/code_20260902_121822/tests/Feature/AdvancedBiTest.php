<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Membership;
use App\Models\User;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\ExecutiveDashboardService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * STEP 97 — Advanced BI Dashboard.
 */
class AdvancedBiTest extends TestCase
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

    protected function owner(string $email): User
    {
        return (new UserAccountService)->registerOwner([
            'name' => 'Step97 Owner',
            'first_name' => 'Step97',
            'last_name' => 'Owner',
            'email' => $email,
            'password_hash' => bcrypt($this->password),
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
        return \App\Models\Role::where('slug', $slug)->firstOrFail()->id;
    }

    protected function assign(User $user, Institute $institute, string $roleSlug, array $attributes = []): Membership
    {
        return (new MembershipService)->assign($user, $institute->id, $this->roleId($roleSlug), $attributes);
    }

    protected function setupAccounting(Institute $institute, ?int $branchId = null): void
    {
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branchId);
    }

    protected function asUser(User $user, int $workspaceId): static
    {
        return $this->withSession([Workspace::SESSION_KEY => $workspaceId])->actingAs($user, 'web');
    }

    protected function executiveService(): ExecutiveDashboardService
    {
        return app(ExecutiveDashboardService::class);
    }

    // ------------------------------------------------------------ Render tests

    public function test_hr_analytics_renders(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $owner = $this->owner('step97-hr@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->get(route('accounting.executive.hr'))
            ->assertOk()
            ->assertSee('HR Analytics');
    }

    public function test_sales_funnel_renders(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $owner = $this->owner('step97-funnel@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->get(route('accounting.executive.sales-funnel'))
            ->assertOk()
            ->assertSee('Sales Funnel');
    }

    public function test_department_reports_renders(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $owner = $this->owner('step97-dept@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->get(route('accounting.executive.departments'))
            ->assertOk()
            ->assertSee('Department Reports');
    }

    // ------------------------------------------------------------ Service tests

    public function test_hr_analytics_service_returns_data(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $data = $this->executiveService()->hrAnalytics((int) $mawa->id, null);

        $this->assertArrayHasKey('total_employees', $data);
        $this->assertArrayHasKey('active_employees', $data);
        $this->assertArrayHasKey('payroll_cost', $data);
        $this->assertArrayHasKey('attendance_rate', $data);
        $this->assertArrayHasKey('leave_utilization', $data);
        $this->assertArrayHasKey('headcount_by_status', $data);
        $this->assertArrayHasKey('headcount_by_department', $data);
        $this->assertIsInt($data['total_employees']);
        $this->assertIsFloat($data['payroll_cost']);
        $this->assertIsFloat($data['attendance_rate']);
        $this->assertIsFloat($data['leave_utilization']);
    }

    public function test_sales_funnel_service_returns_data(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $data = $this->executiveService()->salesFunnel((int) $mawa->id, null);

        $this->assertArrayHasKey('leads_count', $data);
        $this->assertArrayHasKey('quotations_sent', $data);
        $this->assertArrayHasKey('orders_confirmed', $data);
        $this->assertArrayHasKey('deliveries_completed', $data);
        $this->assertArrayHasKey('lead_to_quotation_rate', $data);
        $this->assertArrayHasKey('quotation_to_order_rate', $data);
        $this->assertArrayHasKey('order_to_delivery_rate', $data);
        $this->assertArrayHasKey('overall_conversion_rate', $data);
        $this->assertIsInt($data['leads_count']);
        $this->assertIsFloat($data['lead_to_quotation_rate']);
    }
}
