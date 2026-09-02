<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Role;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * STEP 68A — Verify institute user dashboard navigation works after
 * login through the web guard (User model, not InstituteUser).
 *
 * Routes under auth:institute_user,web that use ResolvesInstitute trait
 * must resolve the institute correctly for web-guard authenticated users.
 */
class InstituteUserNavigationTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();
        BranchContext::clear();
        Workspace::clear();
    }

    protected function createOwnerWithInstitute(string $email): array
    {
        $user = (new UserAccountService)->registerOwner([
            'name' => 'Nav Test Owner',
            'first_name' => 'Nav',
            'last_name' => 'Owner',
            'email' => $email,
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);

        $institute = Institute::create([
            'name' => 'Nav Institute '.uniqid(),
            'slug' => 'nav-inst-'.uniqid(),
            'status' => 'active',
            'industry' => 'education',
        ]);
        $roleId = Role::where('slug', 'institute-owner')->firstOrFail()->id;

        (new MembershipService)->assign($user, $institute->id, $roleId);

        return [$user, $institute];
    }

    protected function createStaffWithInstitute(string $email): array
    {
        $user = (new UserAccountService)->createStaffFromInvitation([
            'name' => 'Nav Test Staff',
            'first_name' => 'Nav',
            'last_name' => 'Staff',
            'email' => $email,
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);

        $institute = Institute::create([
            'name' => 'Nav Staff Institute '.uniqid(),
            'slug' => 'nav-staff-inst-'.uniqid(),
            'status' => 'active',
            'industry' => 'education',
        ]);
        $roleId = Role::where('slug', 'branch-manager')->firstOrFail()->id;

        (new MembershipService)->assign($user, $institute->id, $roleId);

        return [$user, $institute];
    }

    protected function asInstituteUser(User $user, int $institutionId): self
    {
        $this->withSession([Workspace::SESSION_KEY => $institutionId])
            ->actingAs($user, 'web');

        return $this;
    }

    // ── Test 1: Owner can access dashboard via web guard ──
    public function test_owner_can_access_root_dashboard(): void
    {
        [$user, $institute] = $this->createOwnerWithInstitute('nav-owner-1@test.local');

        $this->asInstituteUser($user, $institute->id)
            ->get('/')
            ->assertOk();
    }

    // ── Test 2: Owner can access academic dashboard ──
    public function test_owner_can_access_academic_dashboard(): void
    {
        [$user, $institute] = $this->createOwnerWithInstitute('nav-owner-2@test.local');

        $this->asInstituteUser($user, $institute->id)
            ->get('/academic-dashboard')
            ->assertOk();
    }

    // ── Test 3: Owner can access students index ──
    public function test_owner_can_access_students_index(): void
    {
        [$user, $institute] = $this->createOwnerWithInstitute('nav-owner-3@test.local');

        $this->asInstituteUser($user, $institute->id)
            ->get('/students')
            ->assertOk();
    }

    // ── Test 4: Owner can access teachers index ──
    public function test_owner_can_access_teachers_index(): void
    {
        [$user, $institute] = $this->createOwnerWithInstitute('nav-owner-4@test.local');

        $this->asInstituteUser($user, $institute->id)
            ->get('/teachers')
            ->assertOk();
    }

    // ── Test 5: Owner can access HR dashboard ──
    public function test_owner_can_access_hr_dashboard(): void
    {
        [$user, $institute] = $this->createOwnerWithInstitute('nav-owner-5@test.local');

        $this->asInstituteUser($user, $institute->id)
            ->get('/hr')
            ->assertOk();
    }

    // ── Test 6: Owner can access finance dashboard ──
    public function test_owner_can_access_finance_dashboard(): void
    {
        [$user, $institute] = $this->createOwnerWithInstitute('nav-owner-6@test.local');

        $this->asInstituteUser($user, $institute->id)
            ->get('/finance')
            ->assertOk();
    }

    // ── Test 7: Owner can access accounting report pages ──
    public function test_owner_can_access_accounting_reports(): void
    {
        [$user, $institute] = $this->createOwnerWithInstitute('nav-owner-7@test.local');

        $this->asInstituteUser($user, $institute->id)
            ->get('/accounting/reports/trial-balance')
            ->assertOk();
    }

    // ── Test 8: Workspace session is set after login ──
    public function test_workspace_session_is_set_after_web_login(): void
    {
        [$user, $institute] = $this->createOwnerWithInstitute('nav-owner-8@test.local');

        $this->actingAs($user, 'web')
            ->post('/workspace/switch/'.$institute->id)
            ->assertSessionHasNoErrors();

        $this->assertSame($institute->id, Workspace::id());
        $this->assertSame($institute->id, TenantContext::id());
    }

    // ── Test 9: resolveInstitute works for web-guard user ──
    public function test_resolve_institute_works_for_web_guard_user(): void
    {
        [$user, $institute] = $this->createOwnerWithInstitute('nav-owner-9@test.local');

        $this->asInstituteUser($user, $institute->id)
            ->get('/academic-dashboard')
            ->assertOk();

        // After request, TenantContext should be the institute
        $this->assertSame($institute->id, TenantContext::id());
    }

    // ── Test 10: Staff user can navigate ──
    public function test_staff_user_can_navigate_modules(): void
    {
        [$user, $institute] = $this->createStaffWithInstitute('nav-staff-1@test.local');

        $this->asInstituteUser($user, $institute->id)
            ->get('/')
            ->assertOk();

        $this->asInstituteUser($user, $institute->id)
            ->get('/students')
            ->assertOk();
    }

    // ── Test 11: Cross-tenant access resolves to user's own workspace ──
    public function test_cross_tenant_access_resolves_to_own_workspace(): void
    {
        [$user, $instituteA] = $this->createOwnerWithInstitute('nav-cross-a@test.local');

        $instituteB = Institute::create([
            'name' => 'Other Institute '.uniqid(),
            'slug' => 'other-'.uniqid(),
            'status' => 'active',
        ]);

        $this->asInstituteUser($user, $instituteB->id)
            ->get('/students')
            ->assertOk();

        // SetTenantContext fallback resolves to the user's valid workspace
        $this->assertSame($instituteA->id, TenantContext::id());
    }

    // ── Test 12: Module access check works for enabled module ──
    public function test_module_access_check_works_for_enabled_module(): void
    {
        [$user, $institute] = $this->createOwnerWithInstitute('nav-owner-12@test.local');

        // Finance module should be enabled for MAWA ACADEMY (premium)
        $this->asInstituteUser($user, $institute->id)
            ->get('/finance')
            ->assertOk();
    }

    // ── Test 13: TenantContext is correctly bound during request ──
    public function test_tenant_context_bound_during_request(): void
    {
        [$user, $institute] = $this->createOwnerWithInstitute('nav-owner-13@test.local');

        $this->asInstituteUser($user, $institute->id)
            ->get('/academic-dashboard')
            ->assertOk();

        $this->assertSame($institute->id, TenantContext::id());
    }

    // ── Test 14: Workspace ID persists across requests ──
    public function test_workspace_id_persists_across_requests(): void
    {
        [$user, $institute] = $this->createOwnerWithInstitute('nav-owner-14@test.local');

        $this->actingAs($user, 'web')
            ->post('/workspace/switch/'.$institute->id)
            ->assertSessionHasNoErrors();

        // First request
        $this->get('/academic-dashboard')->assertOk();
        $this->assertSame($institute->id, Workspace::id());

        // Second request
        $this->get('/students')->assertOk();
        $this->assertSame($institute->id, Workspace::id());
    }

    // ── Test 15: Unauthenticated user is redirected to login ──
    public function test_unauthenticated_user_is_redirected(): void
    {
        $this->get('/students')->assertRedirect();
    }
}
