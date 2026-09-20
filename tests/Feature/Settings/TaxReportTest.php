<?php

namespace Tests\Feature\Settings;

use App\Models\Institute;
use App\Models\User;
use App\Services\Accounting\TaxReportService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use Tests\TestCase;

class TaxReportTest extends TestCase
{
    protected function tenantOwner(string $email): array
    {
        $unique = strtolower(\Illuminate\Support\Str::random(10));
        $email = str_replace('@', "+{$unique}@", $email);
        $institute = Institute::create([
            'name' => 'TR Co ' . $unique,
            'slug' => 'tr-co-' . $unique,
            'status' => 'active',
            'country' => 'Bangladesh',
        ]);
        $owner = (new UserAccountService)->registerOwner([
            'name' => 'TR Owner',
            'first_name' => 'TR',
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

    public function test_tds_summary_report_loads(): void
    {
        [$institute, $owner] = $this->tenantOwner('tr-tds@example.test');
        $this->asUser($owner, $institute->id)
            ->get(route('settings.tax-reports.tds-summary'))
            ->assertStatus(200)
            ->assertSee('TDS Summary');
    }

    public function test_advance_tax_report_loads(): void
    {
        [$institute, $owner] = $this->tenantOwner('tr-adv@example.test');
        $this->asUser($owner, $institute->id)
            ->get(route('settings.tax-reports.advance-tax'))
            ->assertStatus(200)
            ->assertSee('Advance Tax');
    }

    public function test_corporate_return_report_loads(): void
    {
        [$institute, $owner] = $this->tenantOwner('tr-corp@example.test');
        $this->asUser($owner, $institute->id)
            ->get(route('settings.tax-reports.corporate-return'))
            ->assertStatus(200)
            ->assertSee('Corporate Tax Return');
    }

    public function test_tax_report_service_returns_correct_structure(): void
    {
        $service = new TaxReportService();
        $summary = $service->tdsSummary(1, (string) date('Y'), 'BD');

        $this->assertArrayHasKey('country_code', $summary);
        $this->assertArrayHasKey('total_deductions', $summary);
        $this->assertArrayHasKey('by_type', $summary);
        $this->assertEquals('BD', $summary['country_code']);
    }
}
