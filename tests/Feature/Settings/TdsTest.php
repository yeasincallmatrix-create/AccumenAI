<?php

namespace Tests\Feature\Settings;

use App\Models\Institute;
use App\Models\TdsDeduction;
use App\Models\TaxDeductionRule;
use App\Models\User;
use App\Services\Accounting\TdsService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use Tests\TestCase;

class TdsTest extends TestCase
{
    protected function tenantOwner(string $email): array
    {
        $unique = strtolower(\Illuminate\Support\Str::random(10));
        $email = str_replace('@', "+{$unique}@", $email);
        $institute = Institute::create([
            'name' => 'TDS Co ' . $unique,
            'slug' => 'tds-co-' . $unique,
            'status' => 'active',
            'country' => 'Bangladesh',
        ]);
        $owner = (new UserAccountService)->registerOwner([
            'name' => 'TDS Owner',
            'first_name' => 'TDS',
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

    public function test_tds_index_loads(): void
    {
        [$institute, $owner] = $this->tenantOwner('tds-idx@example.test');
        $this->asUser($owner, $institute->id)
            ->get(route('settings.tds.index'))
            ->assertStatus(200)
            ->assertSee('TDS');
    }

    public function test_tds_create_form_loads(): void
    {
        [$institute, $owner] = $this->tenantOwner('tds-create@example.test');
        $this->asUser($owner, $institute->id)
            ->get(route('settings.tds.create'))
            ->assertStatus(200)
            ->assertSee('Record TDS');
    }

    public function test_tds_store_records_deduction(): void
    {
        [$institute, $owner] = $this->tenantOwner('tds-store@example.test');

        $this->asUser($owner, $institute->id)
            ->post(route('settings.tds.store'), [
                'type' => 'VENDOR_5',
                'payee_name' => 'Test Vendor',
                'gross_amount' => 100000,
                'deduction_date' => date('Y-m-d'),
            ])->assertRedirect();

        $this->assertDatabaseHas('tds_deductions', [
            'institute_id' => $institute->id,
            'type' => 'VENDOR_5',
            'payee_name' => 'Test Vendor',
        ]);
    }

    public function test_tds_computation_calculates_correctly(): void
    {
        $service = new TdsService();
        $result = $service->compute('VENDOR_5', 100000, 'BD');

        $this->assertEquals(5.00, $result['rate']);
        $this->assertEquals(5000, $result['tax_amount']);
    }

    public function test_tds_computation_below_threshold_returns_zero(): void
    {
        $service = new TdsService();
        $result = $service->compute('VENDOR_5', 10000, 'BD');

        $this->assertEquals(0, $result['tax_amount']);
    }

    public function test_bd_tenant_uses_bd_rules(): void
    {
        $rules = TaxDeductionRule::where('country_code', 'BD')->active()->get();
        $this->assertGreaterThanOrEqual(7, $rules->count());
    }

    public function test_tds_currency_snapshot_bdt(): void
    {
        [$institute, $owner] = $this->tenantOwner('tds-cur@example.test');

        $this->asUser($owner, $institute->id)
            ->post(route('settings.tds.store'), [
                'type' => 'VENDOR_5',
                'payee_name' => 'Snapshot Test',
                'gross_amount' => 50000,
                'deduction_date' => date('Y-m-d'),
            ])->assertRedirect();

        $deduction = TdsDeduction::where('institute_id', $institute->id)->first();
        $this->assertEquals('BD', $deduction->country_code);
        $this->assertEquals('BDT', $deduction->currency_code);
    }

    public function test_tds_deposited_mark(): void
    {
        [$institute, $owner] = $this->tenantOwner('tds-dep@example.test');

        $this->asUser($owner, $institute->id)
            ->post(route('settings.tds.store'), [
                'type' => 'VENDOR_5',
                'payee_name' => 'Deposit Test',
                'gross_amount' => 50000,
                'deduction_date' => date('Y-m-d'),
            ])->assertRedirect();

        $tds = TdsDeduction::where('institute_id', $institute->id)->first();
        $this->asUser($owner, $institute->id)
            ->post(route('settings.tds.deposit', $tds), [
                'deposit_date' => date('Y-m-d'),
                'challan_no' => 'CHL-001',
            ])->assertRedirect();

        $this->assertDatabaseHas('tds_deductions', [
            'id' => $tds->id,
            'status' => 'deposited',
        ]);
    }

    public function test_tds_certificate_generate(): void
    {
        [$institute, $owner] = $this->tenantOwner('tds-cert@example.test');

        $this->asUser($owner, $institute->id)
            ->post(route('settings.tds.store'), [
                'type' => 'VENDOR_5',
                'payee_name' => 'Cert Test',
                'gross_amount' => 50000,
                'deduction_date' => date('Y-m-d'),
            ])->assertRedirect();

        $tds = TdsDeduction::where('institute_id', $institute->id)->first();
        $service = new TdsService();
        $service->markDeposited($tds, ['deposit_date' => date('Y-m-d'), 'challan_no' => 'CHL-001']);

        $this->asUser($owner, $institute->id)
            ->post(route('settings.tds.certificates.generate', $tds))
            ->assertRedirect();

        $this->assertDatabaseHas('tds_certificates', [
            'institute_id' => $institute->id,
            'deduction_id' => $tds->id,
        ]);
    }

    public function test_tds_certificates_page_loads(): void
    {
        [$institute, $owner] = $this->tenantOwner('tds-certs@example.test');
        $this->asUser($owner, $institute->id)
            ->get(route('settings.tds.certificates'))
            ->assertStatus(200)
            ->assertSee('Certificate');
    }

    public function test_tds_summary_returns_correct_structure(): void
    {
        $service = new TdsService();
        $summary = $service->getSummary(1, (string) date('Y'));

        $this->assertArrayHasKey('total_deductions', $summary);
        $this->assertArrayHasKey('total_tax', $summary);
        $this->assertArrayHasKey('by_type', $summary);
    }

    public function test_tds_reference_no_format(): void
    {
        [$institute, $owner] = $this->tenantOwner('tds-ref@example.test');

        $this->asUser($owner, $institute->id)
            ->post(route('settings.tds.store'), [
                'type' => 'VENDOR_5',
                'payee_name' => 'Ref Test',
                'gross_amount' => 50000,
                'deduction_date' => date('Y-m-d'),
            ])->assertRedirect();

        $tds = TdsDeduction::where('institute_id', $institute->id)->first();
        $this->assertStringStartsWith('TDS-' . date('Y') . '-', $tds->reference_no);
    }
}
