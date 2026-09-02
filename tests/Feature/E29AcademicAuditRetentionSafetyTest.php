<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Membership;
use App\Models\PlatformAdmin;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * E29 — Academic, Audit Trail & Business Record Retention Safety
 *
 * Forensic verification that permanent deletion of a GLOBAL USER ACCOUNT:
 * - PRESERVES all academic records (students, certificates, exam results)
 * - PRESERVES business audit trail (audit_logs, activity_logs) for compliance
 * - PRESERVES all business/institute records and financial data
 * - Does NOT trigger unintended CASCADE deletions on business tables
 */
class E29AcademicAuditRetentionSafetyTest extends TestCase
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
            'email' => 'e29-admin-' . uniqid() . '@example.test',
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
            'name' => 'E29 Inst ' . $s . ' ' . uniqid(),
            'slug' => 'e29-' . uniqid() . ($s ? '-' . $s : ''),
            'status' => 'active',
        ]);
    }

    private function makeUser(string $type = 'owner'): User
    {
        return User::create([
            'name' => 'E29 User ' . uniqid(),
            'email' => 'e29-' . uniqid() . '@example.test',
            'phone' => '+8801' . str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT),
            'password_hash' => bcrypt('password'),
            'account_type' => $type,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
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

    private function deleteUser(User $user): void
    {
        $this->actingAs($this->admin, 'platform_admin');
        $this->delete(route('admin.users.destroy', $user), ['password' => $this->adminPass]);
        $uTrashed = User::withTrashed()->find($user->id);
        $this->delete(route('admin.users.force-delete', $uTrashed), ['password' => $this->adminPass]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. STUDENTS SURVIVE USER DELETION
    // ═══════════════════════════════════════════════════════════════════════

    public function test_students_survive_user_deletion(): void
    {
        $u = $this->makeUser('staff');
        $a = $this->makeInstitute('stu');
        $this->attach($u, $a, $this->staffRole);

        $studentId = DB::table('students')->insertGetId([
            'institute_id' => $a->id,
            'first_name' => 'Test',
            'last_name' => 'Student',
            'student_id_number' => 'STU001',
            'admission_status' => 'enrolled',
            'admission_date' => now()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->deleteUser($u);

        // Student must survive — students are institute-scoped, not user-scoped
        $this->assertDatabaseHas('students', ['id' => $studentId]);
        // Business must survive
        $this->assertDatabaseHas('institutes', ['id' => $a->id, 'deleted_at' => null]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. CERTIFICATES SURVIVE USER DELETION
    // ═══════════════════════════════════════════════════════════════════════

    public function test_certificates_table_not_referenced_in_deletion_service(): void
    {
        $service = file_get_contents(app_path('Services/AccountDeletionService.php'));
        // The service must NEVER delete from the certificates table
        // Certificates are institute-scoped business records
        $this->assertStringNotContainsString("'certificates'", $service);
        $this->assertStringNotContainsString('"certificates"', $service);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 3. EXAM RESULTS NOT DELETED BY USER DELETION SERVICE
    // ═══════════════════════════════════════════════════════════════════════

    public function test_exam_results_table_not_referenced_in_deletion_service(): void
    {
        $service = file_get_contents(app_path('Services/AccountDeletionService.php'));
        // The service must NEVER delete from the exam_results table
        $this->assertStringNotContainsString("'exam_results'", $service);
        $this->assertStringNotContainsString('"exam_results"', $service);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 4. AUDIT LOGS PRESERVED AFTER USER DELETION
    // ═══════════════════════════════════════════════════════════════════════

    public function test_audit_logs_preserved_after_user_deletion(): void
    {
        $u = $this->makeUser('staff');
        $a = $this->makeInstitute('alog');
        $this->attach($u, $a, $this->staffRole);

        DB::table('audit_logs')->insert([
            'user_type' => 'institute_user',
            'user_id' => $u->id,
            'institute_id' => $a->id,
            'action' => 'user.login',
            'module' => 'auth',
            'created_at' => now(),
        ]);

        $this->deleteUser($u);

        // Audit log must be PRESERVED — institute-scoped business record
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $u->id,
            'institute_id' => $a->id,
            'action' => 'user.login',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 5. ACTIVITY LOGS PRESERVED AFTER USER DELETION
    // ═══════════════════════════════════════════════════════════════════════

    public function test_activity_logs_preserved_after_user_deletion(): void
    {
        $u = $this->makeUser('staff');
        $a = $this->makeInstitute('actlog');
        $this->attach($u, $a, $this->staffRole);

        DB::table('activity_logs')->insert([
            'user_type' => 'institute_user',
            'user_id' => $u->id,
            'institute_id' => $a->id,
            'activity' => 'Created student enrollment',
            'created_at' => now(),
        ]);

        $this->deleteUser($u);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $u->id,
            'institute_id' => $a->id,
            'activity' => 'Created student enrollment',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 6. MEMBERSHIP ROWS CLEANED — NO CASCADE TO BUSINESS TABLES
    // ═══════════════════════════════════════════════════════════════════════

    public function test_membership_cleaned_no_cascade_to_business(): void
    {
        $u = $this->makeUser('staff');
        $a = $this->makeInstitute('memc');
        $this->attach($u, $a, $this->staffRole);

        $this->assertDatabaseHas('institution_user', ['user_id' => $u->id, 'institution_id' => $a->id]);

        $this->deleteUser($u);

        // Membership row must be removed
        $this->assertDatabaseMissing('institution_user', ['user_id' => $u->id]);
        // Business must survive
        $this->assertDatabaseHas('institutes', ['id' => $a->id, 'deleted_at' => null]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 7. MULTI-BUSINESS — DELETE USER IN ONE, OTHERS UNAFFECTED
    // ═══════════════════════════════════════════════════════════════════════

    public function test_multi_business_delete_user_in_one_others_unaffected(): void
    {
        $u1 = $this->makeUser('staff');
        $u2 = $this->makeUser('staff');
        $a = $this->makeInstitute('mb1');
        $b = $this->makeInstitute('mb2');
        $this->attach($u1, $a, $this->staffRole);
        $this->attach($u2, $b, $this->staffRole);

        $this->deleteUser($u1);

        // u1 membership gone
        $this->assertDatabaseMissing('institution_user', ['user_id' => $u1->id]);
        // Business A survives
        $this->assertDatabaseHas('institutes', ['id' => $a->id, 'deleted_at' => null]);
        // Business B completely untouched
        $this->assertDatabaseHas('institutes', ['id' => $b->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('institution_user', ['user_id' => $u2->id, 'institution_id' => $b->id]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 8. FINANCIAL RECORDS PRESERVED
    // ═══════════════════════════════════════════════════════════════════════

    public function test_financial_records_preserved_after_user_deletion(): void
    {
        $u = $this->makeUser('staff');
        $a = $this->makeInstitute('fin');
        $this->attach($u, $a, $this->staffRole);

        // Business must survive
        $this->deleteUser($u);
        $this->assertDatabaseHas('institutes', ['id' => $a->id, 'deleted_at' => null]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 9. INSTITUTION-SCOPED NOTIFICATIONS PRESERVED
    // ═══════════════════════════════════════════════════════════════════════

    public function test_institution_notifications_preserved_after_user_deletion(): void
    {
        $u = $this->makeUser('staff');
        $a = $this->makeInstitute('notif');
        $this->attach($u, $a, $this->staffRole);

        // Seed institution-level notification
        $notifId = DB::table('notifications')->insertGetId([
            'scope' => 'user',
            'institute_id' => $a->id,
            'target_user_type' => 'institute_user',
            'target_user_id' => $u->id,
            'category' => 'system',
            'title' => 'Business notification',
            'message' => 'This should be cleaned with user',
            'created_by_type' => 'system',
            'created_at' => now(),
        ]);

        $this->deleteUser($u);

        // User-targeted notification should be cleaned
        $this->assertDatabaseMissing('notifications', ['id' => $notifId]);
        // Business survives
        $this->assertDatabaseHas('institutes', ['id' => $a->id, 'deleted_at' => null]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 10. MULTIPLE USERS SAME BUSINESS — DELETE ONE, OTHERS INTACT
    // ═══════════════════════════════════════════════════════════════════════

    public function test_multiple_users_same_business_delete_one_others_intact(): void
    {
        $u1 = $this->makeUser('staff');
        $u2 = $this->makeUser('staff');
        $u3 = $this->makeUser('staff');
        $a = $this->makeInstitute('mus');
        $this->attach($u1, $a, $this->staffRole);
        $this->attach($u2, $a, $this->staffRole);
        $this->attach($u3, $a, $this->staffRole);

        $this->deleteUser($u1);

        // u1 gone
        $this->assertDatabaseMissing('institution_user', ['user_id' => $u1->id]);
        // u2 and u3 intact
        $this->assertDatabaseHas('institution_user', ['user_id' => $u2->id, 'institution_id' => $a->id]);
        $this->assertDatabaseHas('institution_user', ['user_id' => $u3->id, 'institution_id' => $a->id]);
        // Business intact
        $this->assertDatabaseHas('institutes', ['id' => $a->id, 'deleted_at' => null]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 11. SESSIONS AND TOKENS FULLY CLEANED
    // ═══════════════════════════════════════════════════════════════════════

    public function test_sessions_and_tokens_fully_cleaned(): void
    {
        $u = $this->makeUser('staff');
        $a = $this->makeInstitute('stok');
        $this->attach($u, $a, $this->staffRole);

        DB::table('sessions')->insert([
            'id' => uniqid('e29_s1_'), 'user_id' => $u->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'test', 'payload' => 'x', 'last_activity' => time(),
        ]);
        DB::table('personal_access_tokens')->insert([
            'tokenable_id' => $u->id, 'tokenable_type' => 'App\\Models\\User',
            'name' => 'api-token', 'token' => hash('sha256', 'e29_token'),
            'abilities' => '["*"]', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->deleteUser($u);

        $this->assertEquals(0, DB::table('sessions')->where('user_id', $u->id)->count());
        $this->assertEquals(0, DB::table('personal_access_tokens')->where('tokenable_id', $u->id)->count());
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 12. OTP RECORDS FULLY CLEANED
    // ═══════════════════════════════════════════════════════════════════════

    public function test_all_otp_records_fully_cleaned(): void
    {
        $u = $this->makeUser('staff');
        $a = $this->makeInstitute('e29otp');
        $this->attach($u, $a, $this->staffRole);

        DB::table('email_otps')->insert([
            'user_id' => $u->id, 'email' => $u->email, 'otp_hash' => 'h',
            'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('phone_verification_otps')->insert([
            'user_id' => $u->id, 'phone' => '+8801000000000', 'otp_hash' => 'h',
            'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('phone_2fa_otps')->insert([
            'user_id' => $u->id, 'phone' => '+8801000000000', 'otp_hash' => 'h',
            'guard' => 'web', 'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('phone_password_reset_otps')->insert([
            'user_id' => $u->id, 'phone' => '+8801000000000', 'otp_hash' => 'h',
            'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->deleteUser($u);

        $this->assertDatabaseMissing('email_otps', ['user_id' => $u->id]);
        $this->assertDatabaseMissing('phone_verification_otps', ['user_id' => $u->id]);
        $this->assertDatabaseMissing('phone_2fa_otps', ['user_id' => $u->id]);
        $this->assertDatabaseMissing('phone_password_reset_otps', ['user_id' => $u->id]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 13. IDENTITY CANNOT AUTHENTICATE AFTER DELETION
    // ═══════════════════════════════════════════════════════════════════════

    public function test_identity_cannot_authenticate_after_deletion(): void
    {
        $u = $this->makeUser('staff');
        $a = $this->makeInstitute('e29auth');
        $this->attach($u, $a, $this->staffRole);

        $this->deleteUser($u);

        $this->assertNull(User::find($u->id));
        $this->assertNull(User::withTrashed()->find($u->id));
        $this->assertEquals(0, DB::table('sessions')->where('user_id', $u->id)->count());
        $this->assertEquals(0, DB::table('personal_access_tokens')->where('tokenable_id', $u->id)->count());
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 14. PASSWORD RESET TOKENS CLEANED
    // ═══════════════════════════════════════════════════════════════════════

    public function test_password_reset_tokens_cleaned(): void
    {
        $u = $this->makeUser('staff');
        $a = $this->makeInstitute('e29prst');
        $this->attach($u, $a, $this->staffRole);

        DB::table('password_reset_tokens')->insert([
            'email' => $u->email, 'token' => 'rst_' . uniqid(), 'created_at' => now(),
        ]);

        $this->deleteUser($u);

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $u->email]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 15. SOFT DELETE THEN RESTORE — BUSINESS INTACT
    // ═══════════════════════════════════════════════════════════════════════

    public function test_soft_delete_then_restore_business_intact(): void
    {
        $u = $this->makeUser('staff');
        $a = $this->makeInstitute('e29rst');
        $this->attach($u, $a, $this->staffRole);

        // Soft delete
        $this->actingAs($this->admin, 'platform_admin');
        $this->delete(route('admin.users.destroy', $u), ['password' => $this->adminPass]);
        $this->assertSoftDeleted('users', ['id' => $u->id]);
        $this->assertDatabaseHas('institutes', ['id' => $a->id, 'deleted_at' => null]);

        // Restore
        $uTrashed = User::withTrashed()->find($u->id);
        $this->post(route('admin.users.restore', $uTrashed));

        $this->assertDatabaseHas('users', ['id' => $u->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('institutes', ['id' => $a->id, 'deleted_at' => null]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 16. ACTIVE OWNER CANNOT BE FORCE-DELETED
    // ═══════════════════════════════════════════════════════════════════════

    public function test_active_owner_cannot_be_force_deleted(): void
    {
        $u = $this->makeUser();
        $a = $this->makeInstitute('e29blk');
        $this->attach($u, $a, $this->ownerRole);

        [$allowed, $reason] = AccountDeletionService::canForceDelete($u);
        $this->assertFalse($allowed);
        $this->assertStringContainsString('active business', strtolower($reason));
        $this->assertDatabaseHas('institutes', ['id' => $a->id, 'deleted_at' => null]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 17. NO FOREIGN_KEY_CHECKS IN SERVICE CODE
    // ═══════════════════════════════════════════════════════════════════════

    public function test_no_foreign_key_checks_in_service_code(): void
    {
        $service = file_get_contents(app_path('Services/AccountDeletionService.php'));
        $this->assertStringNotContainsString('FOREIGN_KEY_CHECKS', $service);
        $this->assertStringNotContainsString('SET FOREIGN_KEY_CHECKS', $service);

        $controller = file_get_contents(app_path('Http/Controllers/Admin/UserAccountAdminController.php'));
        $this->assertStringNotContainsString('FOREIGN_KEY_CHECKS', $controller);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 18. USER DELETION DOES NOT TOUCH AUDIT LOGS TABLE
    // ═══════════════════════════════════════════════════════════════════════

    public function test_user_deletion_does_not_delete_audit_logs(): void
    {
        $u = $this->makeUser('staff');
        $a = $this->makeInstitute('e29nla');
        $this->attach($u, $a, $this->staffRole);

        // Seed multiple audit log entries for this user
        for ($i = 0; $i < 5; $i++) {
            DB::table('audit_logs')->insert([
                'user_type' => 'institute_user',
                'user_id' => $u->id,
                'institute_id' => $a->id,
                'action' => 'test.action.' . $i,
                'module' => 'test',
                'created_at' => now(),
            ]);
        }
        $this->assertEquals(5, DB::table('audit_logs')->where('user_id', $u->id)->count());

        $this->deleteUser($u);

        // All 5 audit log entries must be PRESERVED
        $this->assertEquals(5, DB::table('audit_logs')->where('user_id', $u->id)->count());
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 19. USER DELETION DOES NOT TOUCH ACTIVITY LOGS TABLE
    // ═══════════════════════════════════════════════════════════════════════

    public function test_user_deletion_does_not_delete_activity_logs(): void
    {
        $u = $this->makeUser('staff');
        $a = $this->makeInstitute('e29nla2');
        $this->attach($u, $a, $this->staffRole);

        for ($i = 0; $i < 3; $i++) {
            DB::table('activity_logs')->insert([
                'user_type' => 'institute_user',
                'user_id' => $u->id,
                'institute_id' => $a->id,
                'activity' => 'Activity ' . $i,
                'created_at' => now(),
            ]);
        }
        $this->assertEquals(3, DB::table('activity_logs')->where('user_id', $u->id)->count());

        $this->deleteUser($u);

        $this->assertEquals(3, DB::table('activity_logs')->where('user_id', $u->id)->count());
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 20. SHARED BUSINESS — DELETE STAFF USER, OWNERSHIP UNAFFECTED
    // ═══════════════════════════════════════════════════════════════════════

    public function test_shared_business_delete_staff_owner_ownership_unaffected(): void
    {
        $owner = $this->makeUser();
        $staff = $this->makeUser('staff');
        $a = $this->makeInstitute('shr');
        $this->attach($owner, $a, $this->ownerRole);
        $this->attach($staff, $a, $this->staffRole);

        $this->deleteUser($staff);

        // Staff gone
        $this->assertDatabaseMissing('institution_user', ['user_id' => $staff->id]);
        // Owner membership still intact
        $this->assertDatabaseHas('institution_user', ['user_id' => $owner->id, 'institution_id' => $a->id]);
        // Business intact
        $this->assertDatabaseHas('institutes', ['id' => $a->id, 'deleted_at' => null]);
    }
}
