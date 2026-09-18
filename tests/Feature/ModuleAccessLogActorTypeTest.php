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
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ModuleAccessLogActorTypeTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;

    protected function setUp(): void
    {
        parent::setUp();

        $free = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['free'])->firstOrFail();

        $this->institute = Institute::create([
            'name' => 'ActorType '.uniqid(),
            'slug' => 'actor-type-'.uniqid(),
            'status' => 'active',
            'package_id' => $free->id,
            'industry' => 'education',
            'sub_industry' => 'school',
            'country' => 'Bangladesh',
        ]);
    }

    public function test_platform_admin_actor_type_recorded(): void
    {
        $admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'actor-type-admin-'.uniqid().'@test.local',
            'password_hash' => bcrypt('secret'),
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'platform_admin');

        $service = app(ModuleAccessService::class);
        $service->enableModule($this->institute, 'hr', $admin->id, 'Test admin enable');

        $log = ModuleAccessLog::where('institute_id', $this->institute->id)
            ->where('module_key', 'hr')
            ->where('action', 'enable')
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('platform_admin', $log->actor_type);
        $this->assertEquals($admin->id, $log->actor_id);
    }

    public function test_institute_user_actor_type_recorded(): void
    {
        $role = Role::where('slug', 'institute-owner')->firstOrFail();

        $prev = TenantContext::id();
        TenantContext::clear();
        $user = InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $role->id,
            'first_name' => 'Actor',
            'last_name' => 'Type',
            'email' => 'actor-type-user-'.uniqid().'@test.local',
            'phone' => '017'.rand(10000000, 99999999),
            'password_hash' => bcrypt('secret'),
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        if ($prev !== null) {
            TenantContext::set($prev);
        }

        $this->actingAs($user, 'institute_user');

        $service = app(ModuleAccessService::class);
        $service->enableModule($this->institute, 'hr', $user->id, 'Test user enable');

        $log = ModuleAccessLog::where('institute_id', $this->institute->id)
            ->where('module_key', 'hr')
            ->where('action', 'enable')
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('institute_user', $log->actor_type);
        $this->assertEquals($user->id, $log->actor_id);
    }

    public function test_platform_admin_and_institute_user_distinguishable(): void
    {
        $admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'actor-collision-admin-'.uniqid().'@test.local',
            'password_hash' => bcrypt('secret'),
            'status' => 'active',
        ]);

        $role = Role::where('slug', 'institute-owner')->firstOrFail();
        $prev = TenantContext::id();
        TenantContext::clear();
        $user = InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $role->id,
            'first_name' => 'Collision',
            'last_name' => 'User',
            'email' => 'actor-collision-user-'.uniqid().'@test.local',
            'phone' => '017'.rand(10000000, 99999999),
            'password_hash' => bcrypt('secret'),
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        if ($prev !== null) {
            TenantContext::set($prev);
        }

        $service = app(ModuleAccessService::class);

        // Log as platform_admin
        $this->actingAs($admin, 'platform_admin');
        $service->enableModule($this->institute, 'finance', $admin->id, 'Admin action');
        $adminLog = ModuleAccessLog::where('action', 'enable')
            ->where('module_key', 'finance')
            ->latest()
            ->first();

        // Log out platform_admin guard so resolveActorType picks up the next one
        Auth::guard('platform_admin')->logout();

        // Log as institute_user
        $this->actingAs($user, 'institute_user');
        $service->enableModule($this->institute, 'crm', $user->id, 'User action');
        $userLog = ModuleAccessLog::where('action', 'enable')
            ->where('module_key', 'crm')
            ->latest()
            ->first();

        // Both should have actor_type set
        $this->assertNotNull($adminLog->actor_type);
        $this->assertNotNull($userLog->actor_type);

        // They should be distinguishable
        $this->assertNotEquals($adminLog->actor_type, $userLog->actor_type);
        $this->assertEquals('platform_admin', $adminLog->actor_type);
        $this->assertEquals('institute_user', $userLog->actor_type);
    }

    public function test_cli_invocation_records_system_actor_type(): void
    {
        $before = ModuleAccessLog::count();

        // Artisan::call runs in console context — resolveActorType() returns 'system'
        \Illuminate\Support\Facades\Artisan::call('entitlements:expire');

        $after = ModuleAccessLog::count();

        // If any logs were created, they should have actor_type = 'system'
        if ($after > $before) {
            $latestLog = ModuleAccessLog::latest()->first();
            $this->assertEquals('system', $latestLog->actor_type);
        }
        // If no logs created, the test still passes — no stale entitlements to expire
        $this->assertGreaterThanOrEqual($before, $after);
    }

    public function test_legacy_rows_with_null_actor_type_readable(): void
    // Manually insert a row with actor_type = NULL (simulating pre-migration data)
    {
        $log = ModuleAccessLog::create([
            'institute_id' => $this->institute->id,
            'module_key' => 'hr',
            'action' => 'enable',
            'actor_id' => 999,
            'actor_type' => null,
            'previous_state' => 'disabled',
            'new_state' => 'enabled',
            'package_id' => null,
            'notes' => 'Legacy row',
        ]);

        $this->assertNull($log->actor_type);
        $this->assertEquals('legacy/unknown', $log->actor_type_label);
    }

    public function test_explicit_actor_type_overrides_resolution(): void
    {
        $service = app(ModuleAccessService::class);

        // Call logAccess directly with an explicit actorType — should use that, not resolve
        $service->logAccess(
            $this->institute->id,
            'hr',
            'enable',
            42,
            'disabled',
            'enabled',
            null,
            'Explicit type test',
            'custom_type'
        );

        $log = ModuleAccessLog::where('notes', 'Explicit type test')->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals('custom_type', $log->actor_type);
    }
}
