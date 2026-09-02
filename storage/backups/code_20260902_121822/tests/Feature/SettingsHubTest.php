<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SettingsHubTest extends TestCase
{
    use DatabaseTransactions;

    public function test_settings_pages_render_standalone(): void
    {
        TenantContext::clear();

        $admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'settings-admin@example.test',
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'platform_admin');

        $this->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Settings')
            ->assertSee('Staff Requests')
            ->assertSee('Change Password')
            ->assertSee('Security')
            ->assertSee('Back to Dashboard');

        $this->get(route('admin.settings.account'))
            ->assertOk()
            ->assertSee($admin->email)
            ->assertSee('Back to Settings');

        $this->get(route('admin.settings.password'))
            ->assertOk()
            ->assertSee('Current Password')
            ->assertSee('Back to Settings');

        $this->get(route('admin.settings.staff'))
            ->assertOk()
            ->assertSee('Staff Registration Requests')
            ->assertSee('Back to Settings');

        $this->get(route('admin.settings.appearance'))
            ->assertOk()
            ->assertSee('Theme')
            ->assertSee('Language')
            ->assertSee('Ocean Blue')
            ->assertSee('Royal Purple')
            ->assertSee('Back to Settings')
            ->assertSee('data-theme-option', false);

        $admin->setPreference('theme_id', 3);
        $admin->setPreference('theme', 'light');

        $this->get(route('admin.settings.account'))
            ->assertOk()
            ->assertSee('--primary: #6F42C1', false);

        $this->post(route('admin.settings.appearance.update'), [
            'theme_id' => 4,
            'language' => 'en',
        ])->assertRedirect(route('admin.settings.index').'#pane-appearance');

        $admin->refresh();
        $this->assertEquals(4, $admin->preference('theme_id'));
        $this->assertEquals('light', $admin->preference('theme'));

        $this->get(route('admin.settings.mail-payment'))
            ->assertOk()
            ->assertSee('SMTP Host')
            ->assertSee('Payment Gateway')
            ->assertSee('Back to Settings');
    }
}
