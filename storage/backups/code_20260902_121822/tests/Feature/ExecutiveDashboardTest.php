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
 * STEP 90 — Executive Dashboard & BI Analytics.
 */
class ExecutiveDashboardTest extends TestCase
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
            'name' => 'Step90 Owner',
            'first_name' => 'Step90',
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

    // ------------------------------------------------------------ Tests

    public function test_executive_dashboard_renders(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $owner = $this->owner('step90-dash@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->get(route('accounting.executive.index'))
            ->assertOk()
            ->assertSee('Executive Dashboard');
    }

    public function test_revenue_analytics_renders(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $owner = $this->owner('step90-revenue@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->get(route('accounting.executive.revenue'))
            ->assertOk()
            ->assertSee('Revenue Analytics');
    }

    public function test_profit_analysis_renders(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $owner = $this->owner('step90-profit@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->get(route('accounting.executive.profit'))
            ->assertOk()
            ->assertSee('Profit Analysis');
    }

    public function test_cash_forecast_renders(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $owner = $this->owner('step90-cash@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->get(route('accounting.executive.cash'))
            ->assertOk()
            ->assertSee('Cash Forecast');
    }

    public function test_business_insights_renders(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $owner = $this->owner('step90-insights@example.test');
        $this->assign($owner, $mawa, 'institute-owner');

        $this->asUser($owner, (int) $mawa->id)
            ->get(route('accounting.executive.insights'))
            ->assertOk()
            ->assertSee('Business Insights');
    }

    public function test_tenant_isolation_executive_dashboard(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $this->setupAccounting($mawa);
        $this->setupAccounting($tutu);

        $owner = $this->owner('step90-tenant@example.test');
        $this->assign($owner, $mawa, 'institute-owner');
        $this->assign($owner, $tutu, 'institute-owner');

        $kpisMawa = $this->executiveService()->kpiSummary((int) $mawa->id, null);
        $kpisTutu = $this->executiveService()->kpiSummary((int) $tutu->id, null);

        $this->assertSame(0.0, $kpisMawa['total_revenue']);
        $this->assertSame(0.0, $kpisTutu['total_revenue']);
        $this->assertSame(0, $kpisMawa['active_customers']);
        $this->assertSame(0, $kpisTutu['active_customers']);
    }
}
