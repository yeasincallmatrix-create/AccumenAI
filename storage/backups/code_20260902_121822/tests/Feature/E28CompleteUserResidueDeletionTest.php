<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Membership;
use App\Models\PlatformAdmin;
use App\Models\PlatformAuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * E28 — Complete Global User Account Permanent Deletion Residue Audit & Fix
 *
 * Forensic audit and verification that permanent deletion of a GLOBAL USER ACCOUNT
 * removes all deletable account-owned residue, while never silently deleting
 * businesses or other users.
 */
class E28CompleteUserResidueDeletionTest extends TestCase
{
    use DatabaseTransactions;

    protected string $adminPass = 'SuperSecret123!';
    protected PlatformAdmin $admin;
    protected Role $ownerRole;
    protected Role $staffRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'e28-admin-' . uniqid() . '@example.test',
            'password_hash' => bcrypt($this->adminPass),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->ownerRole = Role::where('slug', 'institute-owner')->first()
            ?? Role::create(['name' => 'Institute Owner', 'slug' => 'institute-owner', 'is_system' => true]);
        $this->staffRole = Role::where('slug', 'institute-admin')->first()
            ?? Role::create(['name' => 'Institute Admin', 'slug' => 'institute-admin', 'is_system' => true]);
    }

    private function makeInstitute(string $s = ''): Institute
    {
        return Institute::create([
            'name' => 'E28 Inst ' . $s . ' ' . uniqid(),
            'slug' => 'e28-' . uniqid() . ($s ? '-' . $s : ''),
            'status' => 'active',
        ]);
    }

    private function makeUser(string $type = 'owner'): User
    {
        $u = User::create([
            'name' => 'User ' . uniqid(),
            'email' => 'e28-' . uniqid() . '@example.test',
            'phone' => '+8801' . str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT),
            'password_hash' => bcrypt('password'),
            'account_type' => $type,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        // Set 2FA/2FA/security fields via DB since they are not in $fillable
        DB::table('users')->where('id', $u->id)->update([
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_recovery_codes' => json_encode(['code1', 'code2']),
            'two_factor_confirmed_at' => now(),
            'remember_token' => 'remember_me_token_' . uniqid(),
            'preferences' => json_encode(['theme' => 'dark', 'language' => 'bn']),
            'pending_email' => 'pending-' . uniqid() . '@test.com',
            'pending_email_token_hash' => 'token_hash_' . uniqid(),
            'pending_email_expires_at' => now()->addHours(24),
            'pending_phone' => '+8801999999999',
        ]);

        return $u->refresh();
    }

    private function attach(User $u, Institute $i, ?Role $r = null): Membership
    {
        return Membership::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $u->id,
            'institution_id' => $i->id,
            'role_id' => ($r ?? $this->ownerRole)->id,
            'status' => 'active',
        ]);
    }

    private function prepareForForceDelete(User $user, ?Institute $inst = null): void
    {
        $this->actingAs($this->admin, 'platform_admin');
        if ($inst) {
            $this->post(route('admin.institutes.action', $inst), ['action' => 'delete', 'password' => $this->adminPass]);
            $trashed = Institute::withTrashed()->find($inst->id);
            $this->delete(route('admin.institutes.force-delete', $trashed), ['password' => $this->adminPass]);
        }
        $this->delete(route('admin.users.destroy', $user), ['password' => $this->adminPass]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. USER-OWNED DEPENDENCIES REMOVED
    // ═══════════════════════════════════════════════════════════════════════

    public function test_user_owned_dependencies_removed_on_force_delete(): void
    {
        $u = $this->makeUser();
        $a = $this->makeInstitute('dep');
        $this->attach($u, $a);

        // Seed residue across all user-owned tables
        DB::table('sessions')->insert([
            'id' => uniqid('sess_'), 'user_id' => $u->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'test', 'payload' => 'x', 'last_activity' => time(),
        ]);
        DB::table('email_otps')->insert([
            'user_id' => $u->id, 'email' => $u->email, 'otp_hash' => 'hash',
            'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('phone_verification_otps')->insert([
            'user_id' => $u->id, 'phone' => '+8801000000000', 'otp_hash' => 'hash',
            'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('phone_2fa_otps')->insert([
            'user_id' => $u->id, 'phone' => '+8801000000000', 'otp_hash' => 'hash',
            'guard' => 'web', 'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('phone_password_reset_otps')->insert([
            'user_id' => $u->id, 'phone' => '+8801000000000', 'otp_hash' => 'hash',
            'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('identity_audit_logs')->insert([
            'user_id' => $u->id, 'event' => 'test_event', 'created_at' => now(),
        ]);
        DB::table('user_module_access')->insert([
            'institute_id' => $a->id, 'user_type' => 'App\\Models\\User',
            'user_id' => $u->id, 'module_key' => 'hr', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ai_logs')->insert([
            'user_type' => 'App\\Models\\User', 'user_id' => $u->id,
            'prompt' => 'test', 'feature' => 'assistant', 'status' => 'ok',
            'created_at' => now(),
        ]);
        DB::table('audit_logs')->insert([
            'user_type' => 'institute_user', 'user_id' => $u->id,
            'action' => 'test', 'module' => 'test', 'created_at' => now(),
        ]);
        DB::table('activity_logs')->insert([
            'user_type' => 'institute_user', 'user_id' => $u->id,
            'activity' => 'test', 'created_at' => now(),
        ]);
        DB::table('login_attempts')->insert([
            'email' => $u->email, 'user_type' => 'institute_user',
            'ip_address' => '127.0.0.1', 'is_success' => 1, 'attempted_at' => now(),
        ]);

        // Create a valid notification first (FK requires valid notification_id)
        $notifId = DB::table('notifications')->insertGetId([
            'scope' => 'user', 'target_user_type' => 'institute_user',
            'target_user_id' => $u->id, 'category' => 'test',
            'title' => 'Test', 'message' => 'Test notification',
            'created_by_type' => 'system', 'created_at' => now(),
        ]);
        DB::table('notification_reads')->insert([
            'notification_id' => $notifId, 'user_type' => 'institute_user',
            'user_id' => $u->id, 'read_at' => now(),
        ]);

        $this->prepareForForceDelete($u, $a);
        $uTrashed = User::withTrashed()->find($u->id);

        $this->delete(route('admin.users.force-delete', $uTrashed), ['password' => $this->adminPass])->assertRedirect();

        // Verify all user-owned residue removed
        $this->assertDatabaseMissing('users', ['id' => $u->id]);
        $this->assertDatabaseMissing('institution_user', ['user_id' => $u->id]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $u->id]);
        $this->assertDatabaseMissing('email_otps', ['user_id' => $u->id]);
        $this->assertDatabaseMissing('phone_verification_otps', ['user_id' => $u->id]);
        $this->assertDatabaseMissing('phone_2fa_otps', ['user_id' => $u->id]);
        $this->assertDatabaseMissing('phone_password_reset_otps', ['user_id' => $u->id]);
        $this->assertDatabaseMissing('identity_audit_logs', ['user_id' => $u->id]);
        $this->assertDatabaseMissing('user_module_access', ['user_id' => $u->id]);
        $this->assertDatabaseMissing('ai_logs', ['user_id' => $u->id]);
        // E29: audit_logs and activity_logs are PRESERVED — they are institute-scoped
        // business audit records, not user-owned personal data.
        $this->assertDatabaseHas('audit_logs', ['user_id' => $u->id]);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $u->id]);
        $this->assertDatabaseMissing('login_attempts', ['email' => $u->id]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. MEMBERSHIPS REMOVED (including soft-deleted)
    // ═══════════════════════════════════════════════════════════════════════

    public function test_memberships_removed_including_soft_deleted(): void
    {
        $u = $this->makeUser();
        $a = $this->makeInstitute('mem');
        $b = $this->makeInstitute('mem2');
        $this->attach($u, $a);
        $this->attach($u, $b);

        // Soft-delete one membership
        Membership::where('user_id', $u->id)->where('institution_id', $a->id)->delete();

        $this->assertDatabaseHas('institution_user', ['user_id' => $u->id, 'institution_id' => $a->id]);
        $this->assertDatabaseHas('institution_user', ['user_id' => $u->id, 'institution_id' => $b->id]);

        $this->actingAs($this->admin, 'platform_admin');
        $this->delete(route('admin.users.destroy', $u), ['password' => $this->adminPass]);
        $uTrashed = User::withTrashed()->find($u->id);
        $this->delete(route('admin.users.force-delete', $uTrashed), ['password' => $this->adminPass]);

        // Both memberships gone (including the soft-deleted one)
        $this->assertDatabaseMissing('institution_user', ['user_id' => $u->id]);
        $remaining = Membership::withTrashed()->where('user_id', $u->id)->count();
        $this->assertEquals(0, $remaining);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 3. SESSIONS REVOKED
    // ═══════════════════════════════════════════════════════════════════════

    public function test_sessions_revoked_on_force_delete(): void
    {
        $u = $this->makeUser();
        $a = $this->makeInstitute('sess');
        $this->attach($u, $a);

        DB::table('sessions')->insert([
            'id' => uniqid('sess1_'), 'user_id' => $u->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'test', 'payload' => 'x', 'last_activity' => time(),
        ]);
        DB::table('sessions')->insert([
            'id' => uniqid('sess2_'), 'user_id' => $u->id, 'ip_address' => '10.0.0.1',
            'user_agent' => 'test2', 'payload' => 'y', 'last_activity' => time(),
        ]);

        $this->assertEquals(2, DB::table('sessions')->where('user_id', $u->id)->count());

        $this->prepareForForceDelete($u, $a);
        $uTrashed = User::withTrashed()->find($u->id);
        $this->delete(route('admin.users.force-delete', $uTrashed), ['password' => $this->adminPass]);

        $this->assertEquals(0, DB::table('sessions')->where('user_id', $u->id)->count());
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 4. TOKENS REMOVED
    // ═══════════════════════════════════════════════════════════════════════

    public function test_personal_access_tokens_removed(): void
    {
        $u = $this->makeUser();
        $a = $this->makeInstitute('tok');
        $this->attach($u, $a);

        DB::table('personal_access_tokens')->insert([
            'tokenable_id' => $u->id, 'tokenable_type' => 'App\\Models\\User',
            'name' => 'test-token', 'token' => hash('sha256', 'test_token_value'),
            'abilities' => '["*"]', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertEquals(1, DB::table('personal_access_tokens')->where('tokenable_id', $u->id)->count());

        $this->prepareForForceDelete($u, $a);
        $uTrashed = User::withTrashed()->find($u->id);
        $this->delete(route('admin.users.force-delete', $uTrashed), ['password' => $this->adminPass]);

        $this->assertEquals(0, DB::table('personal_access_tokens')->where('tokenable_id', $u->id)->count());
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 5. OTP REMOVED
    // ═══════════════════════════════════════════════════════════════════════

    public function test_all_otp_records_removed(): void
    {
        $u = $this->makeUser();
        $a = $this->makeInstitute('otp');
        $this->attach($u, $a);

        DB::table('email_otps')->insert([
            'user_id' => $u->id, 'email' => $u->email, 'otp_hash' => 'h1',
            'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('phone_verification_otps')->insert([
            'user_id' => $u->id, 'phone' => '+8801000000000', 'otp_hash' => 'h2',
            'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('phone_2fa_otps')->insert([
            'user_id' => $u->id, 'phone' => '+8801000000000', 'otp_hash' => 'h3',
            'guard' => 'web', 'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('phone_password_reset_otps')->insert([
            'user_id' => $u->id, 'phone' => '+8801000000000', 'otp_hash' => 'h4',
            'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->prepareForForceDelete($u, $a);
        $uTrashed = User::withTrashed()->find($u->id);
        $this->delete(route('admin.users.force-delete', $uTrashed), ['password' => $this->adminPass]);

        $this->assertDatabaseMissing('email_otps', ['user_id' => $u->id]);
        $this->assertDatabaseMissing('phone_verification_otps', ['user_id' => $u->id]);
        $this->assertDatabaseMissing('phone_2fa_otps', ['user_id' => $u->id]);
        $this->assertDatabaseMissing('phone_password_reset_otps', ['user_id' => $u->id]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 6. TOTP REMOVED
    // ═══════════════════════════════════════════════════════════════════════

    public function test_totp_secret_and_recovery_codes_removed(): void
    {
        $u = $this->makeUser();
        $a = $this->makeInstitute('totp');
        $this->attach($u, $a);

        // Verify 2FA data was set via DB
        $dbUser = DB::table('users')->where('id', $u->id)->first();
        $this->assertNotNull($dbUser->two_factor_secret);
        $this->assertNotNull($dbUser->two_factor_recovery_codes);

        $this->prepareForForceDelete($u, $a);
        $uTrashed = User::withTrashed()->find($u->id);
        $this->delete(route('admin.users.force-delete', $uTrashed), ['password' => $this->adminPass]);

        $this->assertDatabaseMissing('users', ['id' => $u->id]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 7. PASSWORD RESET REMOVED
    // ═══════════════════════════════════════════════════════════════════════

    public function test_password_reset_tokens_removed(): void
    {
        $u = $this->makeUser();
        $a = $this->makeInstitute('prst');
        $this->attach($u, $a);

        DB::table('password_reset_tokens')->insert([
            'email' => $u->email, 'token' => 'reset_token_' . uniqid(),
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $u->email]);

        $this->prepareForForceDelete($u, $a);
        $uTrashed = User::withTrashed()->find($u->id);
        $this->delete(route('admin.users.force-delete', $uTrashed), ['password' => $this->adminPass]);

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $u->email]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 8. PROFILE DATA REMOVED WHERE APPROPRIATE
    // ═══════════════════════════════════════════════════════════════════════

    public function test_user_preferences_and_pending_state_cleared(): void
    {
        $u = $this->makeUser();
        $a = $this->makeInstitute('prof');
        $this->attach($u, $a);

        $this->prepareForForceDelete($u, $a);
        $uTrashed = User::withTrashed()->find($u->id);
        $this->delete(route('admin.users.force-delete', $uTrashed), ['password' => $this->adminPass]);

        $this->assertDatabaseMissing('users', ['id' => $u->id]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 9. BUSINESS SURVIVES
    // ═══════════════════════════════════════════════════════════════════════

    public function test_business_survives_user_permanent_deletion(): void
    {
        // Use staff user so force-delete is allowed while business still exists
        $u = $this->makeUser('staff');
        $a = $this->makeInstitute('biz');
        $this->attach($u, $a, $this->staffRole);

        $this->actingAs($this->admin, 'platform_admin');
        $this->delete(route('admin.users.destroy', $u), ['password' => $this->adminPass]);
        $uTrashed = User::withTrashed()->find($u->id);
        $this->delete(route('admin.users.force-delete', $uTrashed), ['password' => $this->adminPass]);

        // Business must survive — never silently deleted
        $this->assertDatabaseHas('institutes', ['id' => $a->id, 'deleted_at' => null]);
        $this->assertDatabaseMissing('users', ['id' => $u->id]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 10. OTHER USERS SURVIVE
    // ═══════════════════════════════════════════════════════════════════════

    public function test_other_users_unaffected_by_force_delete(): void
    {
        $u1 = $this->makeUser();
        $u2 = $this->makeUser();
        $a = $this->makeInstitute('iso');
        $b = $this->makeInstitute('iso2');
        $this->attach($u1, $a);
        $this->attach($u2, $b);

        $this->prepareForForceDelete($u1, $a);
        $u1Trashed = User::withTrashed()->find($u1->id);
        $this->delete(route('admin.users.force-delete', $u1Trashed), ['password' => $this->adminPass]);

        // u1 gone, u2 intact
        $this->assertDatabaseMissing('users', ['id' => $u1->id]);
        $this->assertDatabaseHas('users', ['id' => $u2->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('institutes', ['id' => $b->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('institution_user', ['user_id' => $u2->id, 'institution_id' => $b->id]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 11. ACTIVE OWNER DELETION BLOCKED
    // ═══════════════════════════════════════════════════════════════════════

    public function test_active_owner_deletion_blocked(): void
    {
        $u = $this->makeUser();
        $a = $this->makeInstitute('blk');
        $this->attach($u, $a, $this->ownerRole);

        [$allowed, $reason] = AccountDeletionService::canForceDelete($u);
        $this->assertFalse($allowed);
        $this->assertStringContainsString('active business', strtolower($reason));

        // Even through the controller, blocked
        $this->actingAs($this->admin, 'platform_admin');
        $this->delete(route('admin.users.destroy', $u), ['password' => $this->adminPass]);
        $uTrashed = User::withTrashed()->find($u->id);
        Membership::withTrashed()->where('user_id', $u->id)->restore();

        $resp = $this->delete(route('admin.users.force-delete', $uTrashed), ['password' => $this->adminPass]);
        $resp->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $u->id]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 12. TRANSACTION ROLLBACK WORKS
    // ═══════════════════════════════════════════════════════════════════════

    public function test_transaction_rollback_leaves_user_intact(): void
    {
        $u = $this->makeUser();
        $a = $this->makeInstitute('rba');
        $this->attach($u, $a);
        $uid = $u->id;

        try {
            DB::transaction(function () use ($u) {
                $u->delete();
                throw new \Exception('simulated failure');
            });
        } catch (\Exception $e) {}

        $this->assertDatabaseHas('users', ['id' => $uid, 'deleted_at' => null]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 13. FOREIGN KEYS REMAIN ENABLED
    // ═══════════════════════════════════════════════════════════════════════

    public function test_foreign_keys_never_disabled(): void
    {
        $service = file_get_contents(app_path('Services/AccountDeletionService.php'));
        $this->assertStringNotContainsString('FOREIGN_KEY_CHECKS', $service);
        $this->assertStringNotContainsString('SET FOREIGN_KEY_CHECKS', $service);

        $controller = file_get_contents(app_path('Http/Controllers/Admin/UserAccountAdminController.php'));
        $this->assertStringNotContainsString('FOREIGN_KEY_CHECKS', $controller);

        // Verify FK exists on institution_user
        $create = DB::select("SHOW CREATE TABLE institution_user")[0]->{'Create Table'} ?? '';
        if ($create !== '') {
            $this->assertStringContainsString('ON DELETE CASCADE', $create);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 14. AUDIT CONTAINS NO SECRETS
    // ═══════════════════════════════════════════════════════════════════════

    public function test_audit_log_contains_no_secrets(): void
    {
        PlatformAuditLog::query()->delete();
        $u = $this->makeUser();
        $a = $this->makeInstitute('aud');
        $this->attach($u, $a);

        $this->prepareForForceDelete($u, $a);
        $uTrashed = User::withTrashed()->find($u->id);
        $this->delete(route('admin.users.force-delete', $uTrashed), ['password' => $this->adminPass]);

        $log = PlatformAuditLog::where('action', 'account_force_deleted')->latest('id')->first();
        $this->assertNotNull($log);
        $json = strtolower(json_encode($log->meta));
        foreach (['password', 'otp', 'token', 'secret', 'smtp', 'api_key', 'totp', 'recovery'] as $bad) {
            $this->assertStringNotContainsString($bad, $json);
        }
        $this->assertEquals($u->id, $log->meta['user_id'] ?? null);
        $this->assertEquals($u->email, $log->meta['user_email'] ?? null);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 15. DELETED IDENTITY CANNOT AUTHENTICATE
    // ═══════════════════════════════════════════════════════════════════════

    public function test_deleted_identity_cannot_authenticate(): void
    {
        $u = $this->makeUser();
        $a = $this->makeInstitute('auth');
        $this->attach($u, $a);

        $this->prepareForForceDelete($u, $a);
        $uTrashed = User::withTrashed()->find($u->id);
        $this->delete(route('admin.users.force-delete', $uTrashed), ['password' => $this->adminPass]);

        // User row is gone — cannot be found for authentication
        $this->assertNull(User::find($u->id));
        $this->assertNull(User::withTrashed()->find($u->id));
        // Sessions are gone
        $this->assertEquals(0, DB::table('sessions')->where('user_id', $u->id)->count());
        // Tokens are gone
        $this->assertEquals(0, DB::table('personal_access_tokens')->where('tokenable_id', $u->id)->count());
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 16. DELETED IDENTITY CANNOT RECEIVE OTP
    // ═══════════════════════════════════════════════════════════════════════

    public function test_deleted_identity_cannot_receive_otp(): void
    {
        $u = $this->makeUser();
        $a = $this->makeInstitute('otp2');
        $this->attach($u, $a);

        DB::table('email_otps')->insert([
            'user_id' => $u->id, 'email' => $u->email, 'otp_hash' => 'h',
            'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('phone_verification_otps')->insert([
            'user_id' => $u->id, 'phone' => '+8801000000000', 'otp_hash' => 'h',
            'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->prepareForForceDelete($u, $a);
        $uTrashed = User::withTrashed()->find($u->id);
        $this->delete(route('admin.users.force-delete', $uTrashed), ['password' => $this->adminPass]);

        $this->assertDatabaseMissing('email_otps', ['user_id' => $u->id]);
        $this->assertDatabaseMissing('phone_verification_otps', ['user_id' => $u->id]);
        $this->assertDatabaseMissing('phone_2fa_otps', ['user_id' => $u->id]);
        $this->assertDatabaseMissing('phone_password_reset_otps', ['user_id' => $u->id]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 17. DELETED IDENTITY CANNOT RESET PASSWORD
    // ═══════════════════════════════════════════════════════════════════════

    public function test_deleted_identity_cannot_reset_password(): void
    {
        $u = $this->makeUser();
        $a = $this->makeInstitute('rst');
        $this->attach($u, $a);

        DB::table('password_reset_tokens')->insert([
            'email' => $u->email, 'token' => 'rst_' . uniqid(), 'created_at' => now(),
        ]);
        DB::table('phone_password_reset_otps')->insert([
            'user_id' => $u->id, 'phone' => '+8801000000000', 'otp_hash' => 'h',
            'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->prepareForForceDelete($u, $a);
        $uTrashed = User::withTrashed()->find($u->id);
        $this->delete(route('admin.users.force-delete', $uTrashed), ['password' => $this->adminPass]);

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $u->email]);
        $this->assertDatabaseMissing('phone_password_reset_otps', ['user_id' => $u->id]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // NEGATIVE TEST — BUSINESS MUST SURVIVE (Single Business)
    // ═══════════════════════════════════════════════════════════════════════

    public function test_single_business_owner_deletion_blocked_business_survives(): void
    {
        $u = $this->makeUser();
        $a = $this->makeInstitute('neg1');
        $this->attach($u, $a, $this->ownerRole);

        // Blocked before any soft delete
        [$allowed] = AccountDeletionService::canForceDelete($u);
        $this->assertFalse($allowed);

        // Soft-delete user (this also soft-deletes memberships)
        $this->actingAs($this->admin, 'platform_admin');
        $this->delete(route('admin.users.destroy', $u), ['password' => $this->adminPass]);
        $uTrashed = User::withTrashed()->find($u->id);

        // Restore membership to simulate "owns active business" at forceDelete time
        Membership::withTrashed()->where('user_id', $u->id)->restore();

        $resp = $this->delete(route('admin.users.force-delete', $uTrashed), ['password' => $this->adminPass]);
        $resp->assertSessionHasErrors('user');

        // Business survives
        $this->assertDatabaseHas('institutes', ['id' => $a->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('users', ['id' => $u->id]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // MULTI-BUSINESS TEST — ALL ACTIVE BLOCKED
    // ═══════════════════════════════════════════════════════════════════════

    public function test_multi_business_owner_all_active_blocked(): void
    {
        $u = $this->makeUser();
        $a = $this->makeInstitute('mb1');
        $b = $this->makeInstitute('mb2');
        $c = $this->makeInstitute('mb3');
        $this->attach($u, $a);
        $this->attach($u, $b);
        $this->attach($u, $c);

        [$allowed, $reason] = AccountDeletionService::canForceDelete($u);
        $this->assertFalse($allowed);
        $this->assertStringContainsString('3 active business', $reason);

        // No business deleted
        $this->assertDatabaseHas('institutes', ['id' => $a->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('institutes', ['id' => $b->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('institutes', ['id' => $c->id, 'deleted_at' => null]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SHARED BUSINESS TEST — DELETE ONE USER, OTHER SURVIVES
    // ═══════════════════════════════════════════════════════════════════════

    public function test_shared_business_delete_one_user_other_survives(): void
    {
        $u1 = $this->makeUser('staff');
        $u2 = $this->makeUser('staff');
        $a = $this->makeInstitute('sh1');
        $this->attach($u1, $a, $this->staffRole);
        $this->attach($u2, $a, $this->staffRole);

        // Delete u1 (staff, not owner, so force delete allowed)
        $this->actingAs($this->admin, 'platform_admin');
        $this->delete(route('admin.users.destroy', $u1), ['password' => $this->adminPass]);
        $u1Trashed = User::withTrashed()->find($u1->id);
        $this->delete(route('admin.users.force-delete', $u1Trashed), ['password' => $this->adminPass]);

        // u1 gone, u2 intact, business intact
        $this->assertDatabaseMissing('users', ['id' => $u1->id]);
        $this->assertDatabaseHas('users', ['id' => $u2->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('institutes', ['id' => $a->id, 'deleted_at' => null]);
        // u2 membership remains
        $this->assertDatabaseHas('institution_user', ['user_id' => $u2->id, 'institution_id' => $a->id]);
        // u1 membership removed
        $this->assertDatabaseMissing('institution_user', ['user_id' => $u1->id, 'institution_id' => $a->id]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TWO-USER ISOLATION TEST
    // ═══════════════════════════════════════════════════════════════════════

    public function test_two_user_isolation_delete_one_other_intact(): void
    {
        $u1 = $this->makeUser('staff');
        $u2 = $this->makeUser('staff');
        $a = $this->makeInstitute('iso1');
        $b = $this->makeInstitute('iso2');
        $this->attach($u1, $a, $this->staffRole);
        $this->attach($u2, $b, $this->staffRole);

        // Add sessions/tokens for both
        DB::table('sessions')->insert([
            'id' => uniqid('s1_'), 'user_id' => $u1->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'test', 'payload' => 'x', 'last_activity' => time(),
        ]);
        DB::table('sessions')->insert([
            'id' => uniqid('s2_'), 'user_id' => $u2->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'test', 'payload' => 'x', 'last_activity' => time(),
        ]);

        // Delete u1
        $this->actingAs($this->admin, 'platform_admin');
        $this->delete(route('admin.users.destroy', $u1), ['password' => $this->adminPass]);
        $u1Trashed = User::withTrashed()->find($u1->id);
        $this->delete(route('admin.users.force-delete', $u1Trashed), ['password' => $this->adminPass]);

        // u1 completely gone
        $this->assertDatabaseMissing('users', ['id' => $u1->id]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $u1->id]);
        $this->assertDatabaseMissing('institution_user', ['user_id' => $u1->id]);

        // u2 completely intact
        $this->assertDatabaseHas('users', ['id' => $u2->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('sessions', ['user_id' => $u2->id]);
        $this->assertDatabaseHas('institution_user', ['user_id' => $u2->id, 'institution_id' => $b->id]);
        $this->assertDatabaseHas('institutes', ['id' => $b->id, 'deleted_at' => null]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // USER WITH DELETED BUSINESSES ONLY — ALLOWED
    // ═══════════════════════════════════════════════════════════════════════

    public function test_user_with_only_deleted_businesses_allowed(): void
    {
        $u = $this->makeUser();
        $a = $this->makeInstitute('dba');
        $b = $this->makeInstitute('dbb');
        $this->attach($u, $a);
        $this->attach($u, $b);

        // Delete both businesses
        $this->actingAs($this->admin, 'platform_admin');
        $this->post(route('admin.institutes.action', $a), ['action' => 'delete', 'password' => $this->adminPass]);
        $this->post(route('admin.institutes.action', $b), ['action' => 'delete', 'password' => $this->adminPass]);
        $aTrashed = Institute::withTrashed()->find($a->id);
        $bTrashed = Institute::withTrashed()->find($b->id);
        $this->delete(route('admin.institutes.force-delete', $aTrashed), ['password' => $this->adminPass]);
        $this->delete(route('admin.institutes.force-delete', $bTrashed), ['password' => $this->adminPass]);

        // Now allowed
        [$allowed] = AccountDeletionService::canForceDelete($u->refresh());
        $this->assertTrue($allowed);

        // Proceed with user deletion
        $this->delete(route('admin.users.destroy', $u), ['password' => $this->adminPass]);
        $uTrashed = User::withTrashed()->find($u->id);
        $this->delete(route('admin.users.force-delete', $uTrashed), ['password' => $this->adminPass]);

        $this->assertDatabaseMissing('users', ['id' => $u->id]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // STAFF NOT OWNER — ALLOWED EVEN WITH ACTIVE BUSINESS
    // ═══════════════════════════════════════════════════════════════════════

    public function test_staff_not_owner_allowed_with_active_business(): void
    {
        $u = $this->makeUser('staff');
        $a = $this->makeInstitute('stf');
        $this->attach($u, $a, $this->staffRole);

        [$allowed] = AccountDeletionService::canForceDelete($u);
        $this->assertTrue($allowed);

        $this->actingAs($this->admin, 'platform_admin');
        $this->delete(route('admin.users.destroy', $u), ['password' => $this->adminPass]);
        $uTrashed = User::withTrashed()->find($u->id);
        $this->delete(route('admin.users.force-delete', $uTrashed), ['password' => $this->adminPass]);

        $this->assertDatabaseMissing('users', ['id' => $u->id]);
        $this->assertDatabaseHas('institutes', ['id' => $a->id, 'deleted_at' => null]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // REGRESSION: E26 EXISTING TESTS STILL PASS (key scenarios)
    // ═══════════════════════════════════════════════════════════════════════

    public function test_e26_soft_delete_preserves_business_and_audit(): void
    {
        PlatformAuditLog::query()->delete();
        $u = $this->makeUser();
        $a = $this->makeInstitute('reg');
        $this->attach($u, $a);
        DB::table('sessions')->insert([
            'id' => uniqid('reg_'), 'user_id' => $u->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'test', 'payload' => 'x', 'last_activity' => time(),
        ]);

        $this->actingAs($this->admin, 'platform_admin');
        $this->delete(route('admin.users.destroy', $u), ['password' => $this->adminPass])->assertRedirect();
        $this->assertSoftDeleted('users', ['id' => $u->id]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $u->id]);
        $this->assertDatabaseHas('institutes', ['id' => $a->id, 'deleted_at' => null]);

        $log = PlatformAuditLog::where('action', 'account_soft_deleted')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('password', strtolower(json_encode($log->meta)));
    }

    public function test_e26_restore_restores_user_and_memberships(): void
    {
        $u = $this->makeUser();
        $a = $this->makeInstitute('rst2');
        $this->attach($u, $a);

        $this->actingAs($this->admin, 'platform_admin');
        $this->delete(route('admin.users.destroy', $u), ['password' => $this->adminPass]);
        $this->assertSoftDeleted('users', ['id' => $u->id]);

        $uTrashed = User::withTrashed()->find($u->id);
        $this->post(route('admin.users.restore', $uTrashed))->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $u->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('institution_user', ['user_id' => $u->id, 'institution_id' => $a->id, 'deleted_at' => null]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // canForceDelete DETAIL DATA
    // ═══════════════════════════════════════════════════════════════════════

    public function test_get_deletion_check_data_returns_accurate_info(): void
    {
        $u = $this->makeUser();
        $a = $this->makeInstitute('chk');
        $this->attach($u, $a);

        $data = AccountDeletionService::getDeletionCheckData($u);
        $this->assertEquals($u->id, $data['user_id']);
        $this->assertEquals(1, $data['total_memberships']);
        $this->assertEquals(1, $data['active_memberships']);
        $this->assertEquals(1, $data['owned_active']);
        $this->assertFalse($data['can_delete']);
        $this->assertNotNull($data['block_reason']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // NOTIFICATION READS CLEANUP
    // ═══════════════════════════════════════════════════════════════════════

    public function test_notification_reads_cleaned(): void
    {
        $u = $this->makeUser();
        $a = $this->makeInstitute('nrd');
        $this->attach($u, $a);

        // Create a valid notification first (FK requires valid notification_id)
        $notifId = DB::table('notifications')->insertGetId([
            'scope' => 'user', 'target_user_type' => 'institute_user',
            'target_user_id' => $u->id, 'category' => 'test',
            'title' => 'Test', 'message' => 'Test notification',
            'created_by_type' => 'system', 'created_at' => now(),
        ]);

        DB::table('notification_reads')->insert([
            'notification_id' => $notifId, 'user_type' => 'institute_user',
            'user_id' => $u->id, 'read_at' => now(),
        ]);

        $this->prepareForForceDelete($u, $a);
        $uTrashed = User::withTrashed()->find($u->id);
        $this->delete(route('admin.users.force-delete', $uTrashed), ['password' => $this->adminPass]);

        $this->assertDatabaseMissing('notification_reads', ['user_id' => $u->id]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // LOGIN ATTEMPTS CLEANUP
    // ═══════════════════════════════════════════════════════════════════════

    public function test_login_attempts_cleaned(): void
    {
        $u = $this->makeUser();
        $a = $this->makeInstitute('la');
        $this->attach($u, $a);

        DB::table('login_attempts')->insert([
            'email' => $u->email, 'user_type' => 'institute_user',
            'ip_address' => '127.0.0.1', 'is_success' => 1, 'attempted_at' => now(),
        ]);
        DB::table('login_attempts')->insert([
            'email' => $u->email, 'user_type' => 'institute_user',
            'ip_address' => '127.0.0.1', 'is_success' => 0, 'attempted_at' => now(),
        ]);

        $this->prepareForForceDelete($u, $a);
        $uTrashed = User::withTrashed()->find($u->id);
        $this->delete(route('admin.users.force-delete', $uTrashed), ['password' => $this->adminPass]);

        $this->assertEquals(0, DB::table('login_attempts')->where('email', $u->email)->count());
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PASSWORD CONFIRMATION REQUIRED
    // ═══════════════════════════════════════════════════════════════════════

    public function test_wrong_password_rejected_on_force_delete(): void
    {
        $u = $this->makeUser();
        $a = $this->makeInstitute('pw');
        $this->attach($u, $a);

        $this->actingAs($this->admin, 'platform_admin');
        $this->delete(route('admin.users.destroy', $u), ['password' => $this->adminPass]);
        $uTrashed = User::withTrashed()->find($u->id);

        $resp = $this->delete(route('admin.users.force-delete', $uTrashed), ['password' => 'wrong_password']);
        $resp->assertSessionHasErrors('password');
        $this->assertDatabaseHas('users', ['id' => $u->id]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // USER MUST BE IN BIN FIRST
    // ═══════════════════════════════════════════════════════════════════════

    public function test_force_delete_requires_user_in_bin(): void
    {
        $u = $this->makeUser();
        $this->actingAs($this->admin, 'platform_admin');

        $resp = $this->delete(route('admin.users.force-delete', $u), ['password' => $this->adminPass]);
        $resp->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $u->id, 'deleted_at' => null]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // MULTIPLE BUSINESSES — SOME DELETED, SOME ACTIVE — BLOCKED
    // ═══════════════════════════════════════════════════════════════════════

    public function test_mixed_business_states_blocked_if_any_active(): void
    {
        $u = $this->makeUser();
        $a = $this->makeInstitute('mix1');
        $b = $this->makeInstitute('mix2');
        $c = $this->makeInstitute('mix3');
        $this->attach($u, $a);
        $this->attach($u, $b);
        $this->attach($u, $c);

        // Delete business a
        $this->actingAs($this->admin, 'platform_admin');
        $this->post(route('admin.institutes.action', $a), ['action' => 'delete', 'password' => $this->adminPass]);
        $aTrashed = Institute::withTrashed()->find($a->id);
        $this->delete(route('admin.institutes.force-delete', $aTrashed), ['password' => $this->adminPass]);

        // Still blocked (b and c active)
        [$allowed, $reason] = AccountDeletionService::canForceDelete($u);
        $this->assertFalse($allowed);
        $this->assertStringContainsString('2 active business', $reason);

        // Businesses b and c still exist
        $this->assertDatabaseHas('institutes', ['id' => $b->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('institutes', ['id' => $c->id, 'deleted_at' => null]);
    }
}
