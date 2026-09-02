<?php

namespace Tests\Feature;

use App\Http\Controllers\InstituteOnboardingController;
use App\Models\Institute;
use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OwnerRegistrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function phone(string $seed): string
    {
        return '017'.substr(preg_replace('/\D/', '', md5($seed)), 0, 8);
    }

    protected function validAccount(string $seed = 'reg-1'): array
    {
        return [
            'first_name' => 'Rafiq',
            'last_name' => 'Ahmed',
            'email' => $seed.'-owner@example.test',
            'phone' => $this->phone($seed),
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ];
    }

    public function test_selection_page_lists_countries_and_embeds_rules(): void
    {
        // New OTP-first flow: /register shows account credentials, not selection
        $this->get('/register')
            ->assertOk()
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="password_confirmation"', false);
    }

    public function test_selection_persists_and_redirects_to_form(): void
    {
        $this->post('/register/selection', [
            'country' => 'Bangladesh',
            'industry' => 'education',
            'sub_industry' => 'school',
        ])->assertRedirect(route('owner.register.form'));

        $this->assertSame([
            'country' => 'Bangladesh',
            'industry' => 'education',
            'sub_industry' => 'school',
        ], session(InstituteOnboardingController::SESSION_KEY));
    }

    public function test_selection_sub_industry_required_when_pair_lists_them(): void
    {
        $this->post('/register/selection', [
            'country' => 'Bangladesh',
            'industry' => 'education',
            'sub_industry' => '',
        ])->assertSessionHasErrors('sub_industry');
    }

    public function test_selection_sub_industry_must_belong_to_pair(): void
    {
        $this->post('/register/selection', [
            'country' => 'Bangladesh',
            'industry' => 'education',
            'sub_industry' => 'clinic',
        ])->assertSessionHasErrors('sub_industry');
    }

    public function test_selection_industry_scoped_to_country_rules(): void
    {
        $this->post('/register/selection', [
            'country' => 'Bangladesh',
            'industry' => 'healthcare',
            'sub_industry' => '',
        ])->assertSessionHasErrors('sub_industry');
    }

    public function test_selection_country_must_be_valid(): void
    {
        $this->post('/register/selection', [
            'country' => 'Atlantis',
            'industry' => 'education',
            'sub_industry' => '',
        ])->assertSessionHasErrors('country');
    }

    public function test_selection_country_without_rules_falls_back_to_global_without_subs(): void
    {
        $this->post('/register/selection', [
            'country' => 'France',
            'industry' => 'healthcare',
            'sub_industry' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame([
            'country' => 'France',
            'industry' => 'healthcare',
            'sub_industry' => null,
        ], session(InstituteOnboardingController::SESSION_KEY));
    }

    public function test_form_page_bounces_to_selection_without_selection(): void
    {
        $this->get('/register/form')
            ->assertRedirect(route('register.account'));
    }

    public function test_form_page_shows_locked_selection(): void
    {
        // Legacy register-owner form now redirects to OTP-first account page
        $this->withSession([InstituteOnboardingController::SESSION_KEY => [
            'country' => 'Bangladesh',
            'industry' => 'education',
            'sub_industry' => 'madrasha',
        ]])->get('/register/form')
            ->assertRedirect(route('register.account'));
    }

    public function test_form_page_phone_uses_selected_country_code(): void
    {
        $this->withSession([InstituteOnboardingController::SESSION_KEY => [
            'country' => 'Albania',
            'industry' => 'healthcare',
            'sub_industry' => null,
        ]])->get('/register/form')
            ->assertRedirect(route('register.account'));
    }

    public function test_register_without_selection_bounces_to_selection(): void
    {
        // New flow: POST /register with missing email fails validation
        $this->post('/register', ['email' => '', 'password' => 'Secret123!', 'password_confirmation' => 'Secret123!'])
            ->assertSessionHasErrors('email');
    }

    public function test_owner_registers_and_lands_on_verification_prompt(): void
    {
        $this->post('/register/account', [
            'email' => 'reg-1-owner@example.test',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ])->assertRedirect(route('register.otp.form'));

        $pending = PendingRegistration::where('email', 'reg-1-owner@example.test')->firstOrFail();
        $this->assertNotNull($pending->otp_hash);
        // User is NOT created before OTP verification
        $this->assertDatabaseMissing('users', ['email' => 'reg-1-owner@example.test']);
        $this->assertDatabaseMissing('institutes', ['name' => 'any']);
        $this->assertNull(auth('web')->id());
    }

    public function test_full_pre_registration_flow_creates_scoped_institute(): void
    {
        $this->post('/register/account', [
            'email' => 'reg-e2e-owner@example.test',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ])->assertRedirect(route('register.otp.form'));

        $pending = PendingRegistration::where('email', 'reg-e2e-owner@example.test')->firstOrFail();
        // Simulate OTP verification
        $pending->update(['verified_at' => now()]);

        // Organization step
        $this->withSession([\App\Http\Controllers\Auth\RegistrationFlowController::PENDING_ID => $pending->id, \App\Http\Controllers\Auth\RegistrationFlowController::SESSION_KEY => ['email' => $pending->email, 'verified' => true, 'step' => 2]])
            ->post('/register/organization', [
                'organization_name' => 'Rafiq Academy',
                'first_name' => 'Rafiq',
                'last_name' => 'Ahmed',
                'phone' => $this->phone('reg-e2e'),
                'country' => 'Bangladesh',
                'industry' => 'education',
                'sub_industry' => 'school',
            ])->assertRedirect(route('register.address'));

        $this->withSession([\App\Http\Controllers\Auth\RegistrationFlowController::PENDING_ID => $pending->id, \App\Http\Controllers\Auth\RegistrationFlowController::SESSION_KEY => ['email' => $pending->email, 'verified' => true, 'step' => 3]])
            ->post('/register/address', ['address' => 'Test address'])
            ->assertRedirect(route('register.education.placeholder'));

        $institute = Institute::query()->where('slug', 'rafiq-academy')->firstOrFail();
        $this->assertSame('Bangladesh', $institute->country);
        $this->assertSame('education', $institute->industry);
        $this->assertSame('school', $institute->sub_industry);
        $user = User::where('email', 'reg-e2e-owner@example.test')->firstOrFail();
        $this->assertSame('owner', $user->account_type);
    }

    public function test_auth_users_redirected_away_from_selection_and_form(): void
    {
        $user = (new \App\Services\UserAccountService)->registerOwner([
            'name' => 'Existing Owner',
            'email' => 'reg-existing@example.test',
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);

        $this->actingAs($user, 'web')
            ->get('/register')
            ->assertRedirect(route('dashboard'));

        $this->actingAs($user, 'web')
            ->get('/register/account')
            ->assertRedirect(route('dashboard'));
    }
}
