<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LoginCaptchaTest extends TestCase
{
    use DatabaseTransactions;

    protected function enableCaptcha(string $version = '3'): void
    {
        config([
            'services.recaptcha.version' => $version,
            'services.recaptcha.site_key' => 'test-site-key',
            'services.recaptcha.secret_key' => 'test-secret-key',
        ]);
    }

    public function test_login_form_renders_invisible_v3_captcha_when_configured(): void
    {
        $this->enableCaptcha('3');

        $this->get('/login')
            ->assertOk()
            ->assertSee('name="g-recaptcha-response"', false)
            ->assertSee('recaptcha/api.js?render=test-site-key', false)
            ->assertDontSee('g-recaptcha" data-sitekey', false);
    }

    public function test_login_form_renders_checkbox_widget_for_v2_keys(): void
    {
        $this->enableCaptcha('2');

        $this->get('/login')
            ->assertOk()
            ->assertSee('g-recaptcha" data-sitekey="test-site-key"', false)
            ->assertSee('recaptcha/api.js"', false);
    }

    public function test_login_form_renders_captcha_when_configured(): void
    {
        $this->enableCaptcha();

        $this->get('/login')
            ->assertOk()
            ->assertSee('g-recaptcha', false)
            ->assertSee('recaptcha/api.js', false);
    }

    public function test_login_form_omits_captcha_when_not_configured(): void
    {
        config([
            'services.recaptcha.site_key' => null,
            'services.recaptcha.secret_key' => null,
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertDontSee('g-recaptcha', false);
    }

    public function test_login_without_captcha_token_is_rejected(): void
    {
        $this->enableCaptcha();

        $this->post('/login', [
            'email' => 'captcha-missing@example.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('captcha');

        $this->assertGuest('web');
    }

    public function test_login_with_captcha_token_rejected_by_google_is_rejected(): void
    {
        $this->enableCaptcha();

        Http::fake(['*' => Http::response(['success' => false], 200)]);

        $this->post('/login', [
            'email' => 'captcha-rejected@example.test',
            'password' => 'wrong-password',
            'g-recaptcha-response' => 'invalid-token',
        ])->assertSessionHasErrors('captcha');

        $this->assertGuest('web');

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'siteverify')
                && $request['response'] === 'invalid-token';
        });
    }

    public function test_login_with_accepted_captcha_token_reaches_credential_check(): void
    {
        $this->enableCaptcha();

        Http::fake(['*' => Http::response(['success' => true, 'score' => 0.9, 'action' => 'auth'], 200)]);

        $this->post('/login', [
            'email' => 'captcha-passed@example.test',
            'password' => 'wrong-password',
            'g-recaptcha-response' => 'valid-token',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('web');
    }

    public function test_login_with_low_v3_score_is_rejected(): void
    {
        $this->enableCaptcha('3');

        Http::fake(['*' => Http::response(['success' => true, 'score' => 0.1, 'action' => 'auth'], 200)]);

        $this->post('/login', [
            'email' => 'captcha-botscore@example.test',
            'password' => 'wrong-password',
            'g-recaptcha-response' => 'bot-token',
        ])->assertSessionHasErrors('captcha');

        $this->assertGuest('web');
    }

    public function test_login_with_accepted_token_is_not_score_checked_for_v2_keys(): void
    {
        $this->enableCaptcha('2');

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $this->post('/login', [
            'email' => 'captcha-v2-pass@example.test',
            'password' => 'wrong-password',
            'g-recaptcha-response' => 'valid-token',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('web');
    }

    public function test_admin_login_without_captcha_token_is_rejected(): void
    {
        $this->enableCaptcha();

        $this->post('/admin/login', [
            'email' => 'admin@example.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('captcha');

        $this->assertGuest('platform_admin');
    }

    public function test_protected_guest_forms_render_captcha_when_configured(): void
    {
        $this->enableCaptcha();

        foreach (['/register/account', '/forgot-password', '/forgot-password/phone', '/guardian/forgot-password'] as $uri) {
            $this->get($uri)
                ->assertOk()
                ->assertSee('g-recaptcha', false);
        }
    }

    public function test_register_account_without_captcha_token_is_rejected(): void
    {
        $this->enableCaptcha();

        $this->post('/register/account', [
            'email' => 'captcha-register@example.test',
            'password' => 'Sup3rSecret!Pass',
            'password_confirmation' => 'Sup3rSecret!Pass',
        ])->assertSessionHasErrors('captcha');
    }

    public function test_forgot_password_without_captcha_token_is_rejected(): void
    {
        $this->enableCaptcha();

        $this->post('/forgot-password', [
            'email' => 'captcha-reset@example.test',
        ])->assertSessionHasErrors('captcha');
    }

    public function test_phone_forgot_password_without_captcha_token_is_rejected(): void
    {
        $this->enableCaptcha();

        $this->post('/forgot-password/phone', [
            'phone' => '01712345678',
        ])->assertSessionHasErrors('captcha');
    }

    public function test_guardian_forgot_password_without_captcha_token_is_rejected(): void
    {
        $this->enableCaptcha();

        $this->post('/guardian/forgot-password', [
            'email' => 'captcha-guardian@example.test',
        ])->assertSessionHasErrors('captcha');
    }

    public function test_forgot_password_with_accepted_captcha_token_proceeds(): void
    {
        $this->enableCaptcha();

        Http::fake(['*' => Http::response(['success' => true, 'score' => 0.9, 'action' => 'auth'], 200)]);

        $this->from('/forgot-password')
            ->post('/forgot-password', [
                'email' => 'captcha-reset-ok@example.test',
                'g-recaptcha-response' => 'valid-token',
            ])
            ->assertRedirect('/forgot-password')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');
    }
}
