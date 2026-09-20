<?php

namespace Tests\Feature\Settings;

use App\Models\AdvanceTaxPayment;
use App\Models\CorporateTaxComputation;
use App\Models\Institute;
use App\Models\User;
use App\Services\Accounting\CorporateTaxService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use Tests\TestCase;

class CorporateTaxTest extends TestCase
{
    protected function tenantOwner(string $email): array
    {
        $unique = strtolower(\Illuminate\Support\Str::random(10));
        $email = str_replace('@', "+{$unique}@", $email);
        $institute = Institute::create([
            'name' => 'CT Co ' . $unique,
            'slug' => 'ct-co-' . $unique,
            'status' => 'active',
            'country' => 'Bangladesh',
        ]);
        $owner = (new UserAccountService)->registerOwner([
            'name' => 'CT Owner',
            'first_name' => 'CT',
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

    public function test_corporate_tax_index_loads(): void
    {
        [$institute, $owner] = $this->tenantOwner('ct-idx@example.test');
        $this->asUser($owner, $institute->id)
            ->get(route('settings.corporate-tax.index'))
            ->assertStatus(200)
            ->assertSee('Corporate Tax');
    }

    public function test_bd_pvt_rate_25(): void
    {
        $service = new CorporateTaxService();
        $rate = $service->rateFor('BD', 'private_limited');

        $this->assertEquals(25.00, $rate['rate']);
    }

    public function test_bd_minimum_tax_0_6(): void
    {
        $service = new CorporateTaxService();
        $rate = $service->rateFor('BD', 'private_limited');

        $this->assertEquals(0.60, $rate['min_tax_rate']);
    }

    public function test_in_rate_30(): void
    {
        $service = new CorporateTaxService();
        $rate = $service->rateFor('IN', 'private_limited');

        $this->assertEquals(30.00, $rate['rate']);
    }

    public function test_us_rate_21(): void
    {
        $service = new CorporateTaxService();
        $rate = $service->rateFor('US', 'private_limited');

        $this->assertEquals(21.00, $rate['rate']);
    }

    public function test_rate_matrix_lookup(): void
    {
        $service = new CorporateTaxService();
        $this->assertArrayHasKey('BD', CorporateTaxService::RATE_MATRIX);
        $this->assertArrayHasKey('IN', CorporateTaxService::RATE_MATRIX);
        $this->assertArrayHasKey('US', CorporateTaxService::RATE_MATRIX);
    }

    public function test_quarter_due_dates_bd(): void
    {
        $service = new CorporateTaxService();
        $dates = $service->getQuarterDueDates('2026-2027', 'BD');

        $this->assertArrayHasKey('Q1', $dates);
        $this->assertArrayHasKey('Q2', $dates);
        $this->assertArrayHasKey('Q3', $dates);
        $this->assertArrayHasKey('Q4', $dates);
    }

    public function test_quarter_due_dates_in(): void
    {
        $service = new CorporateTaxService();
        $dates = $service->getQuarterDueDates('2026-27', 'IN');

        $this->assertArrayHasKey('Q1', $dates);
        $this->assertArrayHasKey('Q4', $dates);
    }

    public function test_quarter_due_dates_us(): void
    {
        $service = new CorporateTaxService();
        $dates = $service->getQuarterDueDates('2026', 'US');

        $this->assertCount(4, $dates);
    }

    public function test_corporate_tax_compute_creates_record(): void
    {
        [$institute, $owner] = $this->tenantOwner('ct-compute@example.test');
        $service = new CorporateTaxService();

        $computation = $service->compute($institute->id, '2026', [
            'entity_type' => 'private_limited',
            'total_income' => 1000000,
            'deductions' => 200000,
        ]);

        $this->assertInstanceOf(CorporateTaxComputation::class, $computation);
        $this->assertEquals(800000, $computation->taxable_income);
        $this->assertEquals('BD', $computation->country_code);
    }

    public function test_advance_tax_record_creates_record(): void
    {
        [$institute, $owner] = $this->tenantOwner('ct-advance@example.test');
        $service = new CorporateTaxService();

        $advance = $service->recordAdvancePayment($institute->id, [
            'financial_year' => '2026',
            'quarter' => 'Q1',
            'estimated_income' => 500000,
            'entity_type' => 'private_limited',
            'due_date' => date('Y-m-d', strtotime('+30 days')),
        ]);

        $this->assertInstanceOf(AdvanceTaxPayment::class, $advance);
        $this->assertEquals('BD', $advance->country_code);
        $this->assertEquals('BDT', $advance->currency_code);
    }
}
