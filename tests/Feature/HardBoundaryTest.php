<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\PlatformAdmin;
use App\Services\ModuleAccessService;
use App\Services\SuperAdminOverrideService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Phase 6 — Hard boundary enforcement (Layer 7 industry / Layer 8 country):
 * platform admin can NEVER bypass them; only a Super Admin 2FA emergency
 * override can, and only while it is non-expired.
 */
class HardBoundaryTest extends TestCase
{
    use DatabaseTransactions;

    private PlatformAdmin $admin;

    private ModuleAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'hard-boundary-' . uniqid() . '@example.test',
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->service = app(ModuleAccessService::class);
    }

    // ─── Admin attempts (Layer 7) ──────────────────────────

    public function test_admin_cannot_grant_medical_to_education_tenant(): void
    {
        $inst = $this->makeInstitute('education', 'school', 'BD');

        $response = $this->actingAs($this->admin, 'platform_admin')
            ->put(route('admin.institutes.modules.update', $inst), [
                'modules' => array_merge($this->service->getEnabledModules($inst), ['medical']),
                'reason' => 'Attempting industry bypass.',
            ]);

        $response->assertSessionHasErrors('modules');
        $this->assertStringContainsString('industry boundary', $this->errorText($response, 'modules'));
        $this->assertFalse($this->service->isEnabled($inst, 'medical'));
    }

    public function test_admin_cannot_grant_medical_to_retail_tenant(): void
    {
        $inst = $this->makeInstitute('retail', 'grocery', 'BD');

        $response = $this->actingAs($this->admin, 'platform_admin')
            ->put(route('admin.institutes.modules.update', $inst), [
                'modules' => array_merge($this->service->getEnabledModules($inst), ['medical']),
                'reason' => 'Attempting industry bypass.',
            ]);

        $response->assertSessionHasErrors('modules');
        $this->assertStringContainsString('industry boundary', $this->errorText($response, 'modules'));
        $this->assertFalse($this->service->isEnabled($inst, 'medical'));
    }

    // ─── Admin attempts (Layer 8) ──────────────────────────

    public function test_admin_cannot_grant_gst_to_bd_tenant(): void
    {
        $this->ensureRegistryKey('gst', 'GST');
        $inst = $this->makeInstitute('retail', 'grocery', 'BD');

        $response = $this->actingAs($this->admin, 'platform_admin')
            ->put(route('admin.institutes.modules.update', $inst), [
                'modules' => ['crm', 'gst'],
                'reason' => 'Attempting country bypass.',
            ]);

        $response->assertSessionHasErrors('modules');
        $this->assertStringContainsString('country boundary', $this->errorText($response, 'modules'));
        $this->assertFalse($this->service->isEnabled($inst, 'gst'));
    }

    public function test_admin_cannot_grant_sales_tax_to_bd_tenant(): void
    {
        $this->ensureRegistryKey('sales_tax', 'Sales Tax');
        $inst = $this->makeInstitute('retail', 'grocery', 'BD');

        $response = $this->actingAs($this->admin, 'platform_admin')
            ->put(route('admin.institutes.modules.update', $inst), [
                'modules' => ['crm', 'sales_tax'],
                'reason' => 'Attempting country bypass.',
            ]);

        $response->assertSessionHasErrors('modules');
        $this->assertStringContainsString('country boundary', $this->errorText($response, 'modules'));
        $this->assertFalse($this->service->isEnabled($inst, 'sales_tax'));
    }

    // ─── Admin attempts that MUST succeed ──────────────────

    public function test_admin_can_grant_pharmacy_to_healthcare_tenant(): void
    {
        $inst = $this->makeInstitute('healthcare', 'pharmacy', 'BD');
        $this->service->disableModule($inst, 'medical.laboratory', $this->admin->id, 'reset for test');
        $this->assertFalse($this->service->isEnabled($inst, 'medical.laboratory'), 'precondition');

        $response = $this->actingAs($this->admin, 'platform_admin')
            ->put(route('admin.institutes.modules.update', $inst), [
                'modules' => array_merge($this->service->getEnabledModules($inst), ['medical.laboratory']),
                'reason' => 'Lab services onboarded for this pharmacy.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertTrue($this->service->isEnabled($inst, 'medical.laboratory'));
    }

    public function test_admin_can_grant_vat_to_bd_tenant(): void
    {
        $inst = $this->makeInstitute('retail', 'grocery', 'BD');

        $response = $this->actingAs($this->admin, 'platform_admin')
            ->put(route('admin.institutes.modules.update', $inst), [
                'modules' => array_merge($this->service->getEnabledModules($inst), ['vat']),
                'reason' => 'VAT registered.',
            ]);

        $response->assertRedirect();
        $response->assertSessionMissing('errors');
        $this->assertTrue($this->service->isEnabled($inst, 'vat'));
    }

    // ─── Super Admin 2FA emergency path ────────────────────

    public function test_super_admin_can_override_industry_with_2fa(): void
    {
        $inst = $this->makeInstitute('education', 'school', 'BD');
        $code = $this->enable2fa();

        $response = $this->actingAs($this->admin, 'platform_admin')
            ->post(route('super-admin.institutes.emergency-override.store', $inst), [
                'module_key' => 'medical',
                'override_layer' => 'industry',
                'reason' => 'Emergency: attached infirmary requires medical module during transition.',
                'two_factor_code' => $code,
                'confirmation_text' => 'I UNDERSTAND THE RISK',
                'expiry_days' => 7,
            ]);

        $response->assertRedirect(route('admin.institutes.modules', $inst));
        $this->assertDatabaseHas('super_admin_overrides', [
            'institute_id' => $inst->id,
            'module_key' => 'medical',
            'override_layer' => 'industry',
        ]);
        $this->assertTrue($this->service->isEnabled($inst, 'medical'), 'override must take effect in resolution');
    }

    public function test_super_admin_can_override_country_with_2fa(): void
    {
        // US tenant: Layer 8 blocks vat (US allows sales_tax only).
        $inst = $this->makeInstitute('retail', 'grocery', 'US');
        $this->assertFalse($this->service->isCountryTaxAllowed('vat', $inst), 'precondition');
        $code = $this->enable2fa();

        $response = $this->actingAs($this->admin, 'platform_admin')
            ->post(route('super-admin.institutes.emergency-override.store', $inst), [
                'module_key' => 'vat',
                'override_layer' => 'country',
                'reason' => 'Emergency: cross-border filing requires vat module for this tenant now.',
                'two_factor_code' => $code,
                'confirmation_text' => 'I UNDERSTAND THE RISK',
                'expiry_days' => 7,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('super_admin_overrides', [
            'institute_id' => $inst->id,
            'module_key' => 'vat',
            'override_layer' => 'country',
        ]);
        $this->assertTrue($this->service->isEnabled($inst, 'vat'), 'country override must take effect');
    }

    public function test_super_admin_override_expires(): void
    {
        $inst = $this->makeInstitute('education', 'school', 'BD');
        // createOverride() records auth()->id() as approved_by (FK to
        // platform_admins) — establish the authenticated actor first.
        $this->actingAs($this->admin, 'platform_admin');
        $overrideId = app(SuperAdminOverrideService::class)
            ->createOverride($inst, 'medical', 'industry', 'Expiry verification override reason text.', 1);

        $row = DB::table('super_admin_overrides')->where('id', $overrideId)->first();
        $this->assertNotNull($row->expires_at);
        $this->assertTrue(now()->addHours(25)->gte($row->expires_at), 'expiry ~1 day');

        // Active now → applied
        $this->assertTrue($this->service->isEnabled($inst, 'medical'), 'active override applies');

        // Expire it → not applied (flush: isEnabled reads the 1h cache)
        DB::table('super_admin_overrides')->where('id', $overrideId)->update(['expires_at' => now()->subMinute()]);
        $this->service->flushCache($inst->id);
        $this->assertFalse($this->service->isEnabled($inst, 'medical'), 'expired override no longer applies');
    }

    // ─── Resolver-level hard boundaries ────────────────────

    public function test_hard_boundary_enforced_in_resolve_enabled(): void
    {
        $edu = $this->makeInstitute('education', 'school', 'BD');
        $us = $this->makeInstitute('retail', 'grocery', 'US');

        $eduKeys = array_keys(array_filter($this->service->resolveEnabled($edu)));
        $usKeys = array_keys(array_filter($this->service->resolveEnabled($us)));

        $this->assertEmpty(array_values(array_filter($eduKeys, fn ($m) => str_starts_with($m, 'medical'))));
        $this->assertNotContains('vat', $usKeys);
    }

    public function test_admin_override_does_not_bypass_hard_boundary(): void
    {
        $inst = $this->makeInstitute('education', 'school', 'BD');

        $this->actingAs($this->admin, 'platform_admin')
            ->put(route('admin.institutes.modules.update', $inst), [
                'modules' => array_merge($this->service->getEnabledModules($inst), ['medical']),
                'reason' => 'Boundary probe.',
            ]);

        // The blocked request must not have written an enable override row.
        $this->assertDatabaseMissing('institute_module_overrides', [
            'institute_id' => $inst->id,
            'module_key' => 'medical',
            'enabled' => true,
        ]);
        $this->assertNotContains('medical', array_keys(array_filter($this->service->resolveEnabled($inst))));
    }

    public function test_tenant_override_does_not_bypass_hard_boundary(): void
    {
        $inst = $this->makeInstitute('education', 'school', 'BD');

        DB::table('institute_module_overrides')->insert([
            'institute_id' => $inst->id,
            'module_key' => 'medical',
            'enabled' => true,
            'reason' => 'direct tenant row probe',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->service->flushCache($inst->id);

        $this->assertNotContains(
            'medical',
            array_keys(array_filter($this->service->resolveEnabled($inst))),
            'Layer 7 must veto a direct tenant override row'
        );
    }

    // ─── Helpers ───────────────────────────────────────────

    private function errorText($response, string $key): string
    {
        return strtolower(implode(' ', $response->getSession()->get('errors')->get($key) ?: []));
    }

    private function ensureRegistryKey(string $key, string $name): void
    {
        $exists = DB::table('module_registry')->where('key', $key)->exists();
        if (! $exists) {
            DB::table('module_registry')->insert([
                'key' => $key,
                'name' => $name,
                'type' => 'core',
                'is_core' => 0,
                'status' => 'active',
                'sort_order' => 98,
            ]);
        }
    }

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
                'name' => "Phase6 {$industry} {$suffix}",
                'slug' => "phase6-{$industry}-{$suffix}",
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
