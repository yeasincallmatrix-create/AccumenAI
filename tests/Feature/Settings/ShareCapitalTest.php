<?php

namespace Tests\Feature\Settings;

use App\Models\Institute;
use App\Models\ShareCapitalTransaction;
use App\Models\ShareCertificate;
use App\Models\Shareholder;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use Tests\TestCase;

class ShareCapitalTest extends TestCase
{
    protected function tenantOwner(string $email): array
    {
        $unique = strtolower(\Illuminate\Support\Str::random(10));
        $email = str_replace('@', "+{$unique}@", $email);
        // Fresh institute per test: shared rows accumulate (no rollback).
        $institute = Institute::create([
            'name' => 'Pvt Ltd '.$unique,
            'slug' => 'pvt-ltd-'.$unique,
            'status' => 'active',
            'business_entity_type' => 'private_limited',
            'authorized_capital' => 1000000,
            'share_face_value' => 10,
        ]);
        $owner = (new UserAccountService)->registerOwner([
            'name' => 'Capital Owner',
            'first_name' => 'Capital',
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

    protected function shareholder(int $instituteId, int $shares = 1000): Shareholder
    {
        return Shareholder::create([
            'institute_id' => $instituteId,
            'name' => 'SH '.\Illuminate\Support\Str::random(6),
            'shares' => $shares,
            'face_value' => 10,
            'share_percent' => 100,
            'is_active' => true,
        ]);
    }

    public function test_index_loads(): void
    {
        [$institute, $owner] = $this->tenantOwner('sc-idx@example.test');

        $this->asUser($owner, $institute->id)
            ->get(route('settings.share-capital.index'))
            ->assertStatus(200)
            ->assertSee('Share Capital');
    }

    public function test_settings_page_loads(): void
    {
        [$institute, $owner] = $this->tenantOwner('sc-set@example.test');

        $this->asUser($owner, $institute->id)
            ->get(route('settings.share-capital.settings'))
            ->assertStatus(200)
            ->assertSee('Capital Settings');
    }

    public function test_settings_can_be_updated(): void
    {
        [$institute, $owner] = $this->tenantOwner('sc-upd@example.test');

        $this->asUser($owner, $institute->id)
            ->put(route('settings.share-capital.settings.update'), [
                'authorized_capital' => 2000000,
                'share_face_value' => 10,
                'registration_no' => 'CIN-12345',
            ])->assertRedirect();

        $this->assertEquals(2000000, $institute->fresh()->authorized_capital);
    }

    public function test_shares_can_be_issued(): void
    {
        [$institute, $owner] = $this->tenantOwner('sc-issue@example.test');
        $sh = $this->shareholder($institute->id, 0);

        $this->asUser($owner, $institute->id)
            ->post(route('settings.share-capital.issue'), [
                'shareholder_id' => $sh->id,
                'shares' => 500,
                'face_value' => 10,
                'premium_per_share' => 0,
            ])->assertRedirect();

        $this->assertEquals(500, $sh->fresh()->shares);
        $this->assertDatabaseHas('share_certificates', [
            'institute_id' => $institute->id,
            'shareholder_id' => $sh->id,
            'shares' => 500,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('share_capital_transactions', [
            'institute_id' => $institute->id,
            'shareholder_id' => $sh->id,
            'type' => 'issuance',
            'shares' => 500,
        ]);
        $this->assertEquals(500, $institute->fresh()->shares_outstanding);
        $this->assertEquals(5000, $institute->fresh()->issued_capital);
    }

    public function test_share_transfer_moves_shares(): void
    {
        [$institute, $owner] = $this->tenantOwner('sc-xfer@example.test');
        $from = $this->shareholder($institute->id, 1000);
        $to = $this->shareholder($institute->id, 0);

        $this->asUser($owner, $institute->id)
            ->post(route('settings.share-capital.transfer'), [
                'from_shareholder_id' => $from->id,
                'to_shareholder_id' => $to->id,
                'shares' => 300,
                'face_value' => 10,
            ])->assertRedirect();

        $this->assertEquals(700, $from->fresh()->shares);
        $this->assertEquals(300, $to->fresh()->shares);
    }

    public function test_cannot_transfer_more_than_owned(): void
    {
        [$institute, $owner] = $this->tenantOwner('sc-over@example.test');
        $from = $this->shareholder($institute->id, 100);
        $to = $this->shareholder($institute->id, 0);

        $this->asUser($owner, $institute->id)
            ->post(route('settings.share-capital.transfer'), [
                'from_shareholder_id' => $from->id,
                'to_shareholder_id' => $to->id,
                'shares' => 500,
                'face_value' => 10,
            ])->assertSessionHasErrors('error');

        $this->assertEquals(100, $from->fresh()->shares);
    }

    public function test_share_percent_recomputed(): void
    {
        [$institute, $owner] = $this->tenantOwner('sc-pct@example.test');
        $sh = $this->shareholder($institute->id, 0);

        $this->asUser($owner, $institute->id)
            ->post(route('settings.share-capital.issue'), [
                'shareholder_id' => $sh->id,
                'shares' => 1000,
                'face_value' => 10,
            ]);

        $this->assertEquals(100.0, (float) $sh->fresh()->share_percent);
    }

    public function test_certificates_page_loads(): void
    {
        [$institute, $owner] = $this->tenantOwner('sc-cert@example.test');
        $sh = $this->shareholder($institute->id, 500);

        ShareCertificate::create([
            'institute_id' => $institute->id,
            'shareholder_id' => $sh->id,
            'certificate_no' => 'SC-00001',
            'shares' => 500,
            'face_value' => 10,
            'total_value' => 5000,
            'issue_date' => now(),
            'status' => 'active',
        ]);

        $this->asUser($owner, $institute->id)
            ->get(route('settings.share-capital.certificates'))
            ->assertStatus(200)
            ->assertSee('SC-00001');
    }

    public function test_cross_tenant_transaction_blocked(): void
    {
        [$institute, $owner] = $this->tenantOwner('sc-cross@example.test');
        [$other] = $this->tenantOwner('sc-other@example.test');
        $foreign = ShareCapitalTransaction::withoutGlobalScope('institute')->create([
            'institute_id' => $other->id,
            'type' => 'issuance',
            'shares' => 100,
            'face_value' => 10,
            'total_amount' => 1000,
            'transaction_date' => now(),
        ]);

        \App\Support\TenantContext::set($institute->id);
        $visible = ShareCapitalTransaction::pluck('id')->toArray();
        $this->assertNotContains($foreign->id, $visible);
        \App\Support\TenantContext::clear();
    }

    public function test_capital_summary_correct(): void
    {
        [$institute] = $this->tenantOwner('sc-sum@example.test');
        $this->shareholder($institute->id, 5000);

        $service = app(\App\Services\Accounting\ShareCapitalService::class);
        $service->recomputeInstituteCapital($institute->id);
        $summary = $service->getCapitalSummary($institute->id);

        $this->assertEquals(5000, $summary['shares_outstanding']);
        $this->assertEquals(50000, $summary['issued_capital']);
    }
}
