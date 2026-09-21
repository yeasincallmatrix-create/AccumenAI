<?php

namespace Tests\Feature\Settings;

use App\Models\ChartOfAccount;
use App\Models\Institute;
use App\Models\Party;
use App\Models\TdsReceivable;
use App\Models\User;
use App\Services\Accounting\TdsReceivableService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use Tests\TestCase;

class TdsReceivableTest extends TestCase
{
    protected function tenantOwner(string $email): array
    {
        $unique = strtolower(\Illuminate\Support\Str::random(10));
        $email = str_replace('@', "+{$unique}@", $email);
        $institute = Institute::create([
            'name' => 'Recv Co ' . $unique,
            'slug' => 'recv-co-' . $unique,
            'status' => 'active',
            'country' => 'Bangladesh',
        ]);
        $owner = (new UserAccountService)->registerOwner([
            'name' => 'Recv Owner',
            'first_name' => 'Recv',
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
            'name' => 'Test Customer ' . \Illuminate\Support\Str::random(5),
        ]);
    }

    public function test_index_loads(): void
    {
        [$institute, $owner] = $this->tenantOwner('recv-idx@example.test');
        $this->asUser($owner, $institute->id)
            ->get(route('settings.tds-receivable.index'))
            ->assertStatus(200)
            ->assertSee('TDS Receivable');
    }

    public function test_create_form_loads(): void
    {
        [$institute, $owner] = $this->tenantOwner('recv-create@example.test');
        $this->asUser($owner, $institute->id)
            ->get(route('settings.tds-receivable.create'))
            ->assertStatus(200)
            ->assertSee('Record TDS Receivable');
    }

    public function test_receivable_recorded(): void
    {
        [$institute, $owner] = $this->tenantOwner('recv-store@example.test');
        $party = $this->createParty($institute->id);

        $this->asUser($owner, $institute->id)
            ->post(route('settings.tds-receivable.store'), [
                'party_id' => $party->id,
                'gross_amount' => 100000,
                'rate_percent' => 10,
                'deduction_date' => date('Y-m-d'),
                'tax_period' => 'Sep 2026',
                'financial_year' => '2026-2027',
            ])->assertRedirect();

        $this->assertDatabaseHas('tds_receivables', [
            'institute_id' => $institute->id,
            'party_id' => $party->id,
            'gross_amount' => 100000,
        ]);
    }

    public function test_tds_auto_calculated(): void
    {
        [$institute, $owner] = $this->tenantOwner('recv-calc@example.test');
        $party = $this->createParty($institute->id);

        $this->asUser($owner, $institute->id)
            ->post(route('settings.tds-receivable.store'), [
                'party_id' => $party->id,
                'gross_amount' => 100000,
                'rate_percent' => 10,
                'deduction_date' => date('Y-m-d'),
                'tax_period' => 'Sep 2026',
                'financial_year' => '2026-2027',
            ])->assertRedirect();

        $rec = TdsReceivable::where('institute_id', $institute->id)->first();
        $this->assertEquals(10000, (float) $rec->tds_amount);
        $this->assertEquals(90000, (float) $rec->net_amount);
    }

    public function test_receivable_account_auto_created(): void
    {
        [$institute, $owner] = $this->tenantOwner('recv-coa@example.test');
        $party = $this->createParty($institute->id);

        $this->asUser($owner, $institute->id)
            ->post(route('settings.tds-receivable.store'), [
                'party_id' => $party->id,
                'gross_amount' => 50000,
                'rate_percent' => 5,
                'deduction_date' => date('Y-m-d'),
                'tax_period' => 'Sep 2026',
                'financial_year' => '2026-2027',
            ])->assertRedirect();

        $acc = ChartOfAccount::withoutGlobalScope('institute')
            ->where('institute_id', $institute->id)
            ->where('name', 'TDS Receivable')
            ->first();
        $this->assertNotNull($acc);
        $this->assertEquals('asset', $acc->type);
    }

    public function test_pending_certificate_status_default(): void
    {
        [$institute, $owner] = $this->tenantOwner('recv-status@example.test');
        $party = $this->createParty($institute->id);

        $this->asUser($owner, $institute->id)
            ->post(route('settings.tds-receivable.store'), [
                'party_id' => $party->id,
                'gross_amount' => 50000,
                'rate_percent' => 10,
                'deduction_date' => date('Y-m-d'),
                'tax_period' => 'Sep 2026',
                'financial_year' => '2026-2027',
            ])->assertRedirect();

        $rec = TdsReceivable::where('institute_id', $institute->id)->first();
        $this->assertEquals('pending_certificate', $rec->status);
    }

    public function test_cross_tenant_receivable_blocked(): void
    {
        [$inst1, $owner1] = $this->tenantOwner('recv-ct1@example.test');
        [$inst2, $owner2] = $this->tenantOwner('recv-ct2@example.test');
        $party = $this->createParty($inst1->id);

        $this->asUser($owner2, $inst2->id)
            ->post(route('settings.tds-receivable.store'), [
                'party_id' => $party->id,
                'gross_amount' => 50000,
                'rate_percent' => 10,
                'deduction_date' => date('Y-m-d'),
                'tax_period' => 'Sep 2026',
                'financial_year' => '2026-2027',
            ])->assertStatus(403);
    }

    public function test_fy_filter(): void
    {
        [$institute, $owner] = $this->tenantOwner('recv-fy@example.test');
        $party = $this->createParty($institute->id);

        // Create receivable for 2026-2027
        TdsReceivable::create([
            'institute_id' => $institute->id,
            'country_code' => 'BD',
            'party_id' => $party->id,
            'gross_amount' => 80000,
            'rate_percent' => 10,
            'tds_amount' => 8000,
            'net_amount' => 72000,
            'currency_code' => 'BDT',
            'deduction_date' => date('Y-m-d'),
            'tax_period' => 'Sep 2026',
            'financial_year' => '2026-2027',
            'status' => 'pending_certificate',
        ]);

        $this->asUser($owner, $institute->id)
            ->get(route('settings.tds-receivable.index', ['fy' => '2026-2027']))
            ->assertStatus(200)
            ->assertSee('80,000');
    }
}
