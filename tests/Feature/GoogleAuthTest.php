<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    public function test_redirect_route_exists()
    {
        $this->assertTrue(Route::has('auth.google.redirect'));
    }

    public function test_callback_route_exists()
    {
        $this->assertTrue(Route::has('auth.google.callback'));
    }

    public function test_redirect_sends_to_google()
    {
        $provider = \Mockery::mock();
        $provider->shouldReceive('scopes')->andReturnSelf();
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get(route('auth.google.redirect'));
        $response->assertRedirect();
        $this->assertStringContainsString('accounts.google.com', $response->headers->get('Location'));
    }

    public function test_callback_creates_new_user()
    {
        $email = 'newgoogle_'.uniqid().'@example.com';
        $this->mockGoogleUser($email, '123456789'.random_int(1000, 9999));

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('register.organization'));
        $this->assertDatabaseHas('users', [
            'email' => $email,
        ]);
    }

    public function test_new_google_user_gets_verified_pending_for_onboarding()
    {
        $email = 'firsttime_'.uniqid().'@example.com';
        $this->mockGoogleUser($email, '777888'.random_int(1000, 9999));

        $this->get(route('auth.google.callback'));

        $this->assertDatabaseHas('pending_registrations', [
            'email' => $email,
        ]);
        $pending = \App\Models\PendingRegistration::where('email', $email)->first();
        $this->assertNotNull($pending);
        $this->assertNotNull($pending->verified_at);
    }

    public function test_new_google_user_can_open_organization_step()
    {
        $email = 'orgstep_'.uniqid().'@example.com';
        $this->mockGoogleUser($email, '333444'.random_int(1000, 9999));

        $this->get(route('auth.google.callback'));

        $response = $this->get(route('register.organization'));
        $response->assertOk();
    }

    public function test_callback_links_existing_user()
    {
        $email = 'existinggoogle_'.uniqid().'@example.com';
        $googleId = '999888'.random_int(1000, 9999);
        $user = User::factory()->create([
            'email' => $email,
            'google_id' => null,
        ]);

        $this->mockGoogleUser($email, $googleId);

        $this->get(route('auth.google.callback'));

        $this->assertEquals($googleId, $user->fresh()->google_id);
    }

    public function test_callback_logs_user_in()
    {
        $this->mockGoogleUser('logintest_'.uniqid().'@example.com', '555444'.random_int(1000, 9999));

        $this->get(route('auth.google.callback'));

        $this->assertAuthenticated('web');
    }

    public function test_existing_google_user_goes_to_dashboard()
    {
        $email = 'returning_'.uniqid().'@example.com';
        $user = User::factory()->create([
            'email' => $email,
            'google_id' => null,
        ]);
        $this->giveActiveMembership($user);

        $this->mockGoogleUser($email, '111222'.random_int(1000, 9999));

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_user_with_no_institution_goes_to_organization()
    {
        $email = 'noinstitute_'.uniqid().'@example.com';
        User::factory()->create([
            'email' => $email,
            'google_id' => null,
        ]);

        $this->mockGoogleUser($email, '999000'.random_int(1000, 9999));

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('register.organization'));
        $pending = \App\Models\PendingRegistration::where('email', $email)->first();
        $this->assertNotNull($pending);
        $this->assertNotNull($pending->verified_at);
    }

    public function test_google_button_shows_on_login_page()
    {
        $response = $this->get(route('login'));
        $response->assertOk();
        $response->assertSee('Continue with Google');
        $response->assertSee(route('auth.google.redirect'), false);
    }

    private function giveActiveMembership(User $user): void
    {
        $institute = \App\Models\Institute::query()->firstOrFail();
        $role = \App\Models\Role::query()
            ->where('slug', 'institute-owner')
            ->whereNull('institute_id')
            ->firstOrFail();

        \App\Models\Membership::create([
            'user_id' => $user->id,
            'institution_id' => $institute->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }

    private function mockGoogleUser(string $email, string $id): void
    {
        $socialiteUser = (new SocialiteUser)->setRaw([])->map([
            'id' => $id,
            'name' => 'Test Google User',
            'email' => $email,
            'avatar' => 'https://example.com/avatar.jpg',
        ]);

        $provider = \Mockery::mock();
        $provider->shouldReceive('user')->andReturn($socialiteUser);
        $provider->shouldReceive('scopes')->andReturnSelf();
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}
