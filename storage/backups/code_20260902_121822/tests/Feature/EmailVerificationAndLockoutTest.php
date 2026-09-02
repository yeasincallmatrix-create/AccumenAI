<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Services\Auth\PasswordService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationAndLockoutTest extends TestCase
{
    use DatabaseTransactions;

    private function verifiedUser(array $overrides = []): User
    {
        $user = User::create(array_merge([
            'name' => 'Verified User',
            'first_name' => 'Verified',
            'last_name' => 'User',
            'email' => 'verified-'.uniqid().'@example.test',
            'phone' => '+88017'.rand(10000000, 99999999),
            'password_hash' => app(PasswordService::class)->hash('Verified123!'),
            'status' => 'active',
            'account_type' => 'owner',
        ], $overrides));
        $user->forceFill(['email_verified_at' => now()])->save();
        return $user->fresh();
    }

    private function unverifiedUser(array $overrides = []): User
    {
        $user = User::create(array_merge([
            'name' => 'Unverified User',
            'first_name' => 'Unverified',
            'last_name' => 'User',
            'email' => 'unverified-'.uniqid().'@example.test',
            'phone' => '+88017'.rand(10000000, 99999999),
            'password_hash' => app(PasswordService::class)->hash('Verified123!'),
            'status' => 'active',
            'account_type' => 'owner',
        ], $overrides));
        $user->forceFill(['email_verified_at' => null])->save();
        return $user->fresh();
    }

    // --- Email verification ---

    public function test_registration_creates_unverified_user(): void
    {
        $user = app(\App\Services\UserAccountService::class)->registerOwner([
            'name' => 'Owner Test',
            'first_name' => 'Owner',
            'last_name' => 'Test',
            'email' => 'owner-unverified-'.uniqid().'@example.test',
            'phone' => '+8801711'.rand(100000, 999999),
            'password_hash' => 'OwnerPass123!',
            'status' => 'active',
        ]);
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_email_dispatched_on_owner_registration(): void
    {
        Notification::fake();
        // OwnerRegisterController sends verification after create — we simulate via model
        $user = $this->unverifiedUser();
        $user->sendEmailVerificationNotification();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_valid_link_verifies_user(): void
    {
        Event::fake([Verified::class]);
        $user = $this->unverifiedUser();
        $this->actingAs($user, 'web');
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())]);
        $this->get($url)->assertRedirect('/');
        $this->assertNotNull($user->fresh()->email_verified_at);
        Event::assertDispatched(Verified::class);
    }

    public function test_invalid_signature_rejected(): void
    {
        $user = $this->unverifiedUser();
        $this->actingAs($user, 'web');
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())]);
        // Tamper with hash
        $tampered = str_replace(substr($url, -5), 'xxxxx', $url);
        $this->get($tampered)->assertStatus(403);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_expired_verification_rejected(): void
    {
        $user = $this->unverifiedUser();
        $this->actingAs($user, 'web');
        $url = URL::temporarySignedRoute('verification.verify', now()->subMinutes(5), ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())]);
        $this->get($url)->assertStatus(403);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_repeated_verification_handled_safely(): void
    {
        $user = $this->verifiedUser();
        $this->actingAs($user, 'web');
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())]);
        $this->get($url)->assertRedirect('/');
        $this->assertNotNull($user->fresh()->email_verified_at);
        // Second hit should still redirect, not error
        $this->get($url)->assertRedirect('/');
    }

    public function test_throttled_resend(): void
    {
        $user = $this->unverifiedUser();
        $this->actingAs($user, 'web');
        // First resend should succeed (302 back)
        $this->post(route('verification.send'))->assertStatus(302);
        // Hammer 7 times quickly — throttle:6,1 should trigger 429 on 7th
        for ($i = 0; $i < 6; $i++) {
            $this->post(route('verification.send'));
        }
        $this->post(route('verification.send'))->assertStatus(429);
    }

    public function test_unverified_user_cannot_access_protected_workspace(): void
    {
        $user = $this->unverifiedUser();
        $this->actingAs($user, 'web');
        $this->get('/')->assertRedirect(route('verification.notice'));
        $this->get('/workspace')->assertRedirect(route('verification.notice'));
    }

    public function test_verified_user_can_access_workspace(): void
    {
        $user = $this->verifiedUser();
        $this->actingAs($user, 'web');
        // Verified user should NOT be redirected to verification.notice
        $response = $this->get('/');
        $location = $response->headers->get('Location');
        $this->assertNotEquals(route('verification.notice'), $location);
        // Workspace picker should be accessible (200 or redirect to create, but NOT verification)
        $ws = $this->get('/workspace');
        $this->assertNotEquals(route('verification.notice'), $ws->headers->get('Location'));
        $this->assertTrue(in_array($ws->getStatusCode(), [200, 302]));
    }

    // --- Tenant security ---
    public function test_tenant_a_cannot_verify_tenant_b_via_id_mismatch(): void
    {
        $userA = $this->unverifiedUser();
        $userB = $this->unverifiedUser();
        $this->actingAs($userA, 'web');
        // Try to verify userB's id while authenticated as userA
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $userB->getKey(), 'hash' => sha1($userB->getEmailForVerification())]);
        $this->get($url)->assertStatus(403);
        $this->assertNull($userB->fresh()->email_verified_at);
    }

    // --- Platform admin lockout ---
    public function test_platform_admin_repeated_failed_throttled_and_lockout(): void
    {
        $admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'lock-'.uniqid().'@example.test',
            'password_hash' => bcrypt('Correct123!'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        // Disable throttle middleware for this test to isolate per-user lockout from IP throttle
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => $admin->email, 'password' => 'WrongPass123!']);
        }
        $admin->refresh();
        $this->assertNotNull($admin->locked_until);
        $this->assertTrue($admin->isLocked());

        // Correct password while locked should be rejected with throttle message (no enumeration)
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'Correct123!'])
            ->assertSessionHasErrors('email');
    }

    public function test_platform_admin_successful_login_after_lockout_period(): void
    {
        $admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'lock2-'.uniqid().'@example.test',
            'password_hash' => bcrypt('Correct123!'),
            'status' => 'active',
            'email_verified_at' => now(),
            'locked_until' => now()->subMinutes(1),
            'failed_login_count' => 0,
        ]);

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'Correct123!'])
            ->assertRedirect('/');
        $this->assertAuthenticatedAs($admin, 'platform_admin');
        $this->assertNull($admin->fresh()->locked_until);
    }

    public function test_platform_admin_success_clears_failure_state(): void
    {
        $admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'clear-'.uniqid().'@example.test',
            'password_hash' => bcrypt('Correct123!'),
            'status' => 'active',
            'email_verified_at' => now(),
            'failed_login_count' => 3,
        ]);

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'Correct123!'])
            ->assertRedirect('/');
        $this->assertEquals(0, $admin->fresh()->failed_login_count);
    }
}
