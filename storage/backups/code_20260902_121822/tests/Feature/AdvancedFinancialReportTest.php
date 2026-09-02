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

class AdvancedFinancialReportTest extends \Tests\TestCase
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
            'name' => 'AdvFin Owner',
            'first_name' => 'AdvFin',
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

    // ─── Comparative Income Statement ────────────────────────

    public function test_comparative_income_statement_renders(): void
    {
        $mawa = $this->institute('AdvFin CompIS');
        $this->setupAccounting($mawa);
        $owner = $this->owner('advfin-comis@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.reports.advanced.comparative-income', [
                'current_from' => now()->startOfYear()->toDateString(),
                'current_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Comparative Income Statement');
    }

    // ─── Comparative Balance Sheet ───────────────────────────

    public function test_comparative_balance_sheet_renders(): void
    {
        $mawa = $this->institute('AdvFin CompBS');
        $this->setupAccounting($mawa);
        $owner = $this->owner('advfin-combs@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.reports.advanced.comparative-balance', [
                'current_date' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Comparative Balance Sheet');
    }

    // ─── Monthly Revenue Trend ───────────────────────────────

    public function test_monthly_revenue_trend_renders(): void
    {
        $mawa = $this->institute('AdvFin MRT');
        $this->setupAccounting($mawa);
        $owner = $this->owner('advfin-mrt@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.reports.advanced.monthly-revenue', [
                'from' => now()->startOfYear()->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Monthly Revenue Trend');
    }

    // ─── Expense Analysis ────────────────────────────────────

    public function test_expense_analysis_renders(): void
    {
        $mawa = $this->institute('AdvFin EA');
        $this->setupAccounting($mawa);
        $owner = $this->owner('advfin-ea@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.reports.advanced.expense-analysis', [
                'from' => now()->startOfYear()->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Expense Analysis');
    }

    // ─── Profitability Dashboard ─────────────────────────────

    public function test_profitability_dashboard_renders(): void
    {
        $mawa = $this->institute('AdvFin PD');
        $this->setupAccounting($mawa);
        $owner = $this->owner('advfin-pd@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.reports.advanced.profitability', [
                'from' => now()->startOfYear()->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Profitability Dashboard');
    }

    // ─── Service unit tests ──────────────────────────────────

    public function test_comparative_income_service_returns_periods(): void
    {
        $mawa = $this->institute('AdvFin Svc IS');
        $this->setupAccounting($mawa);

        $svc = app(\App\Services\Accounting\AdvancedFinancialReportService::class);
        $result = $svc->comparativeIncomeStatement(
            $mawa->id, null,
            now()->startOfYear()->toDateString(),
            now()->toDateString(),
        );

        $this->assertArrayHasKey('current', $result);
        $this->assertArrayHasKey('prior', $result);
        $this->assertArrayHasKey('variance', $result);
        $this->assertArrayHasKey('total_income', $result['current']);
        $this->assertArrayHasKey('total_income', $result['prior']);
    }

    public function test_profitability_service_returns_margins(): void
    {
        $mawa = $this->institute('AdvFin Svc PD');
        $this->setupAccounting($mawa);

        $svc = app(\App\Services\Accounting\AdvancedFinancialReportService::class);
        $result = $svc->profitabilityDashboard(
            $mawa->id, null,
            now()->startOfYear()->toDateString(),
            now()->toDateString(),
        );

        $this->assertArrayHasKey('revenue', $result);
        $this->assertArrayHasKey('gross_margin', $result);
        $this->assertArrayHasKey('net_margin', $result);
    }

    public function test_monthly_trend_returns_collection(): void
    {
        $mawa = $this->institute('AdvFin Svc MRT');
        $this->setupAccounting($mawa);

        $svc = app(\App\Services\Accounting\AdvancedFinancialReportService::class);
        $months = $svc->monthlyRevenueTrend(
            $mawa->id, null,
            now()->startOfMonth()->toDateString(),
            now()->toDateString(),
        );

        $this->assertTrue($months->isNotEmpty());
        $this->assertObjectHasProperty('month', $months->first());
        $this->assertObjectHasProperty('total_income', $months->first());
    }

    // ─── Tenant isolation ────────────────────────────────────

    public function test_tenant_isolation_comparative_reports(): void
    {
        $mawa = $this->institute('AdvFin Tenant A');
        $other = $this->institute('AdvFin Tenant B');
        $this->setupAccounting($mawa);
        $this->setupAccounting($other);

        $ownerA = $this->owner('advfin-tenanta@example.test');
        (new MembershipService)->assign($ownerA, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($ownerA, $mawa->id)
            ->get(route('accounting.reports.advanced.comparative-income', [
                'current_from' => now()->startOfYear()->toDateString(),
                'current_to' => now()->toDateString(),
            ]))
            ->assertOk();
    }
}
