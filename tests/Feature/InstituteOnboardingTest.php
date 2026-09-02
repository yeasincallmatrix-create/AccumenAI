<?php

namespace Tests\Feature;

use App\Http\Controllers\InstituteOnboardingController;
use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InstituteOnboardingTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function owner(string $email = 'onboard-owner@example.test'): User
    {
        return (new UserAccountService)->registerOwner([
            'name' => 'Onboard Owner',
            'first_name' => 'Onboard',
            'last_name' => 'Owner',
            'email' => $email,
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    protected function staff(string $email = 'onboard-staff@example.test'): User
    {
        return (new UserAccountService)->createStaffFromInvitation([
            'name' => 'Onboard Staff',
            'email' => $email,
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    public function test_onboarding_page_lists_countries_and_embeds_rules(): void
    {
        $owner = $this->owner('onboard-page@example.test');

        $this->actingAs($owner, 'web')
            ->get('/workspace/onboarding')
            ->assertOk()
            ->assertSee('Bangladesh')
            ->assertSee('United States')
            ->assertSee('Madrasha', false)
            ->assertSee('name="sub_industry"', false);
    }

    public function test_choose_persists_selection_and_redirects(): void
    {
        $owner = $this->owner('onboard-req-sub@example.test');

        $this->actingAs($owner, 'web')
            ->post('/workspace/onboarding', [
                'country' => 'Bangladesh',
                'industry' => 'education',
                'sub_industry' => 'school',
            ])
            ->assertRedirect(route('workspace.create'));

        $this->assertSame('Bangladesh', session(InstituteOnboardingController::SESSION_KEY.'.country'));
        $this->assertSame('education', session(InstituteOnboardingController::SESSION_KEY.'.industry'));
        $this->assertSame('school', session(InstituteOnboardingController::SESSION_KEY.'.sub_industry'));
    }

    public function test_sub_industry_required_when_country_industry_lists_them(): void
    {
        $owner = $this->owner('onboard-pair@example.test');

        $this->actingAs($owner, 'web')
            ->post('/workspace/onboarding', [
                'country' => 'Bangladesh',
                'industry' => 'education',
                'sub_industry' => '',
            ])
            ->assertSessionHasErrors('sub_industry');
    }

    public function test_sub_industry_must_belong_to_country_industry_pair(): void
    {
        $owner = $this->owner('onboard-scope@example.test');

        $this->actingAs($owner, 'web')
            ->post('/workspace/onboarding', [
                'country' => 'Bangladesh',
                'industry' => 'education',
                'sub_industry' => 'clinic',
            ])
            ->assertSessionHasErrors('sub_industry');
    }

    public function test_industry_scoped_to_country_rules(): void
    {
        $owner = $this->owner('onboard-fallback@example.test');

        // Bangladesh now lists healthcare with sub-industries, so empty sub triggers sub_industry error, not industry.
        $this->actingAs($owner, 'web')
            ->post('/workspace/onboarding', [
                'country' => 'Bangladesh',
                'industry' => 'healthcare',
                'sub_industry' => '',
            ])
            ->assertSessionHasErrors('sub_industry');
    }

    public function test_unknown_country_falls_back_to_global_industries_without_subs(): void
    {
        $owner = $this->owner('onboard-europe@example.test');

        $this->actingAs($owner, 'web')
            ->post('/workspace/onboarding', [
                'country' => 'France',
                'industry' => 'healthcare',
                'sub_industry' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('France', session(InstituteOnboardingController::SESSION_KEY.'.country'));
        $this->assertSame('healthcare', session(InstituteOnboardingController::SESSION_KEY.'.industry'));
        $this->assertNull(session(InstituteOnboardingController::SESSION_KEY.'.sub_industry'));
    }

    public function test_create_without_selection_bounces_to_onboarding(): void
    {
        $owner = $this->owner('onboard-bounce@example.test');

        $this->actingAs($owner, 'web')
            ->get('/workspace/create')
            ->assertRedirect(route('workspace.onboarding'));
    }

    public function test_create_page_shows_locked_selection(): void
    {
        $owner = $this->owner('onboard-create-page@example.test');

        $this->actingAs($owner, 'web')
            ->withSession([InstituteOnboardingController::SESSION_KEY => [
                'country' => 'Bangladesh',
                'industry' => 'education',
                'sub_industry' => 'madrasha',
            ]])
            ->get('/workspace/create')
            ->assertOk()
            ->assertSee('Bangladesh')
            ->assertSee('Education')
            ->assertSee('Madrasha')
            ->assertSee('Step 2 of 2')
            ->assertSee('name="name"', false);
    }

    public function test_onboarding_restricts_non_owners(): void
    {
        $staff = $this->staff('onboard-non-owner@example.test');

        $this->actingAs($staff, 'web')
            ->get('/workspace/onboarding')
            ->assertForbidden();

        $this->actingAs($staff, 'web')
            ->post('/workspace/onboarding', [
                'country' => 'Bangladesh',
                'industry' => 'education',
                'sub_industry' => 'school',
            ])
            ->assertForbidden();
    }
}