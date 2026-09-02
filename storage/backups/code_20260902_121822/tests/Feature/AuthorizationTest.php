<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\PlatformAdmin;
use App\Models\Role;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function makeStaff(string $roleSlug, string $email): InstituteUser
    {
        $institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
        $role = Role::where('slug', $roleSlug)->firstOrFail();

        return InstituteUser::create([
            'institute_id' => $institute->id,
            'role_id' => $role->id,
            'email' => $email,
            'phone' => '0170000'.substr(md5($email), 0, 4),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    public function test_owner_bypasses_permission_matrix(): void
    {
        $staff = $this->makeStaff('institute-owner', 'authz-owner@example.test');

        $this->assertTrue($staff->isOwner());
        $this->assertTrue($staff->hasPermission('settings.manage'));
        $this->assertTrue($staff->hasPermission('finance.manage'));
    }

    public function test_role_permissions_from_matrix(): void
    {
        $accountant = $this->makeStaff('accountant', 'authz-accountant@example.test');
        $this->assertFalse($accountant->isOwner());
        $this->assertTrue($accountant->hasPermission('finance.manage'));
        $this->assertFalse($accountant->hasPermission('settings.manage'));

        $teacher = $this->makeStaff('teacher', 'authz-teacher@example.test');
        $this->assertTrue($teacher->hasPermission('results.publish'));
        $this->assertFalse($teacher->hasPermission('finance.view'));
        $this->assertFalse($teacher->hasAnyPermission(['settings.manage', 'finance.view']));
        $this->assertTrue($teacher->hasAnyPermission(['settings.manage', 'results.publish']));
    }

    public function test_permission_middleware_denies_when_missing(): void
    {
        Route::middleware(['auth:institute_user', 'permission:students.manage'])
            ->get('/_authz/manage-students', fn () => 'ok');

        $staff = $this->makeStaff('teacher', 'authz-deny@example.test');
        $this->actingAs($staff, 'institute_user')
            ->get('/_authz/manage-students')
            ->assertForbidden();
    }

    public function test_permission_middleware_allows_when_granted(): void
    {
        Route::middleware(['auth:institute_user', 'permission:students.manage'])
            ->get('/_authz/manage-students', fn () => 'ok');

        $staff = $this->makeStaff('accountant', 'authz-deny2@example.test');
        $this->actingAs($staff, 'institute_user')
            ->get('/_authz/manage-students')
            ->assertForbidden();

        $receptionist = $this->makeStaff('receptionist', 'authz-allow@example.test');
        $this->actingAs($receptionist, 'institute_user')
            ->get('/_authz/manage-students')
            ->assertOk()
            ->assertSee('ok');
    }

    public function test_permission_middleware_any_of_semantics(): void
    {
        Route::middleware(['auth:institute_user', 'permission:finance.manage,settings.manage'])
            ->get('/_authz/finance-or-settings', fn () => 'ok');

        $accountant = $this->makeStaff('accountant', 'authz-any@example.test');
        $this->actingAs($accountant, 'institute_user')
            ->get('/_authz/finance-or-settings')
            ->assertOk();

        $teacher = $this->makeStaff('teacher', 'authz-any2@example.test');
        $this->actingAs($teacher, 'institute_user')
            ->get('/_authz/finance-or-settings')
            ->assertForbidden();
    }

    public function test_permission_middleware_allows_platform_admin(): void
    {
        Route::middleware(['auth:platform_admin', 'permission:students.manage'])
            ->get('/_authz/platform', fn () => 'ok');

        $admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'authz-platform@example.test',
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'platform_admin')
            ->get('/_authz/platform')
            ->assertOk();
    }

    public function test_inactive_role_grants_nothing(): void
    {
        $institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
        $role = Role::where('slug', 'teacher')->firstOrFail();
        $role->update(['status' => 'inactive']);

        $staff = InstituteUser::create([
            'institute_id' => $institute->id,
            'role_id' => $role->id,
            'email' => 'authz-inactive-role@example.test',
            'phone' => '01700001111',
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);

        $this->assertFalse($staff->hasPermission('results.publish'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();
    }
}
