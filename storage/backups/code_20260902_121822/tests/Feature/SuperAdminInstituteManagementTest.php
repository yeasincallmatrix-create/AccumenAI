<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\PlatformAdmin;
use App\Models\PlatformAuditLog;
use App\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SuperAdminInstituteManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'SuperSecret123!';
    protected PlatformAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'super-admin-'.uniqid().'@example.test',
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    protected function makeInstitute(string $status = 'pending'): Institute
    {
        return Institute::create([
            'name' => 'E20 Test Institute '.uniqid(),
            'slug' => 'e20-'.uniqid(),
            'status' => $status,
        ]);
    }

    protected function makeInstituteUser(Institute $institute): InstituteUser
    {
        $role = Role::where('slug', 'institute-owner')->first();
        if (! $role) {
            $role = Role::create(['name' => 'Institute Owner', 'slug' => 'institute-owner']);
        }
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'role_id' => $role->id,
            'first_name' => 'Owner',
            'last_name' => substr(uniqid(), -6),
            'email' => 'owner-'.uniqid().'@example.test',
            'phone' => '+8801'.str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT),
            'password_hash' => bcrypt('password'),
            'status' => 'active',
        ]);
    }

    // — Approval —

    public function test_super_admin_can_view_pending_institutes(): void
    {
        $pending = $this->makeInstitute('pending');
        $this->actingAs($this->admin, 'platform_admin');
        $this->get(route('admin.institutes.index', ['status' => 'pending']))
            ->assertOk()
            ->assertSee($pending->name);
    }

    public function test_super_admin_can_approve_pending_institute(): void
    {
        $inst = $this->makeInstitute('pending');
        $this->actingAs($this->admin, 'platform_admin');

        $this->post(route('admin.institutes.action', $inst), ['action' => 'approve'])
            ->assertRedirect(route('admin.institutes.index'));

        $this->assertSame('active', $inst->refresh()->status);
        $this->assertNotNull($inst->refresh()->onboarded_at);
    }

    public function test_super_admin_approve_via_json_returns_json(): void
    {
        $inst = $this->makeInstitute('pending');
        $this->actingAs($this->admin, 'platform_admin');

        $this->postJson(route('admin.institutes.action', $inst), ['action' => 'approve'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'active');

        $this->assertSame('active', $inst->refresh()->status);
    }

    public function test_approved_status_persists(): void
    {
        $inst = $this->makeInstitute('pending');
        $this->actingAs($this->admin, 'platform_admin');
        $this->post(route('admin.institutes.action', $inst), ['action' => 'approve']);
        $this->assertSame('active', $inst->refresh()->status);
        // Reload from DB with trashed excluded still shows
        $fresh = Institute::find($inst->id);
        $this->assertNotNull($fresh);
        $this->assertSame('active', $fresh->status);
    }

    public function test_duplicate_approval_is_safe(): void
    {
        $inst = $this->makeInstitute('active');
        $this->actingAs($this->admin, 'platform_admin');

        $this->postJson(route('admin.institutes.action', $inst), ['action' => 'approve'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('active', $inst->refresh()->status);
    }

    public function test_approval_creates_audit_log(): void
    {
        PlatformAuditLog::query()->delete();
        $inst = $this->makeInstitute('pending');
        $this->actingAs($this->admin, 'platform_admin');
        $this->post(route('admin.institutes.action', $inst), ['action' => 'approve']);
        $this->assertDatabaseHas('platform_audit_logs', [
            'section' => 'institutes',
            'setting_key' => 'institute.'.$inst->id,
            'action' => 'approve',
        ]);
    }

    public function test_guest_cannot_approve(): void
    {
        $inst = $this->makeInstitute('pending');
        $this->post(route('admin.institutes.action', $inst), ['action' => 'approve'])
            ->assertRedirect();
        $this->assertSame('pending', $inst->refresh()->status);
    }

    public function test_institute_user_cannot_approve(): void
    {
        $inst = $this->makeInstitute('pending');
        $otherInst = $this->makeInstitute('active');
        $user = $this->makeInstituteUser($otherInst);
        $this->actingAs($user, 'institute_user');
        $resp = $this->post(route('admin.institutes.action', $inst), ['action' => 'approve']);
        // platform_admin guard unauthenticated -> redirect to admin login (302) or 403 depending on config
        $this->assertTrue(in_array($resp->status(), [302, 401, 403], true));
        $this->assertSame('pending', $inst->refresh()->status);
    }

    // — Delete —

    public function test_super_admin_can_delete_institute(): void
    {
        $inst = $this->makeInstitute('active');
        $this->actingAs($this->admin, 'platform_admin');

        $this->post(route('admin.institutes.action', $inst), ['action' => 'delete', 'password' => $this->password])
            ->assertRedirect(route('admin.institutes.index'));

        $this->assertNotNull($inst->refresh()->deleted_at);
        $this->assertSame('cancelled', $inst->refresh()->status);
    }

    public function test_delete_uses_soft_delete(): void
    {
        $inst = $this->makeInstitute('active');
        $this->actingAs($this->admin, 'platform_admin');
        $this->postJson(route('admin.institutes.action', $inst), ['action' => 'delete', 'password' => $this->password])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('institutes', ['id' => $inst->id]);
        $this->assertNotNull(Institute::withTrashed()->find($inst->id)->deleted_at);
    }

    public function test_deleted_institute_disappears_from_active_list(): void
    {
        $inst = $this->makeInstitute('active');
        $this->actingAs($this->admin, 'platform_admin');
        $this->post(route('admin.institutes.action', $inst), ['action' => 'delete', 'password' => $this->password]);

        $this->get(route('admin.institutes.index'))
            ->assertOk()
            ->assertDontSee($inst->name);
    }

    public function test_deleted_institute_appears_in_recycle_bin(): void
    {
        $inst = $this->makeInstitute('active');
        $this->actingAs($this->admin, 'platform_admin');
        $this->post(route('admin.institutes.action', $inst), ['action' => 'delete', 'password' => $this->password]);

        $this->get(route('admin.institutes.bin'))
            ->assertOk()
            ->assertSee($inst->name);
    }

    public function test_restore_works(): void
    {
        $inst = $this->makeInstitute('active');
        $this->actingAs($this->admin, 'platform_admin');
        $this->post(route('admin.institutes.action', $inst), ['action' => 'delete', 'password' => $this->password]);
        $this->assertNotNull($inst->refresh()->deleted_at);

        $this->post(route('admin.institutes.restore', $inst))
            ->assertRedirect(route('admin.institutes.bin'));

        $this->assertNull($inst->refresh()->deleted_at);
        $this->assertSame('active', $inst->refresh()->status);
        $this->get(route('admin.institutes.index'))->assertSee($inst->name);
    }

    public function test_restore_creates_audit_log(): void
    {
        PlatformAuditLog::query()->delete();
        $inst = $this->makeInstitute('active');
        $inst->delete();
        $this->actingAs($this->admin, 'platform_admin');
        $this->post(route('admin.institutes.restore', $inst));
        $this->assertDatabaseHas('platform_audit_logs', [
            'section' => 'institutes',
            'action' => 'restored',
        ]);
    }

    public function test_delete_wrong_password_fails(): void
    {
        $inst = $this->makeInstitute('active');
        $this->actingAs($this->admin, 'platform_admin');
        $this->post(route('admin.institutes.action', $inst), ['action' => 'delete', 'password' => 'wrong'])
            ->assertSessionHasErrors('password');
        $this->assertNull($inst->refresh()->deleted_at);
    }

    public function test_delete_json_wrong_password_422(): void
    {
        $inst = $this->makeInstitute('active');
        $this->actingAs($this->admin, 'platform_admin');
        $this->postJson(route('admin.institutes.action', $inst), ['action' => 'delete', 'password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertNull($inst->refresh()->deleted_at);
    }

    public function test_unauthorized_user_cannot_delete(): void
    {
        $inst = $this->makeInstitute('active');
        $this->post(route('admin.institutes.action', $inst), ['action' => 'delete', 'password' => $this->password])
            ->assertRedirect();
        $this->assertNull($inst->refresh()->deleted_at);
    }

    public function test_institute_user_cannot_delete(): void
    {
        $inst = $this->makeInstitute('active');
        $user = $this->makeInstituteUser($inst);
        $this->actingAs($user, 'institute_user');
        $resp = $this->post(route('admin.institutes.action', $inst), ['action' => 'delete', 'password' => 'password']);
        $this->assertTrue(in_array($resp->status(), [302, 401, 403], true));
        $this->assertNull($inst->refresh()->deleted_at);
    }

    public function test_tenant_isolation_super_admin_sees_all(): void
    {
        $a = $this->makeInstitute('active');
        $b = $this->makeInstitute('active');
        $this->actingAs($this->admin, 'platform_admin');
        $this->get(route('admin.institutes.index'))->assertSee($a->name)->assertSee($b->name);
    }

    public function test_institute_user_cannot_access_admin_list(): void
    {
        $inst = $this->makeInstitute('active');
        $user = $this->makeInstituteUser($inst);
        $this->actingAs($user, 'institute_user');
        $resp = $this->get(route('admin.institutes.index'));
        $this->assertTrue(in_array($resp->status(), [302, 401, 403], true));
    }

    public function test_unverified_admin_is_blocked(): void
    {
        $unverified = PlatformAdmin::firstOrReuseForTests([
            'email' => 'unverified-'.uniqid().'@example.test',
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
            'email_verified_at' => null,
        ]);
        $inst = $this->makeInstitute('pending');
        $this->actingAs($unverified, 'platform_admin');
        $this->post(route('admin.institutes.action', $inst), ['action' => 'approve'])
            ->assertRedirect(route('verification.notice'));
        $this->assertSame('pending', $inst->refresh()->status);
    }

    public function test_force_delete_requires_recycle_bin(): void
    {
        $inst = $this->makeInstitute('active');
        $this->actingAs($this->admin, 'platform_admin');
        $this->delete(route('admin.institutes.force-delete', $inst), ['password' => $this->password])
            ->assertSessionHasErrors('institute');
        $this->assertDatabaseHas('institutes', ['id' => $inst->id]);
    }

    public function test_delete_is_idempotent_second_delete_fails(): void
    {
        $inst = $this->makeInstitute('active');
        $this->actingAs($this->admin, 'platform_admin');
        $this->post(route('admin.institutes.action', $inst), ['action' => 'delete', 'password' => $this->password])
            ->assertRedirect();
        // Second delete targets soft-deleted institute -> route model binding excludes trashed => 404
        $this->postJson(route('admin.institutes.action', $inst), ['action' => 'delete', 'password' => $this->password])
            ->assertStatus(404);
    }
}
