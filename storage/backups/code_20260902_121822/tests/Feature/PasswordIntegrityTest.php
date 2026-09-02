<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Services\Auth\PasswordService;
use App\Services\UserAccountService;
use App\Support\PasswordHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordIntegrityTest extends TestCase
{
    use DatabaseTransactions;

    private PasswordService $passwords;

    protected function setUp(): void
    {
        parent::setUp();
        $this->passwords = app(PasswordService::class);
    }

    // 1. Owner registration password
    public function test_owner_registration_password(): void
    {
        $plain = 'OwnerPass123!';
        $user = app(UserAccountService::class)->registerOwner([
            'name' => 'Owner Test',
            'first_name' => 'Owner',
            'last_name' => 'Test',
            'email' => 'owner-integrity@example.test',
            'phone' => '01700001001',
            'password_hash' => $plain,
            'status' => 'active',
        ]);

        $this->assertTrue(PasswordHash::looksValid($user->fresh()->getAuthPassword()));
        $this->assertTrue(Hash::check($plain, $user->fresh()->getAuthPassword()));
        $this->assertTrue(PasswordHash::safeCheck($plain, $user->fresh()->getAuthPassword()));
    }

    // 2. Staff password creation via invitation path
    public function test_staff_password_creation(): void
    {
        $plain = 'StaffPass123!';
        $user = app(UserAccountService::class)->createStaffFromInvitation([
            'name' => 'Staff Test',
            'first_name' => 'Staff',
            'last_name' => 'Test',
            'email' => 'staff-integrity@example.test',
            'phone' => '01700001002',
            'password_hash' => $plain,
            'status' => 'active',
        ]);

        $this->assertSame('staff', $user->account_type);
        $this->assertTrue(Hash::check($plain, $user->fresh()->getAuthPassword()));
    }

    // 3. Normal login web guard
    public function test_normal_login_web(): void
    {
        $plain = 'LoginPass123!';
        $user = app(UserAccountService::class)->registerOwner([
            'name' => 'Login User',
            'email' => 'login-web@example.test',
            'phone' => '01700001003',
            'password_hash' => $plain,
            'status' => 'active',
        ]);

        $response = $this->post('/login', ['email' => $user->email, 'password' => $plain]);
        // Owner without workspace lands on picker, with workspace on dashboard — both mean success
        $response->assertRedirect();
        $this->assertTrue(in_array($response->headers->get('Location'), ['http://localhost', 'http://localhost/', 'http://localhost/workspace', 'http://localhost/workspace/create'], true) || str_contains($response->headers->get('Location'), '/workspace') || $response->isRedirect('/'));
        $this->assertAuthenticatedAs($user, 'web');
    }

    // 3b. Normal login institute_user guard
    public function test_normal_login_institute_user(): void
    {
        $institute = Institute::firstOrFail();
        $plain = 'InstitutePass123!';
        $staff = InstituteUser::create([
            'institute_id' => $institute->id,
            'role_id' => 1,
            'email' => 'login-institute@example.test',
            'phone' => '01700001004',
            'password_hash' => $this->passwords->hash($plain),
            'status' => 'active',
        ]);

        $this->post('/institute/login', ['email' => $staff->email, 'password' => $plain])->assertRedirect('/');
        $this->assertAuthenticatedAs($staff, 'institute_user');
    }

    // 3c. Normal login platform_admin guard
    public function test_normal_login_platform_admin(): void
    {
        $plain = 'AdminPass123!';
        $admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'admin-integrity@example.test',
            'password_hash' => $this->passwords->hash($plain),
            'status' => 'active',
        ]);

        $this->post('/admin/login', ['email' => $admin->email, 'password' => $plain])->assertRedirect('/');
        $this->assertAuthenticatedAs($admin, 'platform_admin');
    }

    // 3d. Guardian login
    public function test_normal_login_guardian(): void
    {
        $institute = Institute::firstOrFail();
        $plain = 'GuardianPass123!';
        $guardian = Guardian::create([
            'institute_id' => $institute->id,
            'name' => 'Guardian Test',
            'phone' => '01700001005',
            'email' => 'guardian-integrity@example.test',
            'password_hash' => $this->passwords->hash($plain),
            'status' => 'active',
        ]);

        $this->post('/guardian/login', ['email' => $guardian->email, 'password' => $plain])->assertRedirect('/guardian');
        $this->assertAuthenticatedAs($guardian, 'guardian');
    }

    // 4. Wrong password rejection
    public function test_wrong_password_rejection(): void
    {
        $plain = 'CorrectPass123!';
        $admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'admin-wrong@example.test',
            'password_hash' => $this->passwords->hash($plain),
            'status' => 'active',
        ]);

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'WrongPass123'])->assertSessionHasErrors('email');
        $this->assertGuest('platform_admin');

        $this->post('/login', ['email' => 'owner-integrity@example.test', 'password' => 'WrongPass123']);
        // ensure PasswordHash::safeCheck never throws on wrong
        $this->assertFalse(PasswordHash::safeCheck('WrongPass123', $admin->fresh()->getAuthPassword()));
    }

    // 5. Password change via canonical service
    public function test_password_change(): void
    {
        $user = app(UserAccountService::class)->registerOwner([
            'name' => 'Change User',
            'email' => 'change@example.test',
            'phone' => '01700001006',
            'password_hash' => 'OldPass123!',
            'status' => 'active',
        ]);

        $this->passwords->setForUser($user->fresh(), 'NewPass456!');
        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('NewPass456!', $fresh->getAuthPassword()));
        $this->assertFalse(Hash::check('OldPass123!', $fresh->getAuthPassword()));
    }

    // 6. Password reset via broker
    public function test_password_reset(): void
    {
        $user = app(UserAccountService::class)->registerOwner([
            'name' => 'Reset User',
            'email' => 'reset-integrity@example.test',
            'phone' => '01700001007',
            'password_hash' => 'OldPass123!',
            'status' => 'active',
        ]);

        $token = Password::broker('users')->createToken($user);
        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'ResetPass123!',
            'password_confirmation' => 'ResetPass123!',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('ResetPass123!', $user->fresh()->getAuthPassword()));
    }

    // 7. Admin password reset
    public function test_admin_password_reset(): void
    {
        $admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'admin-reset@example.test',
            'password_hash' => $this->passwords->hash('OldAdmin123!'),
            'status' => 'active',
        ]);

        $token = Password::broker('platform_admins')->createToken($admin);
        $this->post('/reset-password', [
            'token' => $token,
            'email' => $admin->email,
            'password' => 'NewAdmin123!',
            'password_confirmation' => 'NewAdmin123!',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NewAdmin123!', $admin->fresh()->getAuthPassword()));
    }

    // 8. All guards verified above (web, institute_user, platform_admin, guardian)

    // 9. Hash generated exactly once
    public function test_hash_generated_exactly_once(): void
    {
        $plain = 'OncePass123!';
        $hash = $this->passwords->hash($plain);
        $this->assertTrue(PasswordHash::looksValid($hash));
        $this->assertTrue(Hash::check($plain, $hash));

        // Simulate what happens if caller accidentally double-hashes: hash of hash should NOT verify plain
        $double = Hash::make($hash);
        $this->assertFalse(Hash::check($plain, $double));
        // And mutator would keep first hash, not double
        $user = User::create([
            'name' => 'Once User',
            'email' => 'once@example.test',
            'phone' => '01700001008',
            'password_hash' => $hash, // already hashed
            'status' => 'active',
            'account_type' => 'owner',
        ]);
        $this->assertSame($hash, $user->fresh()->getAuthPassword());
        $this->assertTrue(Hash::check($plain, $user->fresh()->getAuthPassword()));
    }

    // 10. Double-hashing regression: Hash::make + mutator must not double
    public function test_double_hashing_regression(): void
    {
        $plain = 'DoublePass123!';
        $preHashed = Hash::make($plain);

        // InstituteUser with pre-hashed via old path Hash::make -> should keep, not double
        $institute = Institute::firstOrFail();
        $iu = InstituteUser::create([
            'institute_id' => $institute->id,
            'role_id' => 1,
            'email' => 'double-iu@example.test',
            'phone' => '01700001009',
            'password_hash' => $preHashed,
            'status' => 'active',
        ]);
        $this->assertSame($preHashed, $iu->fresh()->getAuthPassword());
        $this->assertTrue(Hash::check($plain, $iu->fresh()->getAuthPassword()));

        // User with pre-hashed
        $u = User::create([
            'name' => 'Double User',
            'email' => 'double-user@example.test',
            'phone' => '01700001010',
            'password_hash' => $preHashed,
            'status' => 'active',
            'account_type' => 'owner',
        ]);
        $this->assertSame($preHashed, $u->fresh()->getAuthPassword());

        // Plain should hash once
        $u2 = User::create([
            'name' => 'Double Plain',
            'email' => 'double-plain@example.test',
            'phone' => '01700001011',
            'password_hash' => $plain,
            'status' => 'active',
            'account_type' => 'owner',
        ]);
        $this->assertNotSame($plain, $u2->fresh()->getAuthPassword());
        $this->assertTrue(Hash::check($plain, $u2->fresh()->getAuthPassword()));
    }

    // 11. Malformed hash detection
    public function test_malformed_hash_detection(): void
    {
        $this->assertSame(PasswordHash::STATUS_EMPTY, PasswordHash::classify(''));
        $this->assertSame(PasswordHash::STATUS_UNSUPPORTED, PasswordHash::classify('plaintext'));
        $this->assertSame(PasswordHash::STATUS_MALFORMED, PasswordHash::classify('$2y$12$short'));
        $this->assertSame(PasswordHash::STATUS_MALFORMED, PasswordHash::classify(substr(Hash::make('test'), 0, 59)));
        $this->assertSame(PasswordHash::STATUS_VALID, PasswordHash::classify(Hash::make('valid123')));

        // Login must be blocked for malformed, not 500
        $institute = Institute::firstOrFail();
        $malformed = substr(Hash::make('Malformed123'), 0, 59); // truncated to 59
        // Bypass mutator to force malformed into DB
        DB::table('institute_users')->insert([
            'institute_id' => $institute->id,
            'role_id' => 1,
            'email' => 'malformed@example.test',
            'phone' => '01700001012',
            'password_hash' => $malformed,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post('/institute/login', ['email' => 'malformed@example.test', 'password' => 'Malformed123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest('institute_user');
    }

    // 12. Valid hash is not modified by audit
    public function test_valid_hash_not_modified_by_audit(): void
    {
        $user = app(UserAccountService::class)->registerOwner([
            'name' => 'Audit User',
            'email' => 'audit-valid@example.test',
            'phone' => '01700001013',
            'password_hash' => 'AuditPass123!',
            'status' => 'active',
        ]);

        $before = $user->fresh()->getAuthPassword();
        $this->artisan('security:audit-password-hashes')->assertExitCode(0);
        $after = $user->fresh()->getAuthPassword();
        $this->assertSame($before, $after);
    }

    // 13. Tenant isolation: password change in one institute doesn't affect another
    public function test_tenant_isolation(): void
    {
        $i1 = Institute::firstOrFail();
        $i2 = Institute::where('id', '!=', $i1->id)->first();
        if (! $i2) {
            $this->markTestSkipped('Need at least 2 institutes for tenant isolation test');
        }

        $plain1 = 'Tenant1Pass123!';
        $plain2 = 'Tenant2Pass123!';

        $u1 = InstituteUser::create([
            'institute_id' => $i1->id,
            'role_id' => 1,
            'email' => 'tenant1@example.test',
            'phone' => '01700001014',
            'password_hash' => $this->passwords->hash($plain1),
            'status' => 'active',
        ]);

        $u2 = InstituteUser::create([
            'institute_id' => $i2->id,
            'role_id' => 1,
            'email' => 'tenant2@example.test',
            'phone' => '01700001015',
            'password_hash' => $this->passwords->hash($plain2),
            'status' => 'active',
        ]);

        // Change password for u1 only
        $this->passwords->setForUser($u1, 'Changed123!');

        $this->assertTrue(Hash::check('Changed123!', $u1->fresh()->getAuthPassword()));
        $this->assertTrue(Hash::check($plain2, $u2->fresh()->getAuthPassword()));
        $this->assertFalse(Hash::check($plain1, $u1->fresh()->getAuthPassword()));
    }

    // 14. Owner/Staff account architecture remains unchanged
    public function test_owner_staff_architecture_unchanged(): void
    {
        $owner = app(UserAccountService::class)->registerOwner([
            'name' => 'Arch Owner',
            'email' => 'arch-owner@example.test',
            'phone' => '01700001016',
            'password_hash' => 'ArchPass123!',
            'status' => 'active',
        ]);

        $staff = app(UserAccountService::class)->createStaffFromInvitation([
            'name' => 'Arch Staff',
            'email' => 'arch-staff@example.test',
            'phone' => '01700001017',
            'password_hash' => 'ArchPass123!',
            'status' => 'active',
        ]);

        $this->assertSame('owner', $owner->account_type);
        $this->assertSame('staff', $staff->account_type);
        $this->assertTrue($owner->isOwnerAccount());
        $this->assertTrue($staff->isStaffAccount());
    }
}
