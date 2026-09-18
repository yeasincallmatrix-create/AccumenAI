<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * SEC-04: the self-service module settings endpoint must route every
 * toggle through ModuleAccessService::enableModule()/disableModule()
 * (same methods as the admin path) — no raw
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
            'industry' => 'education',
            'sub_industry' => 'school',
            'country' => 'Bangladesh',
        ]);

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
    }

    private function postModules(array $modules)
    {
        return $this->actingAs($this->user, 'institute_user')
            ->post(route('settings.modules.update'), ['modules' => $modules]);
    }

    public function test_enable_creates_override_row(): void
    {
        $this->postModules(['medical.opd'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Medical sub-modules updated successfully.');

        $this->assertDatabaseHas('institute_module_overrides', [
            'institute_id' => $this->institute->id,
            'module_key' => 'medical.opd',
            'enabled' => true,
        ]);
    }

    public function test_disable_sets_override_false(): void
    {
        $this->postModules(['medical.opd']);
        $this->assertDatabaseHas('institute_module_overrides', [
            'institute_id' => $this->institute->id,
            'module_key' => 'medical.opd',
            'enabled' => true,
        ]);

        $this->postModules([])
            ->assertRedirect()
            ->assertSessionHas('success', 'Medical sub-modules updated successfully.');

        $this->assertDatabaseHas('institute_module_overrides', [
            'institute_id' => $this->institute->id,
            'module_key' => 'medical.opd',
            'enabled' => false,
        ]);
    }

    public function test_industry_incompatible_module_stays_effectively_disabled(): void
    {
        // Education institute: parent 'medical' is industry-incompatible, so
        // the service layer records the override but resolution keeps it off.
        // (enableModule() writes per service semantics — it does not throw;
        // enforcement lives in resolveEnabled(), as on the admin path.)
        $this->postModules(['medical.opd']);

        $this->assertDatabaseHas('institute_module_overrides', [
            'institute_id' => $this->institute->id,
            'module_key' => 'medical.opd',
            'enabled' => true,
        ]);
        $this->assertFalse(
            app(ModuleAccessService::class)->isEnabled($this->institute->fresh(), 'medical.opd')
        );
    }

    public function test_enable_writes_audit_log_with_actor(): void
    {
        $this->postModules(['medical.opd']);

        $this->assertDatabaseHas('module_access_logs', [
            'institute_id' => $this->institute->id,
            'module_key' => 'medical.opd',
            'action' => 'enable',
            'actor_id' => $this->user->id,
        ]);
    }
}
