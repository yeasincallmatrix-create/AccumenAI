<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Services\UserAccountService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use DatabaseTransactions;

    public function test_global_owner_password_reset_uses_users_broker(): void
    {
        $user = (new UserAccountService)->registerOwner([
            'name' => 'Reset Owner',
            'email' => 'reset-owner@example.test',
            'password_hash' => bcrypt('OldPass123!'),
            'status' => 'active',
        ]);

        $token = Password::broker('users')->createToken($user);
        $this->assertNotEmpty($token);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPass123!456',
            'password_confirmation' => 'NewPass123!456',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NewPass123!456', $user->fresh()->getAuthPassword()));
        $this->assertFalse(Hash::check('OldPass123!', $user->fresh()->getAuthPassword()));
    }

    public function test_legacy_institute_user_password_reset_still_works(): void
    {
        $institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
        $staff = InstituteUser::create([
            'institute_id' => $institute->id,
            'role_id' => 1,
            'email' => 'reset-staff@example.test',
            'phone' => '01700009999',
            'password_hash' => bcrypt('OldPass123!'),
            'status' => 'active',
        ]);

        $token = Password::broker('institute_users')->createToken($staff);
        $this->assertNotEmpty($token);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $staff->email,
            'password' => 'NewPass123!456',
            'password_confirmation' => 'NewPass123!456',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NewPass123!456', $staff->fresh()->getAuthPassword()));
    }

    public function test_invalid_reset_token_is_rejected(): void
    {
        $user = (new UserAccountService)->registerOwner([
            'name' => 'Reset Bad',
            'email' => 'reset-bad@example.test',
            'password_hash' => bcrypt('OldPass123!'),
            'status' => 'active',
        ]);

        $this->post('/reset-password', [
            'token' => 'invalid-token-xyz',
            'email' => $user->email,
            'password' => 'NewPass123!456',
            'password_confirmation' => 'NewPass123!456',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('OldPass123!', $user->fresh()->getAuthPassword()));
    }

    public function test_forgot_password_probes_all_portals_without_revealing(): void
    {
        Notification::fake();

        $user = (new UserAccountService)->registerOwner([
            'name' => 'Reset Probe',
            'email' => 'reset-probe@example.test',
            'password_hash' => bcrypt('OldPass123!'),
            'status' => 'active',
        ]);

        $this->post('/forgot-password', ['email' => 'reset-probe@example.test'])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }
}
