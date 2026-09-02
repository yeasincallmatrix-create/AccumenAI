<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Membership;
use App\Models\PlatformAdmin;
use App\Models\PlatformAuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * PHASE E24 — BUSINESS PERMANENT DELETE vs OWNER/USER ACCOUNT SAFETY
 *
 * Verifies that deleting a business never deletes the global User account
 * and never affects sibling businesses.
 */
class E24BusinessOwnerDeleteSafetyTest extends TestCase
{
    use DatabaseTransactions;

    protected string $adminPassword = 'SuperSecret123!';
    protected PlatformAdmin $admin;
    protected Role $ownerRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'e24-admin-'.uniqid().'@example.test',
            'password_hash' => bcrypt($this->adminPassword),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->ownerRole = Role::where('slug', 'institute-owner')->first();
        if (! $this->ownerRole) {
            $this->ownerRole = Role::create(['name' => 'Institute Owner', 'slug' => 'institute-owner', 'is_system' => true]);
        }
    }

    private function makeInstitute(string $suffix = ''): Institute
    {
        return Institute::create([
            'name' => 'E24 Institute '.$suffix.' '.uniqid(),
            'slug' => 'e24-'.uniqid().($suffix ? '-'.$suffix : ''),
            'status' => 'active',
        ]);
    }

    private function makeOwnerUser(): User
    {
        return User::create([
            'name' => 'Owner '.uniqid(),
            'email' => 'e24-owner-'.uniqid().'@example.test',
            'phone' => '+8801'.str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT),
            'password_hash' => bcrypt('password'),
            'account_type' => 'owner',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function attachOwner(User $user, Institute $institute): Membership
    {
        return Membership::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'institution_id' => $institute->id,
            'role_id' => $this->ownerRole->id,
            'status' => 'active',
        ]);
    }

    // TEST 1: One owner → one business → delete business. Business deleted, Owner remains.
    public function test_one_owner_one_business_delete_business_owner_remains(): void
    {
        $user = $this->makeOwnerUser();
        $a = $this->makeInstitute('T1-A');
        $this->attachOwner($user, $a);

        $this->actingAs($this->admin, 'platform_admin');
        $this->post(route('admin.institutes.action', $a), ['action' => 'delete', 'password' => $this->adminPassword])
            ->assertRedirect();

        $this->assertSoftDeleted('institutes', ['id' => $a->id]);
        // Membership should be soft-deleted
        $this->assertSoftDeleted('institution_user', ['user_id' => $user->id, 'institution_id' => $a->id]);
        // User must remain
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertNull(User::find($user->id)->deleted_at);

        // Permanent delete
        $aTrashed = Institute::withTrashed()->find($a->id);
        $this->delete(route('admin.institutes.force-delete', $aTrashed), ['password' => $this->adminPassword])
            ->assertRedirect();
        $this->assertDatabaseMissing('institutes', ['id' => $a->id]);
        // User still exists
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertNotNull(User::find($user->id));
    }

    // TEST 2: One owner → two businesses → delete Business A. B remains, Owner remains.
    public function test_one_owner_two_businesses_delete_a_b_remains_owner_remains(): void
    {
        $user = $this->makeOwnerUser();
        $a = $this->makeInstitute('T2-A');
        $b = $this->makeInstitute('T2-B');
        $this->attachOwner($user, $a);
        $this->attachOwner($user, $b);

        $this->actingAs($this->admin, 'platform_admin');
        $this->post(route('admin.institutes.action', $a), ['action' => 'delete', 'password' => $this->adminPassword])
            ->assertRedirect();

        $this->assertSoftDeleted('institutes', ['id' => $a->id]);
        $this->assertDatabaseHas('institutes', ['id' => $b->id, 'deleted_at' => null]);
        $this->assertSoftDeleted('institution_user', ['user_id' => $user->id, 'institution_id' => $a->id]);
        $this->assertDatabaseHas('institution_user', ['user_id' => $user->id, 'institution_id' => $b->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);

        // Force delete A
        $aTrashed = Institute::withTrashed()->find($a->id);
        $this->delete(route('admin.institutes.force-delete', $aTrashed), ['password' => $this->adminPassword])
            ->assertRedirect();
        $this->assertDatabaseMissing('institutes', ['id' => $a->id]);
        $this->assertDatabaseHas('institutes', ['id' => $b->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        // Membership for A should be gone (cascade hard delete), B remains
        $this->assertDatabaseMissing('institution_user', ['user_id' => $user->id, 'institution_id' => $a->id]);
        $this->assertDatabaseHas('institution_user', ['user_id' => $user->id, 'institution_id' => $b->id]);
    }

    // TEST 3: One owner → three businesses → delete Business B. A and C remain.
    public function test_one_owner_three_businesses_delete_b_others_remain(): void
    {
        $user = $this->makeOwnerUser();
        $a = $this->makeInstitute('T3-A');
        $b = $this->makeInstitute('T3-B');
        $c = $this->makeInstitute('T3-C');
        $this->attachOwner($user, $a);
        $this->attachOwner($user, $b);
        $this->attachOwner($user, $c);

        $this->actingAs($this->admin, 'platform_admin');
        $this->post(route('admin.institutes.action', $b), ['action' => 'delete', 'password' => $this->adminPassword])
            ->assertRedirect();

        $this->assertSoftDeleted('institutes', ['id' => $b->id]);
        $this->assertDatabaseHas('institutes', ['id' => $a->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('institutes', ['id' => $c->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);

        $bTrashed = Institute::withTrashed()->find($b->id);
        $this->delete(route('admin.institutes.force-delete', $bTrashed), ['password' => $this->adminPassword])
            ->assertRedirect();
        $this->assertDatabaseHas('institutes', ['id' => $a->id]);
        $this->assertDatabaseHas('institutes', ['id' => $c->id]);
        $this->assertDatabaseMissing('institutes', ['id' => $b->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    // TEST 4: Owner has Business A in recycle bin + Business B active. Permanently delete A. B remains.
    public function test_recycle_bin_active_business_untouched_on_permanent_delete(): void
    {
        $user = $this->makeOwnerUser();
        $a = $this->makeInstitute('T4-A');
        $b = $this->makeInstitute('T4-B');
        $this->attachOwner($user, $a);
        $this->attachOwner($user, $b);

        $this->actingAs($this->admin, 'platform_admin');
        $this->post(route('admin.institutes.action', $a), ['action' => 'delete', 'password' => $this->adminPassword])
            ->assertRedirect();

        // B still active
        $this->assertDatabaseHas('institutes', ['id' => $b->id, 'deleted_at' => null]);

        $aTrashed = Institute::withTrashed()->find($a->id);
        $this->delete(route('admin.institutes.force-delete', $aTrashed), ['password' => $this->adminPassword])
            ->assertRedirect();

        $this->assertDatabaseMissing('institutes', ['id' => $a->id]);
        $this->assertDatabaseHas('institutes', ['id' => $b->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('institution_user', ['user_id' => $user->id, 'institution_id' => $b->id]);
    }

    // TEST 5: Delete Business A → restore Business A. Owner unchanged, relationships intact.
    public function test_soft_delete_restore_preserves_owner_and_relationships(): void
    {
        $user = $this->makeOwnerUser();
        $a = $this->makeInstitute('T5-A');
        $mem = $this->attachOwner($user, $a);

        $this->actingAs($this->admin, 'platform_admin');
        $this->post(route('admin.institutes.action', $a), ['action' => 'delete', 'password' => $this->adminPassword])
            ->assertRedirect();
        $this->assertSoftDeleted('institutes', ['id' => $a->id]);
        $this->assertSoftDeleted('institution_user', ['id' => $mem->id]);

        $aTrashed = Institute::withTrashed()->find($a->id);
        $this->post(route('admin.institutes.restore', $aTrashed))
            ->assertRedirect();

        $this->assertDatabaseHas('institutes', ['id' => $a->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('institution_user', ['id' => $mem->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        // Relationship intact
        $freshUser = User::find($user->id);
        $this->assertTrue($freshUser->memberships()->where('institution_id', $a->id)->exists());
    }

    // TEST 6: Attempt automatic owner deletion during business force-delete. Owner NOT deleted.
    public function test_force_delete_never_auto_deletes_owner(): void
    {
        $user = $this->makeOwnerUser();
        $a = $this->makeInstitute('T6-A');
        $this->attachOwner($user, $a);

        $this->actingAs($this->admin, 'platform_admin');
        $this->post(route('admin.institutes.action', $a), ['action' => 'delete', 'password' => $this->adminPassword]);
        $aTrashed = Institute::withTrashed()->find($a->id);
        $this->delete(route('admin.institutes.force-delete', $aTrashed), ['password' => $this->adminPassword]);

        // User must still exist even though it was the only business
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertNull(User::withTrashed()->find($user->id)->deleted_at);
        // No membership remains for deleted institute, but user survives orphaned
        $this->assertEquals(0, Membership::withTrashed()->where('user_id', $user->id)->where('institution_id', $a->id)->count());
    }

    // TEST 7: Two different owners with separate businesses. Deleting one never affects the other.
    public function test_two_owners_isolation(): void
    {
        $owner1 = $this->makeOwnerUser();
        $owner2 = $this->makeOwnerUser();
        $inst1 = $this->makeInstitute('T7-O1');
        $inst2 = $this->makeInstitute('T7-O2');
        $this->attachOwner($owner1, $inst1);
        $this->attachOwner($owner2, $inst2);

        $this->actingAs($this->admin, 'platform_admin');
        $this->post(route('admin.institutes.action', $inst1), ['action' => 'delete', 'password' => $this->adminPassword])
            ->assertRedirect();
        $inst1Trashed = Institute::withTrashed()->find($inst1->id);
        $this->delete(route('admin.institutes.force-delete', $inst1Trashed), ['password' => $this->adminPassword])
            ->assertRedirect();

        $this->assertDatabaseMissing('institutes', ['id' => $inst1->id]);
        $this->assertDatabaseHas('institutes', ['id' => $inst2->id]);
        $this->assertDatabaseHas('users', ['id' => $owner1->id]);
        $this->assertDatabaseHas('users', ['id' => $owner2->id]);
        $this->assertDatabaseHas('institution_user', ['user_id' => $owner2->id, 'institution_id' => $inst2->id]);
    }

    // TEST 8: Unauthorized Institute User attempts business deletion. Blocked.
    public function test_institute_user_cannot_delete_business(): void
    {
        $user = $this->makeOwnerUser();
        $inst = $this->makeInstitute('T8-A');
        $this->attachOwner($user, $inst);

        // Create a legacy institute_user for auth
        $role = Role::where('slug', 'institute-owner')->first();
        $iu = InstituteUser::create([
            'institute_id' => $inst->id,
            'role_id' => $role->id,
            'first_name' => 'Hacker',
            'last_name' => 'User',
            'email' => 'hacker-'.uniqid().'@example.test',
            'phone' => '+8801'.str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT),
            'password_hash' => bcrypt('password'),
            'status' => 'active',
        ]);

        $this->actingAs($iu, 'institute_user');
        $resp = $this->post(route('admin.institutes.action', $inst), ['action' => 'delete', 'password' => 'password']);
        $this->assertTrue(in_array($resp->status(), [302, 401, 403], true));
        $this->assertDatabaseHas('institutes', ['id' => $inst->id, 'deleted_at' => null]);
    }

    // TEST 9: Super Admin deletes a business belonging to an owner who has multiple businesses. Only selected deleted.
    public function test_super_admin_multi_business_only_selected_deleted(): void
    {
        $user = $this->makeOwnerUser();
        $a = $this->makeInstitute('T9-A');
        $b = $this->makeInstitute('T9-B');
        $c = $this->makeInstitute('T9-C');
        $this->attachOwner($user, $a);
        $this->attachOwner($user, $b);
        $this->attachOwner($user, $c);

        $this->actingAs($this->admin, 'platform_admin');
        $this->post(route('admin.institutes.action', $b), ['action' => 'delete', 'password' => $this->adminPassword])
            ->assertRedirect();

        $this->assertSoftDeleted('institutes', ['id' => $b->id]);
        $this->assertDatabaseHas('institutes', ['id' => $a->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('institutes', ['id' => $c->id, 'deleted_at' => null]);

        // Verify membership isolation
        $this->assertSoftDeleted('institution_user', ['user_id' => $user->id, 'institution_id' => $b->id]);
        $this->assertDatabaseHas('institution_user', ['user_id' => $user->id, 'institution_id' => $a->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('institution_user', ['user_id' => $user->id, 'institution_id' => $c->id, 'deleted_at' => null]);
    }

    // TEST 10: Audit logs contain business id/name/action/status but no secrets.
    public function test_audit_logs_safe_no_secrets(): void
    {
        PlatformAuditLog::query()->delete();
        $user = $this->makeOwnerUser();
        $inst = $this->makeInstitute('T10-A');
        $this->attachOwner($user, $inst);

        $this->actingAs($this->admin, 'platform_admin');
        $this->post(route('admin.institutes.action', $inst), ['action' => 'delete', 'password' => $this->adminPassword])
            ->assertRedirect();

        $log = PlatformAuditLog::where('section', 'institutes')->where('action', 'deleted')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertEquals($inst->id, $log->meta['institute_id'] ?? null);
        $this->assertEquals($inst->name, $log->meta['institute_name'] ?? null);

        $metaJson = json_encode($log->meta);
        $this->assertStringNotContainsString('password', strtolower($metaJson));
        $this->assertStringNotContainsString('otp', strtolower($metaJson));
        $this->assertStringNotContainsString('token', strtolower($metaJson));
        $this->assertStringNotContainsString('secret', strtolower($metaJson));
        $this->assertStringNotContainsString('smtp', strtolower($metaJson));

        // Restore audit
        $instTrashed = Institute::withTrashed()->find($inst->id);
        $this->post(route('admin.institutes.restore', $instTrashed))->assertRedirect();
        $restoreLog = PlatformAuditLog::where('action', 'restored')->latest('id')->first();
        $this->assertNotNull($restoreLog);
        $this->assertEquals($inst->id, $restoreLog->meta['institute_id'] ?? null);

        // Force delete audit
        $this->post(route('admin.institutes.action', $instTrashed->refresh(), ['action' => 'delete', 'password' => $this->adminPassword]));
        // Need to re-soft-delete after restore then force delete
        $inst2 = $this->makeInstitute('T10-B');
        $this->attachOwner($user, $inst2);
        $this->post(route('admin.institutes.action', $inst2), ['action' => 'delete', 'password' => $this->adminPassword]);
        $inst2Trashed = Institute::withTrashed()->find($inst2->id);
        $this->delete(route('admin.institutes.force-delete', $inst2Trashed), ['password' => $this->adminPassword]);
        $forceLog = PlatformAuditLog::where('action', 'force_deleted')->latest('id')->first();
        $this->assertNotNull($forceLog);
        $forceMeta = json_encode($forceLog->meta);
        $this->assertStringNotContainsString('password', strtolower($forceMeta));
    }

    // Additional: batch actions isolation
    public function test_batch_delete_isolation(): void
    {
        $user = $this->makeOwnerUser();
        $a = $this->makeInstitute('BATCH-A');
        $b = $this->makeInstitute('BATCH-B');
        $c = $this->makeInstitute('BATCH-C');
        $this->attachOwner($user, $a);
        $this->attachOwner($user, $b);
        // c is owned by different user
        $otherUser = $this->makeOwnerUser();
        $this->attachOwner($otherUser, $c);

        $this->actingAs($this->admin, 'platform_admin');
        // Batch delete a and b (at least 2)
        $this->postJson(route('admin.institutes.batch-action'), [
            'ids' => [$a->id, $b->id],
            'action' => 'delete',
            'password' => $this->adminPassword,
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertSoftDeleted('institutes', ['id' => $a->id]);
        $this->assertSoftDeleted('institutes', ['id' => $b->id]);
        $this->assertDatabaseHas('institutes', ['id' => $c->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('users', ['id' => $otherUser->id]);
    }
}
