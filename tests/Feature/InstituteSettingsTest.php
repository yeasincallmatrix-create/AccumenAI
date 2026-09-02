<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InstituteSettingsTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    private Institute $institute;

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();

        $this->institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
    }

    protected function makeStaff(string $roleSlug, string $email): InstituteUser
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();

        return InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $role->id,
            'email' => $email,
            'phone' => '0170000'.substr(md5($email), 0, 4),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    public function test_settings_page_renders_admin_style_tabs(): void
    {
        $owner = $this->makeStaff('institute-owner', 'settings-owner@example.test');

        $this->actingAs($owner, 'institute_user')
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('settings-tab-btn', false)
            ->assertSee('pane-account', false)
            ->assertSee('pane-general', false)
            ->assertSee('pane-appearance', false)
            ->assertSee('pane-security', false)
            ->assertSee('settings-owner@example.test')
            ->assertSee('Update Password');
    }

    public function test_settings_page_hides_appearance_for_non_managers(): void
    {
        $teacher = $this->makeStaff('teacher', 'settings-teacher@example.test');

        $this->actingAs($teacher, 'institute_user')
            ->get(route('settings.index'))
            ->assertOk()
            ->assertDontSee('pane-appearance', false)
            ->assertDontSee('pane-general', false)
            ->assertDontSee('form-control-color', false);
    }

    public function test_settings_page_shows_promotions_link_only_with_promotion_permission(): void
    {
        $owner = $this->makeStaff('institute-owner', 'settings-promo-owner@example.test');
        $teacher = $this->makeStaff('teacher', 'settings-promo-teacher@example.test');

        $this->actingAs($owner, 'institute_user')
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee(route('settings.academic.promotions.index'), false);

        $this->actingAs($teacher, 'institute_user')
            ->get(route('settings.index'))
            ->assertOk()
            ->assertDontSee(route('settings.academic.promotions.index'), false);
    }

    public function test_appearance_shows_theme_swatches_like_admin(): void
    {
        $owner = $this->makeStaff('institute-owner', 'settings-theme@example.test');

        $this->actingAs($owner, 'institute_user')
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('theme-option', false)
            ->assertSee('theme-swatch', false)
            ->assertSee('Ocean Blue')
            ->assertSee('Royal Purple')
            ->assertSee('data-theme-option', false);
    }

    public function test_update_appearance_applies_selected_theme_colors(): void
    {
        $owner = $this->makeStaff('institute-owner', 'settings-theme2@example.test');

        $this->actingAs($owner, 'institute_user')
            ->put(route('settings.appearance.update'), [
                'theme_slug' => 'royal-purple',
                'sidebar_color' => '#123456',
                'timezone' => 'Asia/Dhaka',
                'language' => 'bn',
            ])
            ->assertRedirect(route('settings.index', '#pane-appearance'));

        $stored = DB::table('institute_settings')
            ->where('institute_id', $this->institute->id)
            ->first();

        $this->assertNotNull($stored);
        $this->assertSame('royal-purple', $stored->theme);
        $this->assertSame('#6F42C1', strtoupper($stored->primary_color));
        $this->assertSame('#123456', strtoupper($stored->sidebar_color));
    }

    public function test_update_appearance_requires_a_preset_theme(): void
    {
        $owner = $this->makeStaff('institute-owner', 'settings-theme3@example.test');

        $this->actingAs($owner, 'institute_user')
            ->put(route('settings.appearance.update'), [
                'theme_slug' => '',
                'primary_color' => '#112233',
                'secondary_color' => '#445566',
            ])
            ->assertSessionHasErrors('theme_slug');

        $this->actingAs($owner, 'institute_user')
            ->put(route('settings.appearance.update'), [
                'theme_slug' => 'not-a-theme',
            ])
            ->assertSessionHasErrors('theme_slug');
    }

    public function test_update_general_saves_timezone_and_language(): void
    {
        $owner = $this->makeStaff('institute-owner', 'settings-general@example.test');

        $this->actingAs($owner, 'institute_user')
            ->put(route('settings.general.update'), [
                'timezone' => 'Asia/Kolkata',
                'language' => 'en',
            ])
            ->assertRedirect(route('settings.index', '#pane-general'));

        $stored = DB::table('institute_settings')
            ->where('institute_id', $this->institute->id)
            ->first();

        $this->assertNotNull($stored);
        $this->assertSame('Asia/Kolkata', $stored->timezone);
        $this->assertSame('en', $stored->language);
    }

    public function test_update_general_rejects_invalid_timezone(): void
    {
        $owner = $this->makeStaff('institute-owner', 'settings-general2@example.test');

        $this->actingAs($owner, 'institute_user')
            ->put(route('settings.general.update'), [
                'timezone' => 'Not/AZone',
                'language' => 'en',
            ])
            ->assertSessionHasErrors('timezone');
    }

    public function test_update_password_rejects_wrong_current_password(): void
    {
        $owner = $this->makeStaff('institute-owner', 'settings-pw@example.test');

        $this->actingAs($owner, 'institute_user')
            ->put(route('settings.password'), [
                'current_password' => 'wrong-password',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check($this->password, $owner->fresh()->password_hash));
    }

    public function test_update_password_changes_password(): void
    {
        $owner = $this->makeStaff('institute-owner', 'settings-pw2@example.test');

        $this->actingAs($owner, 'institute_user')
            ->put(route('settings.password'), [
                'current_password' => $this->password,
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertRedirect(route('settings.index', '#pane-account'));

        $this->assertTrue(Hash::check('NewPassword123!', $owner->fresh()->password_hash));
    }
}
