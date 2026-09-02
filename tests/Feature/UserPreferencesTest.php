<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class UserPreferencesTest extends TestCase
{
    use DatabaseTransactions;

    protected function ownerUser(string $email = 'prefs-owner@example.test'): User
    {
        return User::create([
            'name' => 'Prefs Owner',
            'first_name' => 'Prefs',
            'last_name' => 'Owner',
            'email' => $email,
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
            'account_type' => 'owner',
        ]);
    }

    public function test_preferences_page_saves_theme_and_language_per_account(): void
    {
        $user = $this->ownerUser();
        $this->actingAs($user);

        $this->put(route('account.preferences.update'), [
            'theme' => 'dark',
            'language' => 'bn',
        ])->assertRedirect(route('account.preferences'));

        $user->refresh();
        $this->assertSame('dark', $user->preference('theme'));
        $this->assertSame('bn', $user->preferred_language);
    }

    public function test_preferences_are_isolated_between_users(): void
    {
        $first = $this->ownerUser('prefs-first@example.test');
        $second = $this->ownerUser('prefs-second@example.test');

        $this->actingAs($first);
        $this->put(route('account.preferences.update'), [
            'theme' => 'dark',
            'language' => 'bn',
        ])->assertRedirect();

        $second->refresh();
        $this->assertSame('default', $second->preference('theme'));
        $this->assertSame('en', $second->preferred_language);
    }

    public function test_theme_toggle_endpoint_persists_per_account(): void
    {
        $user = $this->ownerUser();
        $this->actingAs($user);

        $this->postJson(route('account.preferences.theme'), ['theme' => 'dark'])
            ->assertOk()
            ->assertJson(['ok' => true, 'theme' => 'dark']);

        $this->assertSame('dark', $user->refresh()->preference('theme'));
    }

    public function test_preferences_work_for_institute_user_accounts(): void
    {
        $institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
        $staff = InstituteUser::create([
            'institute_id' => $institute->id,
            'role_id' => 1,
            'email' => 'prefs-staff@example.test',
            'phone' => '01700009999',
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);

        Auth::guard('institute_user')->login($staff);
        $this->postJson(route('account.preferences.theme'), ['theme' => 'light'])
            ->assertOk();

        $this->assertSame('light', $staff->refresh()->preference('theme'));
    }
}
