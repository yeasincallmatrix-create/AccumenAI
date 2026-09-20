<?php

namespace Tests\Feature\Settings;

use App\Models\Dividend;
use App\Models\Institute;
use App\Models\ShareCertificate;
use App\Models\Shareholder;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use Tests\TestCase;

class DividendTest extends TestCase
{
    protected function tenantOwner(string $email): array
    {
        $unique = strtolower(\Illuminate\Support\Str::random(10));
        $email = str_replace('@', "+{$unique}@", $email);
        // Fresh institute per test: shared rows accumulate (no rollback).
        $institute = Institute::create([
            'name' => 'Div Co '.$unique,
            'slug' => 'div-co-'.$unique,
            'status' => 'active',
            'business_entity_type' => 'private_limited',
            'authorized_capital' => 1000000,
            'share_face_value' => 10,
        ]);
        $owner = (new UserAccountService)->registerOwner([
            'name' => 'Dividend Owner',
            'first_name' => 'Dividend',
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
        [$institute, $owner] = $this->tenantOwner('div-idx@example.test');

        $this->asUser($owner, $institute->id)
            ->get(route('settings.dividend.index'))
            ->assertStatus(200)
            ->assertSee('Dividends');
    }

    public function test_create_form_loads(): void
    {
        [$institute, $owner] = $this->tenantOwner('div-form@example.test');

        $this->asUser($owner, $institute->id)
            ->get(route('settings.dividend.create'))
            ->assertStatus(200)
            ->assertSee('Declare Dividend');
    }

    public function test_dividend_can_be_declared(): void
    {
        [$institute, $owner] = $this->tenantOwner('div-dec@example.test');
        $this->shareholder($institute->id, 1000);

        $this->asUser($owner, $institute->id)
            ->post(route('settings.dividend.store'), [
                'declared_date' => now()->format('Y-m-d'),
                'financial_year' => '2026',
                'total_dividend' => 10000,
            ])->assertRedirect();

        $div = Dividend::where('institute_id', $institute->id)->first();
        $this->assertNotNull($div);
        $this->assertEquals('draft', $div->status);
        $this->assertEquals(10000, $div->payouts()->sum('gross_amount'));
    }

    public function test_per_share_amount_correct(): void
    {
        [$institute, $owner] = $this->tenantOwner('sc-ps@example.test');
        $this->shareholder($institute->id, 1000);

        $this->asUser($owner, $institute->id)
            ->post(route('settings.dividend.store'), [
                'declared_date' => now()->format('Y-m-d'),
                'financial_year' => '2026',
                'total_dividend' => 5000,
            ])->assertRedirect();

        $div = Dividend::where('institute_id', $institute->id)->first();
        $this->assertEquals(5.0, (float) $div->per_share_amount);
        $this->assertEquals(1000, $div->total_shares);
    }

    public function test_payouts_computed_with_tax(): void
    {
        [$institute, $owner] = $this->tenantOwner('sc-tax@example.test');
        $this->shareholder($institute->id, 1000);

        $this->asUser($owner, $institute->id)
            ->post(route('settings.dividend.store'), [
                'declared_date' => now()->format('Y-m-d'),
                'financial_year' => '2026',
                'total_dividend' => 10000,
            ])->assertRedirect();

        $div = Dividend::where('institute_id', $institute->id)->first();
        $payout = $div->payouts()->first();
        $this->assertEquals(10000.0, (float) $payout->gross_amount);
        $this->assertEquals(10.0, (float) $payout->tax_rate);
        $this->assertEquals(1000.0, (float) $payout->tax_amount);
        $this->assertEquals(9000.0, (float) $payout->net_amount);
    }

    public function test_draft_can_be_marked_declared(): void
    {
        [$institute, $owner] = $this->tenantOwner('sc-md@example.test');
        $this->shareholder($institute->id, 100);

        $this->asUser($owner, $institute->id)
            ->post(route('settings.dividend.store'), [
                'declared_date' => now()->format('Y-m-d'),
                'financial_year' => '2026',
                'total_dividend' => 1000,
            ]);

        $div = Dividend::where('institute_id', $institute->id)->first();

        $this->asUser($owner, $institute->id)
            ->post(route('settings.dividend.mark-declared', $div))
            ->assertRedirect();

        $this->assertEquals('declared', $div->fresh()->status);
    }

    public function test_payout_can_be_recorded(): void
    {
        [$institute, $owner] = $this->tenantOwner('sc-pay@example.test');
        $this->shareholder($institute->id, 100);

        $this->asUser($owner, $institute->id)
            ->post(route('settings.dividend.store'), [
                'declared_date' => now()->format('Y-m-d'),
                'financial_year' => '2026',
                'total_dividend' => 1000,
            ]);

        $div = Dividend::where('institute_id', $institute->id)->first();
        $payout = $div->payouts()->first();

        $this->asUser($owner, $institute->id)
            ->post(route('settings.dividend.payouts.pay', [$div, $payout]), [
                'payment_method' => 'bank',
            ])->assertRedirect();

        $this->assertEquals('paid', $payout->fresh()->status);
    }

    public function test_pay_all_marks_dividend_paid(): void
    {
        [$institute, $owner] = $this->tenantOwner('sc-all@example.test');
        $this->shareholder($institute->id, 100);

        $this->asUser($owner, $institute->id)
            ->post(route('settings.dividend.store'), [
                'declared_date' => now()->format('Y-m-d'),
                'financial_year' => '2026',
                'total_dividend' => 1000,
            ]);

        $div = Dividend::where('institute_id', $institute->id)->first();

        $this->asUser($owner, $institute->id)
            ->post(route('settings.dividend.pay-all', $div), [
                'payment_method' => 'bank',
            ])->assertRedirect();

        $this->assertEquals('paid', $div->fresh()->status);
    }

    public function test_dividend_can_be_cancelled(): void
    {
        [$institute, $owner] = $this->tenantOwner('sc-can@example.test');
        $this->shareholder($institute->id, 100);

        $this->asUser($owner, $institute->id)
            ->post(route('settings.dividend.store'), [
                'declared_date' => now()->format('Y-m-d'),
                'financial_year' => '2026',
                'total_dividend' => 1000,
            ]);

        $div = Dividend::where('institute_id', $institute->id)->first();

        $this->asUser($owner, $institute->id)
            ->delete(route('settings.dividend.cancel', $div))
            ->assertRedirect();

        $this->assertEquals('cancelled', $div->fresh()->status);
    }

    public function test_register_report_totals(): void
    {
        [$institute, $owner] = $this->tenantOwner('sc-reg@example.test');
        $this->shareholder($institute->id, 1000);

        $this->asUser($owner, $institute->id)
            ->post(route('settings.dividend.store'), [
                'declared_date' => now()->format('Y-m-d'),
                'financial_year' => '2026',
                'total_dividend' => 10000,
            ]);

        $this->asUser($owner, $institute->id)
            ->get(route('settings.dividend.register'))
            ->assertStatus(200)
            ->assertSee('10,000.00');
    }

    public function test_cross_tenant_dividend_blocked(): void
    {
        [$institute, $owner] = $this->tenantOwner('sc-cross@example.test');
        [$other] = $this->tenantOwner('sc-other@example.test');
        $foreign = Dividend::withoutGlobalScope('institute')->create([
            'institute_id' => $other->id,
            'declared_date' => now()->format('Y-m-d'),
            'financial_year' => '2026',
            'total_dividend' => 500,
            'per_share_amount' => 5,
            'total_shares' => 100,
            'status' => 'draft',
        ]);

        $this->asUser($owner, $institute->id)
            ->get(route('settings.dividend.show', $foreign))
            ->assertStatus(404);
    }

    public function test_capital_summary_correct(): void
    {
        [$institute] = $this->tenantOwner('sc-sum@example.test');
        $this->shareholder($institute->id, 5000);

        $service = app(\App\Services\Accounting\ShareCapitalService::class);
        $service->recomputeInstituteCapital($institute->id);
        $svc = app(\App\Services\Accounting\DividendService::class);
        $summary = $svc->getSummary($institute->id);

        $this->assertIsArray($summary);
        $this->assertEquals(0, $summary['total_dividends']);
    }
}
