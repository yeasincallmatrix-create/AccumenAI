<?php

namespace Tests\Feature;

use App\Models\InstituteUser;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BackupSettingsLinkTest extends TestCase
{
    public function test_backup_route_exists()
    {
        $this->assertTrue(Route::has('tenant.backup.index'));
    }

    public function test_settings_shows_backup_link_for_owner()
    {
        $user = $this->owner();
        if (!$user) {
            $this->markTestSkipped('No active-institute owner available');
        }

        $response = $this->actingAs($user, 'institute_user')->get('/settings');

        $response->assertOk();
        $response->assertSee('Backup');
        $response->assertSee(route('tenant.backup.index'), false);
    }

    public function test_settings_hides_backup_link_for_non_owner()
    {
        $user = $this->nonOwner();
        if (!$user) {
            $this->markTestSkipped('No non-owner institute user available');
        }

        $response = $this->actingAs($user, 'institute_user')->get('/settings');

        $response->assertOk();
        $response->assertDontSee(route('tenant.backup.index'), false);
    }

    public function test_backup_route_requires_permission()
    {
        // Non-owner (no settings.manage) → 403 even via direct URL
        $nonOwner = $this->nonOwner();
        if ($nonOwner) {
            $this->actingAs($nonOwner, 'institute_user')
                ->get('/tenant/backup')
                ->assertForbidden();
        }

        // Owner passes the permission gate (must not be 403)
        $owner = $this->owner();
        if (!$owner) {
            $this->markTestSkipped('No owner available for positive permission check');
        }

        $response = $this->actingAs($owner, 'institute_user')->get('/tenant/backup');
        $this->assertNotEquals(403, $response->status(), 'Owner must not be forbidden');
        $response->assertOk();

        // Breadcrumb: Dashboard > Settings > Backup
        $response->assertSee('aria-label="breadcrumb"', false);
        $response->assertSee(route('settings.index'), false);
    }

    public function test_existing_settings_items_preserved()
    {
        $user = $this->owner();
        if (!$user) {
            $this->markTestSkipped('No active-institute owner available');
        }

        $response = $this->actingAs($user, 'institute_user')->get('/settings');

        $response->assertOk();

        // Existing menu items — raw literals in the blade, must stay visible
        $response->assertSee('Branding');
        $response->assertSee('Medical');
        $response->assertSee('Advanced Accounting');
        $response->assertSee('Modules');
        $response->assertSee('Terminology');
        // Academic Settings is intentionally NOT asserted: it renders only for
        // institutes whose industry is 'education' ($isAcademic guard).
    }

    private function owner(): ?InstituteUser
    {
        // A request earlier in the same test leaves TenantContext bound to the
        // requesting user's institute, which would hide owners from other
        // institutes via the TenantScoped global scope.
        \App\Support\TenantContext::clear();

        // Note: the 'tenant' middleware (SetTenantContext) does not gate on
        // institute.status, so any owner with an institute_id qualifies here.
        return InstituteUser::query()
            ->join('roles', 'roles.id', '=', 'institute_users.role_id')
            ->where('roles.slug', 'institute-owner')
            ->whereNotNull('institute_users.institute_id')
            ->select('institute_users.*')
            ->orderBy('institute_users.id')
            ->first();
    }

    private function nonOwner(): ?InstituteUser
    {
        \App\Support\TenantContext::clear();

        return InstituteUser::query()
            ->join('roles', 'roles.id', '=', 'institute_users.role_id')
            ->where('roles.slug', '!=', 'institute-owner')
            ->whereNotNull('institute_users.institute_id')
            ->select('institute_users.*')
            ->orderBy('institute_users.id')
            ->first();
    }
}
