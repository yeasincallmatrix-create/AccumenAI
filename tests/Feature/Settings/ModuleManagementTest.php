<?php

namespace Tests\Feature\Settings;

use App\Models\Institute;
use App\Models\InstituteModuleOverride;
use App\Models\ModuleRegistry;
use App\Models\PackageModule;
use App\Models\Role;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\ModuleAccessService;
use App\Services\UserAccountService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ModuleManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected function tenantOwner(string $email): array
    {
        $unique = strtolower(preg_replace('/[^a-z]/i', '', uniqid()));
        $email = str_replace('@', "+{$unique}@", $email);
        $institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
        $owner = (new UserAccountService)->registerOwner([
            'name' => 'Module Owner',
            'first_name' => 'Module',
            'last_name' => 'Owner',
            'email' => $email,
            'password_hash' => bcrypt('password'),
            'status' => 'active',
        ]);
        $roleId = Role::where('slug', 'institute-owner')->firstOrFail()->id;
        (new MembershipService)->assign($owner, $institute->id, $roleId);

        return [$institute, $owner];
    }

    protected function asUser(User $user, int $workspaceId): static
    {
        return $this->withSession([\App\Support\Workspace::SESSION_KEY => $workspaceId])
            ->actingAs($user, 'web');
    }

    protected function ensurePackageModules(Institute $institute, array $moduleKeys): void
    {
        if (! $institute->package_id) {
            $free = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['free'])->first();
            if ($free) {
                $institute->update(['package_id' => $free->id]);
            } else {
                $pkg = SubscriptionPackage::create(['name' => 'Test', 'slug' => 'test-pkg-' . uniqid(), 'status' => 'active', 'price_monthly' => 0, 'price_yearly' => 0]);
                $institute->update(['package_id' => $pkg->id]);
            }
        }
        foreach ($moduleKeys as $key) {
            PackageModule::updateOrCreate(
                ['package_id' => $institute->package_id, 'module_key' => $key],
                ['enabled' => true]
            );
        }
        app(ModuleAccessService::class)->flushCache($institute->id);
    }

    public function test_index_requires_auth(): void
    {
        $this->get(route('settings.modules'))->assertRedirect();
    }

    public function test_index_requires_view_permission(): void
    {
        [$institute, $owner] = $this->tenantOwner('module-noperm@example.test');
        $this->asUser($owner, $institute->id)
            ->get(route('settings.modules'))
            ->assertOk();
    }

    public function test_index_lists_all_modules(): void
    {
        [$institute, $owner] = $this->tenantOwner('module-list@example.test');
        $response = $this->asUser($owner, $institute->id)
            ->get(route('settings.modules'));
        $response->assertOk();
        $response->assertSee('Module Management');
        $response->assertSee('CRM');
    }

    public function test_index_shows_medical_sub_modules_grouped(): void
    {
        [$institute, $owner] = $this->tenantOwner('module-medical@example.test');
        $response = $this->asUser($owner, $institute->id)
            ->get(route('settings.modules'));
        $response->assertOk();
        $response->assertSee('Medical');
    }

    public function test_toggle_requires_auth(): void
    {
        $this->postJson(route('settings.modules.toggle'), [
            'module_key' => 'crm',
            'enabled' => 1,
        ])->assertUnauthorized();
    }

    public function test_toggle_enable_module_succeeds(): void
    {
        [$institute, $owner] = $this->tenantOwner('module-toggle-on@example.test');
        $this->ensurePackageModules($institute, ['notifications']);

        $this->asUser($owner, $institute->id)
            ->postJson(route('settings.modules.toggle'), [
                'module_key' => 'notifications',
                'enabled' => true,
            ])
            ->assertOk()
            ->assertJson(['status' => 'ok', 'module_key' => 'notifications', 'enabled' => true]);
    }

    public function test_toggle_disable_module_succeeds(): void
    {
        [$institute, $owner] = $this->tenantOwner('module-toggle-off@example.test');
        $this->ensurePackageModules($institute, ['notifications']);

        $this->asUser($owner, $institute->id)
            ->postJson(route('settings.modules.toggle'), [
                'module_key' => 'notifications',
                'enabled' => false,
            ])
            ->assertOk()
            ->assertJson(['status' => 'ok', 'module_key' => 'notifications', 'enabled' => false]);
    }

    public function test_toggle_persists_to_institute_module_overrides(): void
    {
        [$institute, $owner] = $this->tenantOwner('module-persist@example.test');
        $this->ensurePackageModules($institute, ['notifications']);

        $this->asUser($owner, $institute->id)
            ->postJson(route('settings.modules.toggle'), [
                'module_key' => 'notifications',
                'enabled' => false,
            ])
            ->assertOk();

        $override = \DB::table('institute_module_overrides')
            ->where('institute_id', $institute->id)
            ->where('module_key', 'notifications')
            ->first();

        $this->assertNotNull($override);
        $this->assertFalse((bool) $override->enabled);
    }

    public function test_toggle_parent_off_cascades_to_children(): void
    {
        [$institute, $owner] = $this->tenantOwner('module-cascade@example.test');
        $this->ensurePackageModules($institute, ['medical', 'medical.opd', 'medical.ipd', 'medical.pharmacy']);

        $service = app(ModuleAccessService::class);
        InstituteModuleOverride::updateOrCreate(
            ['institute_id' => $institute->id, 'module_key' => 'medical'],
            ['enabled' => true, 'overridden_by' => $owner->id]
        );
        InstituteModuleOverride::updateOrCreate(
            ['institute_id' => $institute->id, 'module_key' => 'medical.opd'],
            ['enabled' => true, 'overridden_by' => $owner->id]
        );
        $service->flushCache($institute->id);

        $this->asUser($owner, $institute->id)
            ->postJson(route('settings.modules.toggle'), [
                'module_key' => 'medical',
                'enabled' => false,
            ])
            ->assertOk();

        $overrides = \DB::table('institute_module_overrides')
            ->where('institute_id', $institute->id)
            ->whereIn('module_key', ['medical.opd', 'medical.ipd', 'medical.pharmacy'])
            ->get();

        foreach ($overrides as $o) {
            $this->assertFalse((bool) $o->enabled, "Child {$o->module_key} should be disabled");
        }
    }

    public function test_toggle_child_on_blocked_when_parent_off(): void
    {
        [$institute, $owner] = $this->tenantOwner('module-parent-block@example.test');
        $this->ensurePackageModules($institute, ['medical', 'medical.opd']);

        $service = app(ModuleAccessService::class);
        InstituteModuleOverride::updateOrCreate(
            ['institute_id' => $institute->id, 'module_key' => 'medical'],
            ['enabled' => true, 'overridden_by' => $owner->id]
        );
        InstituteModuleOverride::updateOrCreate(
            ['institute_id' => $institute->id, 'module_key' => 'medical.opd'],
            ['enabled' => true, 'overridden_by' => $owner->id]
        );
        $service->flushCache($institute->id);

        $this->asUser($owner, $institute->id)
            ->postJson(route('settings.modules.toggle'), [
                'module_key' => 'medical',
                'enabled' => false,
            ])
            ->assertOk();

        $this->asUser($owner, $institute->id)
            ->postJson(route('settings.modules.toggle'), [
                'module_key' => 'medical.opd',
                'enabled' => true,
            ])
            ->assertStatus(422);
    }

    public function test_toggle_invalid_module_rejected(): void
    {
        [$institute, $owner] = $this->tenantOwner('module-invalid@example.test');

        $this->asUser($owner, $institute->id)
            ->postJson(route('settings.modules.toggle'), [
                'module_key' => 'nonexistent_module',
                'enabled' => true,
            ])
            ->assertStatus(422);
    }

    public function test_toggle_module_not_in_package_rejected(): void
    {
        [$institute, $owner] = $this->tenantOwner('module-nopkg@example.test');
        $this->ensurePackageModules($institute, ['notifications']);

        $this->asUser($owner, $institute->id)
            ->postJson(route('settings.modules.toggle'), [
                'module_key' => 'crm',
                'enabled' => true,
            ])
            ->assertStatus(403);
    }

    public function test_institute_a_toggle_does_not_affect_institute_b(): void
    {
        [$instituteA, $ownerA] = $this->tenantOwner('module-iso-a@example.test');
        $this->ensurePackageModules($instituteA, ['notifications']);

        $instituteB = Institute::create([
            'name' => 'Isolation Test Institute B',
            'slug' => 'isolation-test-b-' . uniqid(),
            'industry' => 'healthcare',
            'status' => 'active',
            'package_id' => $instituteA->package_id,
        ]);
        $ownerB = (new UserAccountService)->registerOwner([
            'name' => 'Isolation B Owner',
            'first_name' => 'Isolation',
            'last_name' => 'OwnerB',
            'email' => 'module-iso-b-owner@example.test',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
        ]);
        $roleId = Role::where('slug', 'institute-owner')->firstOrFail()->id;
        (new MembershipService)->assign($ownerB, $instituteB->id, $roleId);

        $countBefore = \DB::table('institute_module_overrides')
            ->where('institute_id', $instituteB->id)
            ->count();

        $this->asUser($ownerA, $instituteA->id)
            ->postJson(route('settings.modules.toggle'), [
                'module_key' => 'notifications',
                'enabled' => false,
            ])
            ->assertOk();

        $countAfter = \DB::table('institute_module_overrides')
            ->where('institute_id', $instituteB->id)
            ->count();
        $this->assertEquals($countBefore, $countAfter, 'Institute B should not have new overrides from Institute A toggle');
    }
}
