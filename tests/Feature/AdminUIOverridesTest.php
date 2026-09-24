<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\ModuleAccessLog;
use App\Models\PlatformAdmin;
use App\Models\Role;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Phase 5 — Admin UI: module access page, hard-boundary enforcement,
 * audit log, bulk overview, super admin emergency override (2FA),
 * and tenant terminology overrides.
 */
class AdminUIOverridesTest extends TestCase
{
    use DatabaseTransactions;

    private PlatformAdmin $admin;

    private Institute $institute;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'phase5-admin-' . uniqid() . '@example.test',
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $pkg = SubscriptionPackage::where('slug', 'basic')->firstOrFail();

        $this->institute = Institute::create([
            'name' => 'Phase5 UI Tenant',
            'slug' => 'phase5-ui-' . uniqid(),
            'status' => 'active',
            'package_id' => $pkg->id,
            'industry' => 'education',
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    // ─── 1. Tenant module access page ──────────────────────

    public function test_view_tenant_modules_page(): void
    {
        $response = $this->actingAs($this->admin, 'platform_admin')
            ->get(route('admin.institutes.modules', $this->institute));

        $response->assertOk();
        $response->assertSee($this->institute->name);
        $response->assertSee('Industry');
        $response->assertSee('Audit Log');
    }

    // ─── 2. Package override toggle (with reason) ──────────

    public function test_toggle_package_override_with_reason_succeeds(): void
    {
        $service = app(ModuleAccessService::class);
        $this->assertFalse($service->isEnabled($this->institute, 'inventory'));

        $desired = array_values(array_unique(array_merge(
            $service->getEnabledModules($this->institute),
            ['inventory']
        )));

        $response = $this->actingAs($this->admin, 'platform_admin')
            ->put(route('admin.institutes.modules.update', $this->institute), [
                'modules' => $desired,
                'reason' => 'Book inventory module for campus store.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertTrue($service->isEnabled($this->institute, 'inventory'));
        $this->assertDatabaseHas('institute_module_overrides', [
            'institute_id' => $this->institute->id,
            'module_key' => 'inventory',
            'enabled' => true,
        ]);
        $this->assertDatabaseHas('module_access_logs', [
            'institute_id' => $this->institute->id,
            'module_key' => 'inventory',
            'action' => 'enable',
        ]);
    }

    // ─── 3. Industry hard boundary blocks enable ───────────

    public function test_industry_boundary_blocks_enable(): void
    {
        $service = app(ModuleAccessService::class);
        $this->assertSame('education', $this->institute->industry);
        $this->assertFalse($service->isIndustryCompatible($this->institute, 'medical'));

        $response = $this->actingAs($this->admin, 'platform_admin')
            ->put(route('admin.institutes.modules.update', $this->institute), [
                'modules' => array_merge($service->getEnabledModules($this->institute), ['medical']),
                'reason' => 'Trying to bypass the industry boundary.',
            ]);

        $response->assertSessionHasErrors('modules');
        $this->assertStringContainsString(
            'industry boundary',
            strtolower(implode(' ', $response->getSession()->get('errors')->get('modules')))
        );
        $this->assertFalse($service->isEnabled($this->institute, 'medical'));
    }

    // ─── 4. Country hard boundary blocks enable ────────────

    public function test_country_boundary_blocks_enable(): void
    {
        $this->institute->country_code = 'BD';
        $this->institute->save();

        // gst is a tax module BD does not allow (BD = vat + tds only).
        // Register it so the exists:module_registry validation passes and
        // the boundary check is what actually rejects it.
        DB::table('module_registry')->insert([
            'key' => 'gst',
            'name' => 'GST (test)',
            'type' => 'core',
            'is_core' => 0,
            'status' => 'active',
            'sort_order' => 99,
        ]);

        $service = app(ModuleAccessService::class);
        $this->assertFalse($service->isCountryTaxAllowed('gst', $this->institute));

        $response = $this->actingAs($this->admin, 'platform_admin')
            ->put(route('admin.institutes.modules.update', $this->institute), [
                'modules' => ['crm', 'gst'],
                'reason' => 'Trying to bypass the country boundary.',
            ]);

        $response->assertSessionHasErrors('modules');
        $this->assertStringContainsString(
            'country boundary',
            strtolower(implode(' ', $response->getSession()->get('errors')->get('modules')))
        );
        $this->assertFalse($service->isEnabled($this->institute, 'gst'));
    }

    // ─── 5. Audit log records the action ───────────────────

    public function test_access_log_page_records_enable_action(): void
    {
        $service = app(ModuleAccessService::class);
        $service->enableModule($this->institute, 'inventory', $this->admin->id, 'Audit trail test');

        $log = ModuleAccessLog::where('institute_id', $this->institute->id)
            ->where('module_key', 'inventory')
            ->where('action', 'enable')
            ->first();
        $this->assertNotNull($log);

        $response = $this->actingAs($this->admin, 'platform_admin')
            ->get(route('admin.institutes.access-log', $this->institute));

        $response->assertOk();
        $response->assertSee('inventory');
        $response->assertSee('enable');
    }

    // ─── 6. Bulk overview shows tenants ────────────────────

    public function test_overview_shows_tenants(): void
    {
        $response = $this->actingAs($this->admin, 'platform_admin')
            ->get(route('admin.institutes.modules-overview', [
                'search' => $this->institute->name,
            ]));

        $response->assertOk();
        $response->assertSee($this->institute->name);
        $response->assertSee('Overrides');
    }

    // ─── 7. Super admin emergency override (2FA) ────────────

    public function test_super_admin_emergency_override_with_2fa(): void
    {
        $g2fa = new Google2FA();
        // 16 chars matches Fortify's own secret length (fits varchar(255) encrypted).
        $secret = $g2fa->generateSecretKey(16);

        $this->admin->two_factor_secret = Crypt::encryptString($secret);
        $this->admin->two_factor_confirmed_at = now();
        $this->admin->save();
        $this->admin->refresh();

        $code = $g2fa->getCurrentOtp($secret);
        $this->assertNotEmpty($code);

        $reason = 'Hospital wing acquisition requires OPD modules during the transition period.';

        $response = $this->actingAs($this->admin, 'platform_admin')->post(
            route('super-admin.institutes.emergency-override.store', $this->institute),
            [
                'module_key' => 'medical',
                'override_layer' => 'industry',
                'reason' => $reason,
                'two_factor_code' => $code,
                'confirmation_text' => 'I UNDERSTAND THE RISK',
                'expiry_days' => 7,
            ]
        );

        $response->assertRedirect(route('admin.institutes.modules', $this->institute));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('super_admin_overrides', [
            'institute_id' => $this->institute->id,
            'module_key' => 'medical',
            'override_layer' => 'industry',
            'two_factor_verified' => true,
        ]);
        $this->assertDatabaseHas('module_access_logs', [
            'institute_id' => $this->institute->id,
            'module_key' => 'medical',
            'action' => 'emergency_override',
        ]);
    }

    public function test_emergency_override_rejects_invalid_2fa_code(): void
    {
        $g2fa = new Google2FA();
        $secret = $g2fa->generateSecretKey(16);

        $this->admin->two_factor_secret = Crypt::encryptString($secret);
        $this->admin->two_factor_confirmed_at = now();
        $this->admin->save();

        $response = $this->actingAs($this->admin, 'platform_admin')->post(
            route('super-admin.institutes.emergency-override.store', $this->institute),
            [
                'module_key' => 'medical',
                'override_layer' => 'industry',
                'reason' => 'Hospital wing acquisition requires OPD modules during the transition period.',
                'two_factor_code' => '000000',
                'confirmation_text' => 'I UNDERSTAND THE RISK',
                'expiry_days' => 7,
            ]
        );

        $response->assertSessionHasErrors('two_factor_code');
        $this->assertDatabaseMissing('super_admin_overrides', [
            'institute_id' => $this->institute->id,
            'module_key' => 'medical',
        ]);
    }

    // ─── 8. Tenant terminology override ────────────────────

    public function test_terminology_override_saves(): void
    {
        $owner = InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => Role::where('slug', 'institute-owner')->firstOrFail()->id,
            'first_name' => 'Term',
            'last_name' => 'Owner',
            'email' => 'term-owner-' . uniqid() . '@test.test',
            'phone' => '017' . mt_rand(10000000, 99999999),
            'password_hash' => bcrypt('secret'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        TenantContext::set($this->institute->id);
        $this->actingAs($owner, 'institute_user');

        $page = $this->get(route('settings.terminology.index'));
        $page->assertOk();
        $page->assertSee('common.customer');

        $response = $this->put(route('settings.terminology.update'), [
            'terms' => [
                'common.customer' => 'Client',
            ],
        ]);
        $response->assertSessionHas('success');

        $this->institute->refresh();
        $overrides = is_string($this->institute->terminology_overrides)
            ? json_decode($this->institute->terminology_overrides, true)
            : $this->institute->terminology_overrides;

        $this->assertSame('Client', $overrides['common.customer'] ?? null);

        $page2 = $this->get(route('settings.terminology.index'));
        $page2->assertOk();
        $page2->assertSee('Client');
    }

    public function test_terminology_empty_value_removes_override(): void
    {
        $owner = InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => Role::where('slug', 'institute-owner')->firstOrFail()->id,
            'first_name' => 'Term',
            'last_name' => 'Owner',
            'email' => 'term-owner-' . uniqid() . '@test.test',
            'phone' => '017' . mt_rand(10000000, 99999999),
            'password_hash' => bcrypt('secret'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $service = app(\App\Services\TerminologyService::class);
        $service->setOverride($this->institute, 'common.customer', 'Client');

        TenantContext::set($this->institute->id);
        $this->actingAs($owner, 'institute_user');

        $response = $this->put(route('settings.terminology.update'), [
            'terms' => ['common.customer' => ''],
        ]);
        $response->assertSessionHas('success');

        $this->institute->refresh();
        $overrides = is_string($this->institute->terminology_overrides)
            ? json_decode($this->institute->terminology_overrides, true)
            : ($this->institute->terminology_overrides ?? []);

        $this->assertArrayNotHasKey('common.customer', $overrides);
    }
}
