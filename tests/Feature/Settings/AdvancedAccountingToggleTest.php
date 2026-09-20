<?php

namespace Tests\Feature\Settings;

use App\Models\Institute;
use App\Models\User;
use App\Services\Accounting\TenantAccountingModeService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use Tests\TestCase;

class AdvancedAccountingToggleTest extends TestCase
{
    protected function tenantOwner(string $email): array
    {
        $unique = strtolower(preg_replace('/[^a-z]/i', '', uniqid()));
        $email = str_replace('@', "+{$unique}@", $email);
        $institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
        $owner = (new UserAccountService)->registerOwner([
            'name' => 'Toggle Owner',
            'first_name' => 'Toggle',
            'last_name' => 'Owner',
            'email' => $email,
            'password_hash' => bcrypt('password'),
            'status' => 'active',
        ]);
        $roleId = \App\Models\Role::where('slug', 'institute-owner')->firstOrFail()->id;
        (new MembershipService)->assign($owner, $institute->id, $roleId);

        return [$institute, $owner];
    }

    protected function asUser(User $user, int $workspaceId): static
    {
        return $this->withSession([\App\Support\Workspace::SESSION_KEY => $workspaceId])
            ->actingAs($user, 'web');
    }

    public function test_default_is_disabled(): void
    {
        [$institute] = $this->tenantOwner('toggle-def@example.test');
        app(TenantAccountingModeService::class)->disable($institute->id);

        \App\Support\TenantContext::set($institute->id);
        $this->assertFalse(advanced_accounting());
    }

    public function test_toggle_enables_advanced(): void
    {
        [$institute, $owner] = $this->tenantOwner('toggle-on@example.test');
        app(TenantAccountingModeService::class)->disable($institute->id);

        $this->asUser($owner, $institute->id)
            ->post(route('settings.advanced-accounting.toggle'))
            ->assertRedirect();

        $this->assertTrue((bool) $institute->fresh()->advanced_accounting_enabled);
    }

    public function test_toggle_disables_advanced(): void
    {
        [$institute, $owner] = $this->tenantOwner('toggle-off@example.test');
        app(TenantAccountingModeService::class)->enable($institute->id);

        $this->asUser($owner, $institute->id)
            ->post(route('settings.advanced-accounting.toggle'))
            ->assertRedirect();

        $this->assertFalse((bool) $institute->fresh()->advanced_accounting_enabled);
    }

    public function test_settings_page_renders(): void
    {
        [$institute, $owner] = $this->tenantOwner('toggle-page@example.test');

        $this->asUser($owner, $institute->id)
            ->get(route('settings.advanced-accounting'))
            ->assertStatus(200)
            ->assertSee('Advanced Accounting');
    }

    public function test_advanced_routes_blocked_when_disabled(): void
    {
        [$institute, $owner] = $this->tenantOwner('toggle-block@example.test');
        app(TenantAccountingModeService::class)->disable($institute->id);

        $this->asUser($owner, $institute->id)
            ->get(route('accounting.reports.ratios'))
            ->assertStatus(403);
    }

    public function test_advanced_routes_work_when_enabled(): void
    {
        [$institute, $owner] = $this->tenantOwner('toggle-allow@example.test');
        app(TenantAccountingModeService::class)->enable($institute->id);

        $this->asUser($owner, $institute->id)
            ->get(route('accounting.reports.ratios'))
            ->assertStatus(200);
    }
}
