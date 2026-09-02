<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Models\Theme;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ThemeManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function platformAdmin(): PlatformAdmin
    {
        return PlatformAdmin::firstOrReuseForTests([
            'email' => 'theme-admin@example.test',
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    public function test_platform_admin_views_all_themes(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        $this->get(route('admin.industry-settings'))
            ->assertOk()
            ->assertSee('Manage Themes')
            ->assertSee('Ocean Blue')
            ->assertSee('Mark as default')
            ->assertSee('name="is_dark"', false);
    }

    public function test_manage_themes_available_for_specific_industry(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        $this->get(route('admin.industry-settings', ['industry' => 'education']))
            ->assertOk()
            ->assertSee('Education Settings')
            ->assertSee('Manage Themes')
            ->assertSee('Mark as default')
            ->assertSee('name="theme_slug"', false);
    }

    public function test_platform_admin_edits_theme_and_marks_default(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        $theme = Theme::where('slug', 'ocean-blue')->firstOrFail();

        $this->put(route('admin.themes.update', $theme), [
            'name' => 'Ocean Blue X',
            'primary_color' => '#112233',
            'secondary_color' => '#445566',
            'status' => 'active',
            'is_dark' => 1,
            'is_default' => 1,
        ])->assertRedirect(route('admin.industry-settings'));

        $theme->refresh();
        $this->assertSame('Ocean Blue X', $theme->name);
        $this->assertSame('#112233', $theme->primary_color);
        $this->assertSame('#445566', $theme->secondary_color);
        $this->assertSame(1, $theme->is_dark);
        $this->assertSame(1, $theme->is_default);

        $this->assertSame(1, Theme::where('is_default', 1)->count());
    }

    public function test_marking_default_unmarks_other_themes(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        $ocean = Theme::where('slug', 'ocean-blue')->firstOrFail();
        $purple = Theme::where('slug', 'royal-purple')->firstOrFail();

        $this->put(route('admin.themes.update', $purple), [
            'name' => $purple->name,
            'primary_color' => $purple->primary_color,
            'secondary_color' => $purple->secondary_color,
            'status' => 'active',
            'is_default' => 1,
        ])->assertRedirect(route('admin.industry-settings'));

        $this->assertSame(1, $purple->fresh()->is_default);
        $this->assertSame(0, $ocean->fresh()->is_default);
        $this->assertSame(1, Theme::where('is_default', 1)->count());
    }

    public function test_theme_update_rejects_bad_color_and_duplicate_slug(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        $theme = Theme::where('slug', 'ocean-blue')->firstOrFail();

        $this->put(route('admin.themes.update', $theme), [
            'name' => 'Ocean Blue',
            'primary_color' => 'red',
            'secondary_color' => '#445566',
            'status' => 'active',
        ])->assertSessionHasErrors('primary_color');

        $other = Theme::where('slug', 'royal-purple')->firstOrFail();

        $this->put(route('admin.themes.update', $theme), [
            'name' => 'Ocean Blue',
            'slug' => $other->slug,
            'primary_color' => '#112233',
            'secondary_color' => '#445566',
            'status' => 'active',
        ])->assertSessionHasErrors('slug');
    }
}
