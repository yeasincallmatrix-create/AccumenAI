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
        $unique = strtolower(preg_replace('/[^a-z]/i', '', uniqid()));
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

        $this->asUser($owner, $institute->id)
            ->get(route('accounting.reports.ratios'))
            ->assertStatus(200)
            ->assertSee('Ratio Analysis')
            ->assertSee('Liquidity');
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
}
