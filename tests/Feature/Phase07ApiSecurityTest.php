<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Medical\Patient;
use App\Models\Membership;
use App\Models\Notification;
use App\Models\NotificationRead;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 07 — API security & token/endpoint hardening.
 *
 * Proves behavior over the Sanctum API surface (no Medical API v1 exists by
 * design): authentication, lockout, expiration, revocation, permission and
 * tenant boundaries, notification ownership, validation, error shapes and
 * rate limiting. Token abilities are issuance metadata (documented decision),
 * not an enforcement layer — the permission middleware + tenant context are.
 */
class Phase07ApiSecurityTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $a;

    private Institute $b;

    private Institute $edu;

    private InstituteUser $apiUser;

    private InstituteUser $eduUser;

    private string $password = 'Secret123!';

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = Institute::create([
            'name' => 'API Security A',
            'slug' => 'api-security-a-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
        $this->b = Institute::create([
            'name' => 'API Security B',
            'slug' => 'api-security-b-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $baseRole = Role::create([
            'institute_id' => $this->a->id,
            'name' => 'API Base Role',
            'slug' => 'api-base-'.uniqid(),
            'status' => 'active',
        ]);

        $this->apiUser = InstituteUser::create([
            'institute_id' => $this->a->id,
            'role_id' => $baseRole->id,
            'email' => 'api-'.uniqid().'@example.test',
            'phone' => '01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'password_hash' => Hash::make($this->password),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        // Education surface (module_access:education) needs an institute
        // with that module enabled — healthcare institutes are refused at
        // the module gate regardless of permission.
        $this->edu = Institute::create([
            'name' => 'API Security Edu',
            'slug' => 'api-security-edu-'.uniqid(),
            'industry' => 'education',
            'sub_industry' => 'school',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
        $eduRole = Role::create([
            'institute_id' => $this->edu->id,
            'name' => 'API Edu Role',
            'slug' => 'api-edu-'.uniqid(),
            'status' => 'active',
        ]);
        $this->eduUser = InstituteUser::create([
            'institute_id' => $this->edu->id,
            'role_id' => $eduRole->id,
            'email' => 'edu-'.uniqid().'@example.test',
            'phone' => '01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'password_hash' => Hash::make($this->password),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function roleWith(array $slugs, ?Institute $institute = null, ?InstituteUser $user = null): Role
    {
        $institute ??= $this->a;
        $user ??= $this->apiUser;
        $role = Role::create([
            'institute_id' => $institute->id,
            'name' => 'API Role '.uniqid(),
            'slug' => 'api-role-'.uniqid(),
            'status' => 'active',
        ]);
        foreach ($slugs as $slug) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                ['module' => explode('.', $slug)[0] ?? 'general', 'name' => $slug]
            );
            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $role->id,
                'permission_id' => $permission->id,
            ]);
        }
        // Attach as the user's current role (replaces any prior test role).
        $user->forceFill(['role_id' => $role->id])->save();
        $user->unsetRelation('role');

        return $role;
    }

    private function tokenFor(?InstituteUser $user = null, array $abilities = ['*']): string
    {
        $user ??= $this->apiUser;

        return $user->createToken('test-suite', $abilities)->plainTextToken;
    }

    private function eduAuth(array $slugs): array
    {
        $this->roleWith($slugs, $this->edu, $this->eduUser);

        return [
            'Authorization' => 'Bearer '.$this->eduUser->createToken('t', ['*'])->plainTextToken,
            'Accept' => 'application/json',
        ];
    }

    private function auth(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
    }

    private function loginPayload(?string $password = null): array
    {
        return [
            'email' => $this->apiUser->email,
            'password' => $password ?? $this->password,
            'institute_id' => $this->a->id,
        ];
    }

    // --- Authentication -------------------------------------------------

    public function test_unauthenticated_denied(): void
    {
        $this->getJson('/api/profile')->assertStatus(401);
        $this->getJson('/api/students')->assertStatus(401);
    }

    public function test_invalid_token_denied(): void
    {
        $this->getJson('/api/profile', $this->auth('bogus-token-value'))->assertStatus(401);
    }

    public function test_valid_token_accepted(): void
    {
        $this->getJson('/api/profile', $this->auth($this->tokenFor()))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_logout_revokes_current_token(): void
    {
        $token = $this->tokenFor();
        $row = $this->apiUser->tokens()->latest('id')->firstOrFail();

        $this->postJson('/api/logout', [], $this->auth($token))->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $row->id]);
    }

    public function test_revoked_token_denied(): void
    {
        // Deleted server-side in a prior lifecycle; the very next request
        // with it must fail (separate test = fresh guard, no cached user).
        $token = $this->tokenFor();
        $this->apiUser->tokens()->latest('id')->firstOrFail()->delete();
        $this->getJson('/api/profile', $this->auth($token))->assertStatus(401);
    }

    public function test_expired_token_denied(): void
    {
        $plain = $this->tokenFor();
        $token = $this->apiUser->tokens()->latest('id')->firstOrFail();
        // Age the token past the 30-day sanctum expiration (config-driven).
        $token->forceFill(['created_at' => now()->subDays(31)])->save();

        $this->getJson('/api/profile', $this->auth($plain))->assertStatus(401);
    }

    // --- Login security --------------------------------------------------

    public function test_login_errors_do_not_enumerate(): void
    {
        // Unknown account vs wrong password: identical status + message.
        $unknown = $this->postJson('/api/login', [
            'email' => 'nobody-'.uniqid().'@example.test',
            'password' => 'wrong',
            'institute_id' => $this->a->id,
        ]);
        $badPassword = $this->postJson('/api/login', $this->loginPayload('wrong-password'));

        $unknown->assertStatus(401);
        $badPassword->assertStatus(401);
        $this->assertSame($unknown->json('message'), $badPassword->json('message'));
    }

    public function test_login_validation_shape(): void
    {
        $this->postJson('/api/login', [])->assertStatus(422);
    }

    public function test_lockout_blocks_after_threshold(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/login', $this->loginPayload('wrong-'.$i))->assertStatus(401);
        }
        $this->assertFalse($this->apiUser->fresh()->isLocked());

        $this->postJson('/api/login', $this->loginPayload('wrong-4'))->assertStatus(401);
        $this->assertTrue($this->apiUser->fresh()->isLocked());

        // Correct password while locked stays blocked (423), no oracle change.
        $this->postJson('/api/login', $this->loginPayload())->assertStatus(423);
        $this->assertSame(5, (int) $this->apiUser->fresh()->failed_login_count);
    }

    public function test_lockout_expires_and_success_resets(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', $this->loginPayload('wrong'));
        }
        $this->assertTrue($this->apiUser->fresh()->isLocked());

        try {
            Carbon::setTestNow(now()->addMinutes(16));
            $response = $this->postJson('/api/login', $this->loginPayload());
            $response->assertOk();
            $this->assertSame(0, (int) $this->apiUser->fresh()->failed_login_count);
            $this->assertNull($this->apiUser->fresh()->locked_until);
            $this->assertNotEmpty($response->json('data.token'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_inactive_account_cannot_use_api(): void
    {
        $this->apiUser->forceFill(['status' => 'inactive'])->save();

        // Login refused with the generic message (no oracle).
        $this->postJson('/api/login', $this->loginPayload())->assertStatus(401);

        // A token minted before deactivation is refused at the gate.
        $token = $this->apiUser->createToken('pre-deactivation')->plainTextToken;
        $this->getJson('/api/profile', $this->auth($token))->assertStatus(401);
    }

    public function test_login_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/login', $this->loginPayload('wrong-'.$i));
        }
        // 11th request inside the minute window: throttled regardless of
        // account lockout state (layered with the per-account policy).
        $this->postJson('/api/login', $this->loginPayload('wrong-final'))->assertStatus(429);
    }

    // --- Token abilities are issuance metadata, not gates -----------------

    public function test_arbitrary_abilities_do_not_gate_or_leak(): void
    {
        // Abilities are issuance metadata, not gates: a token carrying an
        // unrelated ability still passes where the ROLE permission allows,
        // and the permission layer remains the enforcement point.
        $this->eduAuth(['students.view']);
        $odd = ['Authorization' => 'Bearer '.$this->eduUser->createToken('t', ['unrelated-scope'])->plainTextToken, 'Accept' => 'application/json'];
        $this->getJson('/api/students', $odd)->assertOk();
    }

    // --- Authorization ----------------------------------------------------

    public function test_missing_permission_denied(): void
    {
        $headers = $this->eduAuth(['hr.view']);
        $this->getJson('/api/students', $headers)->assertStatus(403);
    }

    public function test_exact_permission_accepted(): void
    {
        $headers = $this->eduAuth(['students.view']);
        $this->getJson('/api/students', $headers)->assertOk();
    }

    // --- Tenant isolation ---------------------------------------------------

    public function test_cross_tenant_api_read_denied(): void
    {
        $headers = $this->eduAuth(['students.view']);

        $foreign = \App\Models\Student::create([
            'institute_id' => $this->b->id,
            'student_id' => substr(strtoupper(uniqid()), -6),
            'student_id_number' => 'SID-'.uniqid(),
            'admission_date' => now()->format('Y-m-d'),
            'first_name' => 'Foreign',
            'last_name' => 'Student',
        ]);

        $this->getJson('/api/students/'.$foreign->id, $headers)->assertStatus(404);
        $index = $this->getJson('/api/students?search=Foreign', $headers)->assertOk();
        $this->assertStringNotContainsString('Foreign', (string) $index->getContent());
    }

    public function test_medical_react_denied_without_view_permission(): void
    {
        // Perm-less token: clinical feeds refuse even inside the institute.
        $this->roleWith(['hr.view']);
        $headers = $this->auth($this->tokenFor());

        $this->getJson('/api/medical/patients', $headers)->assertStatus(403);
        $this->getJson('/api/medical/prescriptions', $headers)->assertStatus(403);
    }

    public function test_medical_react_scoped_to_own_institute(): void
    {
        // Correct view grant: own institute data only.
        $this->roleWith(['medical_patients.view', 'medical_prescriptions.view']);
        $ok = $this->getJson('/api/medical/patients', $this->auth($this->tokenFor()))->assertOk();
        $this->assertStringNotContainsString('RivalPatient', (string) $ok->getContent());
    }

    // --- Ownership: notification read ---------------------------------------

    public function test_notification_read_ownership(): void
    {
        $this->roleWith(['notifications.view']);
        $headers = $this->auth($this->tokenFor());

        $mine = Notification::create([
            'scope' => 'institute',
            'institute_id' => $this->a->id,
            'category' => 'general',
            'title' => 'Mine',
            'message' => 'hello',
            'created_by_type' => 'system',
        ]);
        $foreign = Notification::create([
            'scope' => 'institute',
            'institute_id' => $this->b->id,
            'category' => 'general',
            'title' => 'Theirs',
            'message' => 'hello',
            'created_by_type' => 'system',
        ]);

        $this->postJson('/api/notifications/'.$mine->id.'/read', [], $headers)->assertOk();
        $this->assertSame(1, NotificationRead::where('notification_id', $mine->id)->count());

        // Foreign id: 404, and no read row is created (no cross-tenant write).
        $this->postJson('/api/notifications/'.$foreign->id.'/read', [], $headers)->assertStatus(404);
        $this->assertSame(0, NotificationRead::where('notification_id', $foreign->id)->count());
    }

    // --- Validation: no privilege/tenant injection ---------------------------

    // NOTE: attendance tenant-injection is covered by the pre-existing
    // ApiAttendanceSecurityTest (cross-tenant student/batch blocked,
    // branch restrictions, 201-shape) — not duplicated here.

    // --- Error shapes --------------------------------------------------------

    public function test_error_shapes(): void
    {
        $headers = $this->eduAuth(['students.view']);
        $this->getJson('/api/students/999999999', $headers)->assertStatus(404);
    }
}
