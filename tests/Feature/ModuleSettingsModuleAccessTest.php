<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\PackageModule;
use App\Models\Role;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * SEC-04: the self-service module settings endpoint
 * (POST settings/modules/toggle) must route every toggle through
 * ModuleAccessService::enableModule()/disableModule() (same methods as the
 * admin path) — no raw
 * DB::table('institute_module_overrides') writes.
 */
class ModuleSettingsModuleAccessTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;

    private InstituteUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        $free = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['free'])->firstOrFail();

        $this->institute = Institute::create([
            'name' => 'SEC04 '.uniqid(),
            'slug' => 'sec04-'.uniqid(),
            'status' => 'active',
            'package_id' => $free->id,
            'industry' => 'healthcare',
            'country' => 'Bangladesh',
        ]);

        // The free package does not ship these keys; isPackageAllowed() in
        // ModuleManagementController::toggle() requires them.
        foreach (['medical.opd', 'education'] as $key) {
            PackageModule::updateOrCreate(
                ['package_id' => $free->id, 'module_key' => $key],
                ['enabled' => true]
            );
        }

        $role = Role::where('slug', 'institute-owner')->firstOrFail();

        $prev = TenantContext::id();
        TenantContext::clear();
        $this->user = InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $role->id,
            'first_name' => 'SEC04',
            'last_name' => 'User',
            'email' => 'sec04-'.uniqid().'@test.local',
            'phone' => '017'.rand(10000000, 99999999),
            'password_hash' => bcrypt('secret'),
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        if ($prev !== null) {
            TenantContext::set($prev);
        }

        app(ModuleAccessService::class)->flushCache($this->institute->id);
    }

    private function toggle(string $moduleKey, bool $enabled)
    {
        return $this->actingAs($this->user, 'institute_user')
            ->postJson(route('settings.modules.toggle'), [
                'module_key' => $moduleKey,
                'enabled' => $enabled,
            ]);
    }

    public function test_enable_creates_override_row(): void
    {
        $this->toggle('medical.opd', true)
            ->assertOk()
            ->assertJson(['status' => 'ok', 'module_key' => 'medical.opd', 'enabled' => true]);

        $this->assertDatabaseHas('institute_module_overrides', [
            'institute_id' => $this->institute->id,
            'module_key' => 'medical.opd',
            'enabled' => true,
        ]);
    }

    public function test_disable_sets_override_false(): void
    {
        $this->toggle('medical.opd', true)->assertOk();
        $this->assertDatabaseHas('institute_module_overrides', [
            'institute_id' => $this->institute->id,
            'module_key' => 'medical.opd',
            'enabled' => true,
        ]);

        $this->toggle('medical.opd', false)
            ->assertOk()
            ->assertJson(['status' => 'ok', 'module_key' => 'medical.opd', 'enabled' => false]);

        $this->assertDatabaseHas('institute_module_overrides', [
            'institute_id' => $this->institute->id,
            'module_key' => 'medical.opd',
            'enabled' => false,
        ]);
    }

    public function test_industry_incompatible_module_stays_effectively_disabled(): void
    {
        // Healthcare institute: 'education' is industry-disabled
        // (config industry-modules.healthcare.disabled), so the service layer
        // records the override but resolution keeps it off.
        $this->toggle('education', true)
            ->assertOk()
            ->assertJson(['status' => 'ok', 'module_key' => 'education', 'enabled' => true]);

        $this->assertDatabaseHas('institute_module_overrides', [
            'institute_id' => $this->institute->id,
            'module_key' => 'education',
            'enabled' => true,
        ]);
        $this->assertFalse(
            app(ModuleAccessService::class)->isEnabled($this->institute, 'education')
        );
    }

    public function test_enable_writes_audit_log_with_actor(): void
    {
        $this->toggle('medical.opd', true)->assertOk();

        $this->assertDatabaseHas('module_access_logs', [
            'institute_id' => $this->institute->id,
            'module_key' => 'medical.opd',
            'action' => 'enable',
            'actor_id' => $this->user->id,
        ]);
    }
}
