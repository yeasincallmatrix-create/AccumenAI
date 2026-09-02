<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\PlatformAdmin;
use App\Models\Student;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected string $adminPassword = 'secret12345';

    protected string $staffPassword = 'secret12345';

    public function test_dashboard_redirects_guest_to_login(): void
    {
        $this->get('/')->assertRedirectContains('login');
    }

    public function test_platform_admin_login_and_dashboard(): void
    {
        $admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'flow-admin@example.test',
            'password_hash' => bcrypt($this->adminPassword),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->post('/admin/login', ['email' => $admin->email, 'password' => $this->adminPassword])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($admin, 'platform_admin');

        $this->get('/')
            ->assertOk()
            ->assertSee('Super Admin');
    }

    public function test_platform_admin_rejected_with_bad_password(): void
    {
        $admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'flow-admin-bad@example.test',
            'password_hash' => bcrypt($this->adminPassword),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'not-the-password'])
            ->assertSessionHasErrors('email');
    }

    public function test_platform_admin_rejected_when_suspended(): void
    {
        $admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'flow-admin-suspended@example.test',
            'password_hash' => bcrypt($this->adminPassword),
            'status' => 'suspended',
            'email_verified_at' => now(),
        ]);

        $this->post('/admin/login', ['email' => $admin->email, 'password' => $this->adminPassword])
            ->assertSessionHasErrors('email');
    }

    public function test_institute_user_login_applies_tenant_style(): void
    {
        TenantContext::clear();

        $institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
        $staff = InstituteUser::create([
            'institute_id' => $institute->id,
            'role_id' => 1,
            'email' => 'flow-staff@example.test',
            'phone' => '01700000001',
            'password_hash' => bcrypt($this->staffPassword),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->post('/institute/login', ['email' => $staff->email, 'password' => $this->staffPassword])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($staff, 'institute_user');
        $this->assertTrue(TenantContext::enabled());

        // Every tenant model is now scoped to the logged-in institute only.
        $expected = DB::table('students')->where('institute_id', $institute->id)->whereNull('deleted_at')->count();
        $this->assertSame($expected, Student::count());
        $this->assertLessThan(DB::table('students')->count(), Student::count());
        $this->assertSame($institute->id, TenantContext::id());

        $this->get('/')
            ->assertOk()
            ->assertSee('Institute Owner')
            ->assertSee('MAWA ACADEMY');
    }

    public function test_logout_clears_tenant_context_and_guard_session(): void
    {
        $institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
        $staff = InstituteUser::create([
            'institute_id' => $institute->id,
            'role_id' => 1,
            'email' => 'flow-staff-out@example.test',
            'phone' => '01700000002',
            'password_hash' => bcrypt($this->staffPassword),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->post('/institute/login', ['email' => $staff->email, 'password' => $this->staffPassword])
            ->assertRedirect('/');

        $this->assertTrue(TenantContext::enabled());

        $this->post('/logout');

        $this->assertFalse(TenantContext::enabled());
        $this->assertGuest('institute_user');
        $this->get('/')->assertRedirectContains('login');
    }

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();
    }
}
