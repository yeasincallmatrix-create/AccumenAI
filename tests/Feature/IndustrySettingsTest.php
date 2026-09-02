<?php

namespace Tests\Feature;

use App\Models\IndustrySetting;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\PlatformAdmin;
use App\Models\Role;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class IndustrySettingsTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function platformAdmin(): PlatformAdmin
    {
        return PlatformAdmin::firstOrReuseForTests([
            'email' => 'ind-admin@example.test',
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    public function test_industry_settings_page_shows_theme_option(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        $this->get(route('admin.industry-settings'))
            ->assertOk()
            ->assertSee('Default Color Theme')
            ->assertSee('theme-option', false)
            ->assertSee('theme-swatch', false)
            ->assertSee('Ocean Blue')
            ->assertSee('Royal Purple');
    }

    public function test_settings_page_has_country_and_sub_industry_filters(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        $this->get(route('admin.industry-settings'))
            ->assertOk()
            ->assertSee('country-filter', false)
            ->assertSee('Filter by country')
            ->assertDontSee('sub-industry-filter', false);

        $this->get(route('admin.industry-settings', ['industry' => 'education']))
            ->assertOk()
            ->assertSee('country-filter', false)
            ->assertSee('sub-industry-filter', false)
            ->assertSee('Filter by sub industry')
            ->assertSee('All Sub Industries', false);
    }

    public function test_platform_admin_saves_default_theme_for_industry(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        $this->post(route('admin.industry-settings.theme'), [
            'industry_key' => 'education',
            'theme_slug' => 'royal-purple',
        ])->assertRedirect(route('admin.industry-settings', ['industry' => 'education']));

        $this->assertSame('royal-purple', IndustrySetting::where('industry_key', 'education')->value('theme_slug'));
    }

    public function test_platform_admin_saves_default_theme_for_all_industries(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        $this->post(route('admin.industry-settings.theme'), [
            'industry_key' => 'all',
            'theme_slug' => 'crimson-red',
        ])->assertRedirect(route('admin.industry-settings'));

        $this->assertSame('crimson-red', IndustrySetting::where('industry_key', 'all')->value('theme_slug'));
    }

    public function test_industry_theme_update_rejects_invalid_values(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        $this->post(route('admin.industry-settings.theme'), [
            'industry_key' => 'not-an-industry',
            'theme_slug' => 'royal-purple',
        ])->assertSessionHasErrors('industry_key');

        $this->post(route('admin.industry-settings.theme'), [
            'industry_key' => 'education',
            'theme_slug' => 'not-a-theme',
        ])->assertSessionHasErrors('theme_slug');

        $this->assertSame(0, IndustrySetting::count());
    }

    public function test_industry_default_theme_used_when_institute_has_no_setting(): void
    {
        TenantContext::clear();

        $institute = Institute::create([
            'name' => 'Theme Fallback '.mt_rand(1000, 9999),
            'slug' => 'theme-fallback-'.mt_rand(1000, 9999),
            'industry' => 'education',
            'status' => 'active',
        ]);

        IndustrySetting::create([
            'industry_key' => 'education',
            'theme_slug' => 'royal-purple',
        ]);

        $role = Role::where('slug', 'institute-owner')->firstOrFail();
        $owner = InstituteUser::create([
            'institute_id' => $institute->id,
            'role_id' => $role->id,
            'email' => 'fallback-owner@example.test',
            'phone' => '0170000'.substr(md5('fallback-owner@example.test'), 0, 4),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);

        $this->actingAs($owner, 'institute_user')
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('value="royal-purple" checked', false)
            ->assertSee('#6F42C1');
    }
}
