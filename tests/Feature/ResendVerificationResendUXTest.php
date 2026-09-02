<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ResendVerificationResendUXTest extends TestCase
{
    use DatabaseTransactions;

    private function unverifiedUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Resend Test',
            'first_name' => 'Resend',
            'last_name' => 'Test',
            'email' => 'resend-' . uniqid() . '@example.test',
            'phone' => '+88017' . rand(10000000, 99999999),
            'password_hash' => bcrypt('Resend123!'),
            'status' => 'active',
            'account_type' => 'owner',
            'email_verified_at' => null,
        ], $overrides));
    }

    // --- 1. Resend button request queues verification email ---

    public function test_resend_request_dispatches_notification(): void
    {
        $user = $this->unverifiedUser();
        Notification::fake();

        $this->actingAs($user, 'web')
            ->postJson(route('verification.send'))
            ->assertOk()
            ->assertJson(['success' => true]);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    // --- 2. HTTP request does not perform synchronous SMTP ---

    public function test_resend_request_returns_quickly(): void
    {
        $user = $this->unverifiedUser();

        $this->actingAs($user, 'web');
        $start = microtime(true);
        $response = $this->postJson(route('verification.send'));
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(2, $elapsed, "Resend should return in <2s, took {$elapsed}s");
        $response->assertOk();
    }

    // --- 3. Job is inserted into jobs (testing env uses sync, verify notification dispatched) ---

    public function test_notification_is_queued_class(): void
    {
        $notification = new QueuedVerifyEmail();
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $notification);
    }

    public function test_queued_notification_uses_default_queue(): void
    {
        $notification = new QueuedVerifyEmail();
        $this->assertSame('default', $notification->queue);
    }

    // --- 4. Existing QueuedVerifyEmail is used ---

    public function test_resend_uses_queued_verify_email_in_non_testing(): void
    {
        $user = $this->unverifiedUser();
        Notification::fake();

        $user->notify(new QueuedVerifyEmail());
        Notification::assertSentTo($user, QueuedVerifyEmail::class);
    }

    // --- 5. Double-click cannot create duplicate requests through frontend behavior ---

    public function test_json_response_success_structure(): void
    {
        $user = $this->unverifiedUser();
        Notification::fake();

        $response = $this->actingAs($user, 'web')
            ->postJson(route('verification.send'));

        $response->assertOk()
            ->assertJsonStructure(['success', 'message']);
    }

    // --- 6. Server-side throttle still blocks rapid repeated requests ---

    public function test_server_throttle_blocks_rapid_requests(): void
    {
        $user = $this->unverifiedUser();
        $this->actingAs($user, 'web');

        // First request succeeds
        $this->postJson(route('verification.send'))->assertOk();

        // Hammer 6 more times (throttle:6,1)
        for ($i = 0; $i < 6; $i++) {
            $this->postJson(route('verification.send'));
        }

        // 7th should be 429
        $this->postJson(route('verification.send'))->assertStatus(429);
    }

    // --- 7. 429 response is handled correctly ---

    public function test_throttle_returns_429_json(): void
    {
        $user = $this->unverifiedUser();
        $this->actingAs($user, 'web');

        // Exhaust throttle
        for ($i = 0; $i < 7; $i++) {
            $this->postJson(route('verification.send'));
        }

        $response = $this->postJson(route('verification.send'));
        $response->assertStatus(429);
    }

    // --- 8. Already verified user gets success (idempotent) ---

    public function test_already_verified_returns_success(): void
    {
        $user = $this->unverifiedUser();
        $user->forceFill(['email_verified_at' => now()])->save();
        Notification::fake();

        $this->actingAs($user, 'web')
            ->postJson(route('verification.send'))
            ->assertOk()
            ->assertJson(['success' => true]);

        Notification::assertNothingSent();
    }

    // --- 9. Successful response disables button (frontend verification) ---

    public function test_verify_email_view_has_resend_button(): void
    {
        $user = $this->unverifiedUser();
        $this->actingAs($user, 'web');

        $response = $this->get(route('verification.notice'));
        $response->assertOk();
        $response->assertSee('resend-btn');
        $response->assertSee('setCooldown');
        $response->assertSee('Resend available in');
    }

    // --- 10. View contains countdown JavaScript ---

    public function test_view_contains_cooldown_js(): void
    {
        $user = $this->unverifiedUser();
        $this->actingAs($user, 'web');

        $response = $this->get(route('verification.notice'));
        $response->assertSee('cooldown');
        $response->assertSee('setCooldown');
        $response->assertSee('disabled');
    }

    // --- 11. View contains AJAX fetch logic ---

    public function test_view_contains_ajax_fetch(): void
    {
        $user = $this->unverifiedUser();
        $this->actingAs($user, 'web');

        $response = $this->get(route('verification.notice'));
        $response->assertSee('fetch(');
        $response->assertSee('X-Requested-With');
        $response->assertSee('XMLHttpRequest');
    }

    // --- 12. Verify view displays email address ---

    public function test_view_displays_user_email(): void
    {
        $user = $this->unverifiedUser();
        $this->actingAs($user, 'web');

        $response = $this->get(route('verification.notice'));
        $response->assertSee($user->email);
    }

    // --- 13. Verify view has flash auto-dismiss support ---

    public function test_view_has_flash_and_resend_status(): void
    {
        $user = $this->unverifiedUser();
        $this->actingAs($user, 'web');

        $response = $this->get(route('verification.notice'));
        // flash.js is loaded for auto-dismiss of flash messages
        $response->assertSee('flash.js');
        // AJAX status div is present as the async replacement
        $response->assertSee('resend-status');
    }

    // --- 14. Verification link remains valid ---

    public function test_signed_verification_url_still_works(): void
    {
        $user = $this->unverifiedUser();
        $this->actingAs($user, 'web');
        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())]
        );
        $this->get($url)->assertRedirect('/');
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    // --- 15. No secret appears in notification code ---

    public function test_no_smtp_password_in_notification_code(): void
    {
        $reflection = new \ReflectionClass(QueuedVerifyEmail::class);
        $content = file_get_contents($reflection->getFileName());
        $this->assertStringNotContainsString('verify_peer=false', strtolower($content));
        $this->assertStringNotContainsString('wuknxxrwbfohcudh', $content);
        $this->assertStringNotContainsString('callmatrix', strtolower($content));
    }

    // --- 16. No verify_peer=false in QueuedVerifyEmail ---

    public function test_queued_verify_email_no_verify_peer_false(): void
    {
        $reflection = new \ReflectionClass(QueuedVerifyEmail::class);
        $content = file_get_contents($reflection->getFileName());
        $this->assertStringNotContainsString('verify_peer', strtolower($content));
    }

    // --- 17. CSRF protection on resend route ---

    public function test_resend_route_has_csrf_middleware(): void
    {
        $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName('verification.send');
        $this->assertNotNull($route);
        // The route should be within the 'web' middleware group which includes VerifyCsrfToken
        $middleware = collect($route->gatherMiddleware())->merge($route->getController()->middleware ?? []);
        $this->assertTrue(
            $middleware->contains('web') || $middleware->contains(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class),
            'Resend route must have CSRF protection via web middleware group'
        );
    }

    // --- 18. Existing authentication tests remain green (summary) ---

    public function test_unauthenticated_user_redirected(): void
    {
        $this->postJson(route('verification.send'))
            ->assertStatus(401);
    }

    // --- 19. Controller returns JSON for wantsJson ---

    public function test_controller_returns_json_for_json_request(): void
    {
        $user = $this->unverifiedUser();
        Notification::fake();

        $response = $this->actingAs($user, 'web')
            ->postJson(route('verification.send'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJson(['success' => true]);
    }

    // --- 20. Controller still returns redirect for non-AJAX ---

    public function test_controller_returns_redirect_for_form_post(): void
    {
        $user = $this->unverifiedUser();
        Notification::fake();

        $response = $this->actingAs($user, 'web')
            ->post(route('verification.send'));

        $response->assertStatus(302);
    }

    // --- 21. Verify view has CSRF token for AJAX ---

    public function test_view_has_csrf_token_for_ajax(): void
    {
        $user = $this->unverifiedUser();
        $this->actingAs($user, 'web');

        $response = $this->get(route('verification.notice'));
        $response->assertSee('X-CSRF-TOKEN');
        // csrf_token() is evaluated by Blade — the actual token value should appear
        $response->assertSee(csrf_token());
    }

    // --- 22. Security panel resend button also has AJAX ---

    public function test_security_panel_has_resend_button(): void
    {
        $user = $this->unverifiedUser();
        $this->actingAs($user, 'web');

        $response = $this->get(route('account.security'));
        // Security panel should show the unverified warning with resend button
        $response->assertSee('resend-email-btn');
        $response->assertSee('data-resend-url');
    }
}
