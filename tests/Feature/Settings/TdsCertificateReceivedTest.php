<?php

namespace Tests\Feature\Settings;

use App\Models\Institute;
use App\Models\Party;
use App\Models\TdsCertificateReceived;
use App\Models\TdsReceivable;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use Tests\TestCase;

class TdsCertificateReceivedTest extends TestCase
{
    protected function tenantOwner(string $email): array
    {
        $unique = strtolower(\Illuminate\Support\Str::random(10));
        $email = str_replace('@', "+{$unique}@", $email);
        $institute = Institute::create([
            'name' => 'CertRecv Co ' . $unique,
            'slug' => 'certrecv-co-' . $unique,
            'status' => 'active',
            'country' => 'Bangladesh',
        ]);
        $owner = (new UserAccountService)->registerOwner([
            'name' => 'CertRecv Owner',
            'first_name' => 'CertRecv',
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
            'name' => 'Cert Party ' . \Illuminate\Support\Str::random(5),
        ]);
    }

    public function test_index_loads(): void
    {
        [$institute, $owner] = $this->tenantOwner('cert-idx@example.test');
        $this->asUser($owner, $institute->id)
            ->get(route('settings.tds-certificates-received.index'))
            ->assertStatus(200)
            ->assertSee('Certificates Received');
    }

    public function test_certificate_recorded(): void
    {
        [$institute, $owner] = $this->tenantOwner('cert-store@example.test');
        $party = $this->createParty($institute->id);

        $this->asUser($owner, $institute->id)
            ->post(route('settings.tds-certificates-received.store'), [
                'party_id' => $party->id,
                'certificate_no' => 'CERT-001',
                'certificate_date' => date('Y-m-d'),
                'tax_period' => 'Sep 2026',
                'financial_year' => '2026-2027',
                'total_base' => 100000,
                'total_tds' => 10000,
            ])->assertRedirect();

        $this->assertDatabaseHas('tds_certificates_received', [
            'institute_id' => $institute->id,
            'party_id' => $party->id,
            'certificate_no' => 'CERT-001',
        ]);
    }

    public function test_certificate_auto_links_receivables(): void
    {
        [$institute, $owner] = $this->tenantOwner('cert-link@example.test');
        $party = $this->createParty($institute->id);

        // Create a pending receivable first
        TdsReceivable::create([
            'institute_id' => $institute->id,
            'country_code' => 'BD',
            'party_id' => $party->id,
            'gross_amount' => 100000,
            'rate_percent' => 10,
            'tds_amount' => 10000,
            'net_amount' => 90000,
            'currency_code' => 'BDT',
            'deduction_date' => date('Y-m-d'),
            'tax_period' => 'Sep 2026',
            'financial_year' => '2026-2027',
            'status' => 'pending_certificate',
        ]);

        $this->asUser($owner, $institute->id)
            ->post(route('settings.tds-certificates-received.store'), [
                'party_id' => $party->id,
                'certificate_no' => 'CERT-002',
                'certificate_date' => date('Y-m-d'),
                'tax_period' => 'Sep 2026',
                'financial_year' => '2026-2027',
                'total_base' => 100000,
                'total_tds' => 10000,
            ])->assertRedirect();

        $cert = TdsCertificateReceived::where('institute_id', $institute->id)->first();
        $rec = TdsReceivable::where('institute_id', $institute->id)->first();
        $this->assertEquals($cert->id, $rec->certificate_id);
        $this->assertEquals('certified', $rec->status);
    }

    public function test_certificate_verify_changes_status(): void
    {
        [$institute, $owner] = $this->tenantOwner('cert-verify@example.test');
        $party = $this->createParty($institute->id);

        $cert = TdsCertificateReceived::create([
            'institute_id' => $institute->id,
            'country_code' => 'BD',
            'party_id' => $party->id,
            'certificate_no' => 'CERT-003',
            'certificate_date' => date('Y-m-d'),
            'tax_period' => 'Sep 2026',
            'financial_year' => '2026-2027',
            'total_base' => 50000,
            'total_tds' => 5000,
            'currency_code' => 'BDT',
            'status' => 'received',
        ]);

        $this->asUser($owner, $institute->id)
            ->post(route('settings.tds-certificates-received.verify', $cert))
            ->assertRedirect();

        $this->assertDatabaseHas('tds_certificates_received', [
            'id' => $cert->id,
            'status' => 'verified',
        ]);
    }

    public function test_duplicate_certificate_rejected(): void
    {
        [$institute, $owner] = $this->tenantOwner('cert-dup@example.test');
        $party = $this->createParty($institute->id);

        TdsCertificateReceived::create([
            'institute_id' => $institute->id,
            'country_code' => 'BD',
            'party_id' => $party->id,
            'certificate_no' => 'CERT-DUP',
            'certificate_date' => date('Y-m-d'),
            'tax_period' => 'Sep 2026',
            'financial_year' => '2026-2027',
            'total_base' => 50000,
            'total_tds' => 5000,
            'currency_code' => 'BDT',
            'status' => 'received',
        ]);

        $this->asUser($owner, $institute->id)
            ->post(route('settings.tds-certificates-received.store'), [
                'party_id' => $party->id,
                'certificate_no' => 'CERT-DUP',
                'certificate_date' => date('Y-m-d'),
                'tax_period' => 'Sep 2026',
                'financial_year' => '2026-2027',
                'total_base' => 50000,
                'total_tds' => 5000,
            ])->assertStatus(302); // redirect back with error
    }

    public function test_cross_tenant_certificate_blocked(): void
    {
        [$inst1, $owner1] = $this->tenantOwner('cert-ct1@example.test');
        [$inst2, $owner2] = $this->tenantOwner('cert-ct2@example.test');
        $party = $this->createParty($inst1->id);

        $cert = TdsCertificateReceived::create([
            'institute_id' => $inst1->id,
            'country_code' => 'BD',
            'party_id' => $party->id,
            'certificate_no' => 'CERT-CT',
            'certificate_date' => date('Y-m-d'),
            'tax_period' => 'Sep 2026',
            'financial_year' => '2026-2027',
            'total_base' => 50000,
            'total_tds' => 5000,
            'currency_code' => 'BDT',
            'status' => 'received',
        ]);

        $this->asUser($owner2, $inst2->id)
            ->post(route('settings.tds-certificates-received.verify', $cert))
            ->assertStatus(404);
    }
}
