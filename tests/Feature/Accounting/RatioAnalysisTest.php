<?php

namespace Tests\Feature\Accounting;

use App\Models\Institute;
use App\Models\User;
use App\Services\Accounting\RatioAnalysisService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use Tests\TestCase;

class RatioAnalysisTest extends TestCase
{
    protected function tenantOwner(string $email): array
    {
        $unique = strtolower(\Illuminate\Support\Str::random(10));
        $email = str_replace('@', "+{$unique}@", $email);
        $institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
        $owner = (new UserAccountService)->registerOwner([
            'name' => 'Ratio Owner',
            'first_name' => 'Ratio',
            'last_name' => 'Owner',
            'email' => $email,
            'password_hash' => bcrypt('password'),
            'status' => 'active',
        ]);
        $roleId = \App\Models\Role::where('slug', 'institute-owner')->firstOrFail()->id;
        (new MembershipService)->assign($owner, $institute->id, $roleId);

        return [$institute, $owner];
    }

    protected function asUser(User $user, int $workspaceId): static
    {
        return $this->withSession([\App\Support\Workspace::SESSION_KEY => $workspaceId])
            ->actingAs($user, 'web');
    }

    public function test_service_returns_all_ratio_categories(): void
    {
        [$institute] = $this->tenantOwner('ratio-cats@example.test');
        $data = app(RatioAnalysisService::class)
            ->computeAll($institute->id, now()->format('Y-m-d'));

        $this->assertArrayHasKey('liquidity', $data);
        $this->assertArrayHasKey('profitability', $data);
        $this->assertArrayHasKey('leverage', $data);
        $this->assertArrayHasKey('efficiency', $data);
        $this->assertArrayHasKey('current_ratio', $data['liquidity']);
        $this->assertArrayHasKey('net_profit_margin', $data['profitability']);
        $this->assertArrayHasKey('debt_to_equity', $data['leverage']);
        $this->assertArrayHasKey('asset_turnover', $data['efficiency']);
    }

    public function test_zero_division_returns_null(): void
    {
        [$institute] = $this->tenantOwner('ratio-zero@example.test');
        $data = app(RatioAnalysisService::class)
            ->computeAll($institute->id, now()->format('Y-m-d'));

        $this->assertNull($data['profitability']['net_profit_margin']);
        $this->assertNull($data['profitability']['gross_profit_margin']);
    }

    public function test_page_renders(): void
    {
        [$institute, $owner] = $this->tenantOwner('ratio-page@example.test');
        app(\App\Services\Accounting\TenantAccountingModeService::class)->enable($institute->id);

        $this->asUser($owner, $institute->id)
            ->get(route('accounting.reports.ratios'))
            ->assertStatus(200)
            ->assertSee('Ratio Analysis')
            ->assertSee('Liquidity');
    }

    public function test_page_blocked_when_simple_mode(): void
    {
        [$institute, $owner] = $this->tenantOwner('ratio-blocked@example.test');
        app(\App\Services\Accounting\TenantAccountingModeService::class)->disable($institute->id);

        $this->asUser($owner, $institute->id)
            ->get(route('accounting.reports.ratios'))
            ->assertStatus(403);
    }

    public function test_cross_tenant_data_isolated(): void
    {
        [$institute] = $this->tenantOwner('ratio-iso@example.test');
        $other = Institute::where('name', 'Tutu Center')->firstOrFail();

        $a = app(RatioAnalysisService::class)->computeAll($institute->id, now()->format('Y-m-d'));
        $b = app(RatioAnalysisService::class)->computeAll($other->id, now()->format('Y-m-d'));

        $this->assertIsArray($a);
        $this->assertIsArray($b);
        $this->assertSame(array_keys($a), array_keys($b));
    }

    public function test_all_six_categories_returned(): void
    {
        [$institute] = $this->tenantOwner('ratio-six@example.test');
        $data = app(RatioAnalysisService::class)
            ->computeAll($institute->id, now()->format('Y-m-d'));

        foreach (['liquidity', 'profitability', 'leverage', 'efficiency', 'market', 'cash_flow'] as $cat) {
            $this->assertArrayHasKey($cat, $data);
        }
    }

    public function test_total_ratio_count(): void
    {
        [$institute] = $this->tenantOwner('ratio-count@example.test');
        $data = app(RatioAnalysisService::class)
            ->computeAll($institute->id, now()->format('Y-m-d'));

        $total = 0;
        foreach (['liquidity', 'profitability', 'leverage', 'efficiency', 'market', 'cash_flow'] as $cat) {
            $total += count($data[$cat]);
        }

        $this->assertSame(34, $total);
    }

    public function test_new_ratios_present(): void
    {
        [$institute] = $this->tenantOwner('ratio-new@example.test');
        $data = app(RatioAnalysisService::class)
            ->computeAll($institute->id, now()->format('Y-m-d'));

        $this->assertArrayHasKey('net_working_capital_ratio', $data['liquidity']);
        $this->assertArrayHasKey('cash_conversion_cycle', $data['liquidity']);
        $this->assertArrayHasKey('operating_profit_margin', $data['profitability']);
        $this->assertArrayHasKey('ebitda_margin', $data['profitability']);
        $this->assertArrayHasKey('roce', $data['profitability']);
        $this->assertArrayHasKey('interest_coverage', $data['leverage']);
        $this->assertArrayHasKey('debt_service_coverage', $data['leverage']);
        $this->assertArrayHasKey('days_sales_outstanding', $data['efficiency']);
        $this->assertArrayHasKey('days_payable_outstanding', $data['efficiency']);
        $this->assertArrayHasKey('days_inventory_outstanding', $data['efficiency']);
        $this->assertArrayHasKey('earnings_per_share', $data['market']);
        $this->assertArrayHasKey('operating_cash_flow_ratio', $data['cash_flow']);
        $this->assertArrayHasKey('free_cash_flow', $data['cash_flow']);

        // Graceful nulls: no shares column, no interest COA, no transactions.
        $this->assertNull($data['market']['earnings_per_share']);
        $this->assertNull($data['leverage']['interest_coverage']);
    }

    public function test_page_renders_all_six_cards(): void
    {
        [$institute, $owner] = $this->tenantOwner('ratio-cards@example.test');
        app(\App\Services\Accounting\TenantAccountingModeService::class)->enable($institute->id);

        $this->asUser($owner, $institute->id)
            ->get(route('accounting.reports.ratios'))
            ->assertStatus(200)
            ->assertSee('Liquidity')
            ->assertSee('Profitability')
            ->assertSee('Leverage')
            ->assertSee('Efficiency')
            ->assertSee('Market')
            ->assertSee('Cash Flow');
    }
}
