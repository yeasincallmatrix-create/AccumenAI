<?php

namespace Tests\Feature\Settings;

use App\Models\AdvanceTaxPayment;
use App\Models\CorporateTaxComputation;
use App\Models\Institute;
use App\Models\Party;
use App\Models\TaxReturnReconciliation;
use App\Models\TdsDeduction;
use App\Models\TdsReceivable;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use Tests\TestCase;

class TaxReconciliationTest extends TestCase
{
    protected function tenantOwner(string $email): array
    {
        $unique = strtolower(\Illuminate\Support\Str::random(10));
        $email = str_replace('@', "+{$unique}@", $email);
        $institute = Institute::create([
            'name' => 'Recon Co ' . $unique,
            'slug' => 'recon-co-' . $unique,
            'status' => 'active',
            'country' => 'Bangladesh',
        ]);
        $owner = (new UserAccountService)->registerOwner([
            'name' => 'Recon Owner',
            'first_name' => 'Recon',
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

    protected function createParty(int $instituteId): Party
    {
        return Party::create([
            'institute_id' => $instituteId,
            'type' => 'customer',
            'name' => 'Recon Party ' . \Illuminate\Support\Str::random(5),
        ]);
    }

    public function test_index_loads(): void
    {
        [$institute, $owner] = $this->tenantOwner('recon-idx@example.test');
        $this->asUser($owner, $institute->id)
            ->get(route('settings.tax-reconciliation.index'))
            ->assertStatus(200)
            ->assertSee('Tax Return Reconciliation');
    }

    public function test_compute_sums_all_sources(): void
    {
        [$institute, $owner] = $this->tenantOwner('recon-compute@example.test');
        $party = $this->createParty($institute->id);

        // Create TDS deduction (payable) via direct insert
        TdsDeduction::create([
            'institute_id' => $institute->id,
            'country_code' => 'BD',
            'currency_code' => 'BDT',
            'reference_no' => 'TDS-' . \Illuminate\Support\Str::random(8) . '-001',
            'type' => 'VENDOR_5',
            'payee_name' => 'Vendor A',
            'gross_amount' => 200000,
            'tax_rate' => 5,
            'tax_amount' => 10000,
            'deduction_date' => '2026-09-15',
            'status' => 'pending',
        ]);

        // Create TDS receivable
        TdsReceivable::create([
            'institute_id' => $institute->id,
            'country_code' => 'BD',
            'party_id' => $party->id,
            'gross_amount' => 100000,
            'rate_percent' => 10,
            'tds_amount' => 10000,
            'net_amount' => 90000,
            'currency_code' => 'BDT',
            'deduction_date' => '2026-09-15',
            'tax_period' => 'Sep 2026',
            'financial_year' => '2026-2027',
            'status' => 'certified',
        ]);

        // Create advance tax
        AdvanceTaxPayment::create([
            'institute_id' => $institute->id,
            'country_code' => 'BD',
            'currency_code' => 'BDT',
            'reference_no' => 'AT-' . \Illuminate\Support\Str::random(8),
            'financial_year' => '2026-2027',
            'quarter' => 'Q1',
            'estimated_income' => 500000,
            'tax_rate' => 10,
            'tax_amount' => 50000,
            'due_date' => '2026-09-15',
            'payment_date' => '2026-09-15',
            'challan_no' => 'CHL-001',
            'status' => 'paid',
        ]);

        $this->asUser($owner, $institute->id)
            ->get(route('settings.tax-reconciliation.index'))
            ->assertStatus(200)
            ->assertSee('10,000.00'); // tds_receivable_total
    }

    public function test_finalize_creates_record(): void
    {
        [$institute, $owner] = $this->tenantOwner('recon-finalize@example.test');

        $this->asUser($owner, $institute->id)
            ->post(route('settings.tax-reconciliation.finalize'), [
                'financial_year' => '2026-2027',
                'notes' => 'Test finalize',
            ])->assertRedirect();

        $this->assertDatabaseHas('tax_return_reconciliations', [
            'institute_id' => $institute->id,
            'financial_year' => '2026-2027',
            'status' => 'computed',
        ]);
    }

    public function test_mark_filed_updates_status(): void
    {
        [$institute, $owner] = $this->tenantOwner('recon-filed@example.test');

        $recon = TaxReturnReconciliation::create([
            'institute_id' => $institute->id,
            'country_code' => 'BD',
            'financial_year' => '2026-2027',
            'status' => 'computed',
            'currency_code' => 'BDT',
        ]);

        $this->asUser($owner, $institute->id)
            ->post(route('settings.tax-reconciliation.mark-filed', $recon), [
                'filing_date' => '2026-09-21',
                'acknowledgment_no' => 'ACK-2026-001',
            ])->assertRedirect();

        $this->assertDatabaseHas('tax_return_reconciliations', [
            'id' => $recon->id,
            'status' => 'filed',
            'acknowledgment_no' => 'ACK-2026-001',
        ]);
    }
}
