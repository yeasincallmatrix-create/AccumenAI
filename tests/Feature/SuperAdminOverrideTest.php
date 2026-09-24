<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\PlatformAdmin;
use App\Services\ModuleAccessService;
use App\Services\SuperAdminOverrideService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Phase 6 — Emergency override store flow: 2FA required, typed confirmation,
 * 50-char justification, expiry, email alert, risk_level audit.
 */
class SuperAdminOverrideTest extends TestCase
{
    use DatabaseTransactions;

    private PlatformAdmin $admin;

    private ModuleAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'override-test-' . uniqid() . '@example.test',
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->service = app(ModuleAccessService::class);
    }

    public function test_super_admin_can_create_industry_override(): void
    {
        $inst = $this->makeInstitute('education', 'school', 'BD');
        // createOverride() records auth()->id() as approved_by (FK to
        // platform_admins) — establish the authenticated actor first.
        $this->actingAs($this->admin, 'platform_admin');

        $id = app(SuperAdminOverrideService::class)
            ->createOverride($inst, 'medical', 'industry', 'Automated verification of industry override creation.');

        $this->assertDatabaseHas('super_admin_overrides', [
            'id' => $id,
            'institute_id' => $inst->id,
            'module_key' => 'medical',
            'override_layer' => 'industry',
        ]);
        $this->assertTrue(
            app(SuperAdminOverrideService::class)->isActive($inst, 'medical', 'industry'),
            'freshly created override must be active'
        );
    }

    public function test_super_admin_can_create_country_override(): void
    {
        $inst = $this->makeInstitute('retail', 'grocery', 'US');
        // createOverride() records auth()->id() as approved_by (FK to
        // platform_admins) — establish the authenticated actor first.
        $this->actingAs($this->admin, 'platform_admin');

        $id = app(SuperAdminOverrideService::class)
            ->createOverride($inst, 'vat', 'country', 'Automated verification of country override creation.');

        $this->assertDatabaseHas('super_admin_overrides', [
            'id' => $id,
            'override_layer' => 'country',
        ]);
        $this->assertTrue(app(SuperAdminOverrideService::class)->isActive($inst, 'vat', 'country'));
    }

    public function test_override_requires_2fa(): void
    {
        $inst = $this->makeInstitute('education', 'school', 'BD');
        // No two_factor_secret configured on this admin.

        $response = $this->actingAs($this->admin, 'platform_admin')
            ->post(route('super-admin.institutes.emergency-override.store', $inst), [
                'module_key' => 'medical',
                'override_layer' => 'industry',
                'reason' => 'Emergency justification long enough to pass validation gate check.',
                'confirmation_text' => 'I UNDERSTAND THE RISK',
                'expiry_days' => 7,
            ]);

        $response->assertSessionHasErrors('two_factor_code');
        $this->assertDatabaseMissing('super_admin_overrides', [
            'institute_id' => $inst->id,
            'module_key' => 'medical',
        ]);
    }

    public function test_override_requires_50_char_justification(): void
    {
        $inst = $this->makeInstitute('education', 'school', 'BD');
        $shortReason = str_repeat('x', 49);

        $response = $this->actingAs($this->admin, 'platform_admin')
            ->post(route('super-admin.institutes.emergency-override.store', $inst), [
                'module_key' => 'medical',
                'override_layer' => 'industry',
                'reason' => $shortReason,
                'two_factor_code' => '000000',
                'confirmation_text' => 'I UNDERSTAND THE RISK',
                'expiry_days' => 7,
            ]);

        $response->assertSessionHasErrors('reason');
        $this->assertDatabaseMissing('super_admin_overrides', [
            'institute_id' => $inst->id,
            'module_key' => 'medical',
        ]);
    }

    public function test_override_requires_typed_confirmation(): void
    {
        $inst = $this->makeInstitute('education', 'school', 'BD');

        $response = $this->actingAs($this->admin, 'platform_admin')
            ->post(route('super-admin.institutes.emergency-override.store', $inst), [
                'module_key' => 'medical',
                'override_layer' => 'industry',
                'reason' => 'Emergency justification long enough to pass the validation minimum gate.',
                'two_factor_code' => '000000',
                'confirmation_text' => 'yes i understand',
                'expiry_days' => 7,
            ]);

        $response->assertSessionHasErrors('confirmation_text');
        $this->assertDatabaseMissing('super_admin_overrides', [
            'institute_id' => $inst->id,
            'module_key' => 'medical',
        ]);
    }

    public function test_override_expires_after_duration(): void
    {
        $inst = $this->makeInstitute('education', 'school', 'BD');
        $code = $this->enable2fa();

        $response = $this->actingAs($this->admin, 'platform_admin')
            ->post(route('super-admin.institutes.emergency-override.store', $inst), [
                'module_key' => 'medical',
                'override_layer' => 'industry',
                'reason' => 'Emergency expiry duration verification justification text here.',
                'two_factor_code' => $code,
                'confirmation_text' => 'I UNDERSTAND THE RISK',
                'expiry_days' => 1,
            ]);

        $response->assertRedirect();

        $row = DB::table('super_admin_overrides')
            ->where('institute_id', $inst->id)
            ->where('module_key', 'medical')
            ->first();

        $this->assertNotNull($row, 'override row created');
        $this->assertNotNull($row->expires_at);

        $hoursUntilExpiry = now()->diffInHours(now()->setTimestamp(strtotime($row->expires_at)), false);
        $this->assertGreaterThan(23, $hoursUntilExpiry, 'expires_at ~now+1 day (lower bound)');
        $this->assertLessThan(25, $hoursUntilExpiry, 'expires_at ~now+1 day (upper bound)');
    }

    public function test_override_sends_email_alert(): void
    {
        Mail::fake();
        $inst = $this->makeInstitute('education', 'school', 'BD');
        $code = $this->enable2fa();

        $this->actingAs($this->admin, 'platform_admin')
            ->post(route('super-admin.institutes.emergency-override.store', $inst), [
                'module_key' => 'medical',
                'override_layer' => 'industry',
                'reason' => 'Emergency email alert verification justification for this override.',
                'two_factor_code' => $code,
                'confirmation_text' => 'I UNDERSTAND THE RISK',
                'expiry_days' => 7,
            ]);

        $row = DB::table('super_admin_overrides')
            ->where('institute_id', $inst->id)
            ->where('module_key', 'medical')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame($this->admin->email, $row->email_sent_to, 'alert recipient recorded');

        $critical = DB::table('module_access_logs')
            ->where('institute_id', $inst->id)
            ->where('action', 'emergency_override')
            ->where('risk_level', 'critical')
            ->exists();
        $this->assertTrue($critical, 'emergency override logged with risk_level=critical');
    }

    public function test_expired_override_not_applied(): void
    {
        $inst = $this->makeInstitute('education', 'school', 'BD');

        DB::table('super_admin_overrides')->insert([
            'institute_id' => $inst->id,
            'module_key' => 'medical',
            'override_layer' => 'industry',
            'reason' => 'Already expired probe row for resolution check.',
            'two_factor_verified' => true,
            'started_at' => now()->subDays(3),
            'expires_at' => now()->subDay(),
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);
        $this->service->flushCache($inst->id);

        $this->assertNotContains(
            'medical',
            array_keys(array_filter($this->service->resolveEnabled($inst))),
            'expired override must not apply'
        );
    }

    // ─── Helpers ───────────────────────────────────────────

    private function enable2fa(): string
    {
        $g2fa = new Google2FA();
        $secret = $g2fa->generateSecretKey(16);

        $this->admin->two_factor_secret = Crypt::encryptString($secret);
        $this->admin->two_factor_confirmed_at = now();
        $this->admin->save();

        return $g2fa->getCurrentOtp($secret);
    }

    private function makeInstitute(string $industry, string $subcategory, string $country): Institute
    {
        $suffix = substr(uniqid(), -8);
        $pkgId = DB::table('subscription_packages')->where('slug', 'basic')->value('id');

        $inst = Institute::withoutEvents(function () use ($industry, $subcategory, $country, $pkgId, $suffix) {
            $inst = Institute::create([
                'name' => "SAOverride {$industry} {$suffix}",
                'slug' => "sa-override-{$industry}-{$suffix}",
                'status' => 'active',
                'country' => 'Bangladesh',
                'industry' => $industry,
                'package_id' => $pkgId,
            ]);
            $inst->subcategory_key = $subcategory;
            $inst->country_code = $country;
            $inst->save();

            return $inst;
        });

        DB::table('institute_subscriptions')->insert([
            'institute_id' => $inst->id,
            'package_id' => $pkgId,
            'billing_cycle' => 'yearly',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
        ]);

        return $inst->fresh();
    }
}
