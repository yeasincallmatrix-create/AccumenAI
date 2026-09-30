<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\EmailBanService;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class EmailBanTest extends TestCase
{
    private EmailBanService $banService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->banService = app(EmailBanService::class);
    }

    public function test_ban_creates_record()
    {
        $email = 'ban-'.uniqid().'@example.com';
        $this->banService->ban($email, 'Test reason');

        $this->assertDatabaseHas('banned_emails', [
            'email' => strtolower($email),
            'reason' => 'Test reason',
            'is_permanent' => true,
        ]);
    }

    public function test_is_banned_returns_true()
    {
        $email = 'ban-'.uniqid().'@example.com';
        $this->banService->ban($email, 'Test');
        $this->assertTrue($this->banService->isBanned($email));
    }

    public function test_case_insensitive_matching()
    {
        $email = 'Ban-'.uniqid().'@Example.com';
        $this->banService->ban($email, 'Test');

        $this->assertTrue($this->banService->isBanned(strtolower($email)));
        $this->assertTrue($this->banService->isBanned(strtoupper($email)));
    }

    public function test_unban_removes_record()
    {
        $email = 'ban-'.uniqid().'@example.com';
        $this->banService->ban($email, 'Test');
        $this->assertTrue($this->banService->isBanned($email));

        $this->banService->unban($email);
        $this->assertFalse($this->banService->isBanned($email));
    }

    public function test_expired_ban_not_active()
    {
        $email = 'ban-'.uniqid().'@example.com';
        $this->banService->ban($email, 'Test', null, false, now()->subDay());
        $this->assertFalse($this->banService->isBanned($email));
    }

    public function test_ban_survives_user_hard_delete()
    {
        $email = 'ban-'.uniqid().'@example.com';

        $user = User::factory()->create(['email' => $email]);
        $this->banService->ban($email, 'Test');
        $user->forceDelete();

        $this->assertEquals(0, User::withTrashed()->where('email', $email)->count());
        $this->assertTrue($this->banService->isBanned($email));
    }

    public function test_google_oauth_blocks_banned_email()
    {
        $email = 'ban-'.uniqid().'@example.com';
        $this->banService->ban($email, 'Test');

        $socialiteUser = (new SocialiteUser)->setRaw([])->map([
            'id' => '123456', 'name' => 'Banned', 'email' => $email, 'avatar' => null,
        ]);
        $provider = \Mockery::mock();
        $provider->shouldReceive('user')->andReturn($socialiteUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email']);
        $this->assertGuest('web');
    }

    public function test_ban_does_not_delete_user_record()
    {
        $email = 'ban-'.uniqid().'@example.com';
        User::factory()->create(['email' => $email]);
        $this->banService->ban($email, 'Test');

        $this->assertNotNull(User::where('email', $email)->first());
        $this->assertTrue($this->banService->isBanned($email));
    }
}
