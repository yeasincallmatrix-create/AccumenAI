<?php

namespace Tests\Feature;

use App\Models\AccountingAuditTrail;
use App\Models\Branch;
use App\Models\Institute;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\SecurityAuditService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * STEP 99 — Security Audit Tests.
 *
 * 5 tests covering: dashboard rendering, audit logs rendering,
 * security service summary, security headers, and permission system roles.
 */
class SecurityAuditTest extends TestCase
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

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        Workspace::clear();
        parent::tearDown();
    }

    protected function owner(string $email): User
    {
        $user = (new UserAccountService)->registerOwner([
            'name' => 'Step99 Owner',
            'first_name' => 'Step99',
            'last_name' => 'Owner',
            'email' => $email,
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        return $user;
    }

    protected function institute(string $name): Institute
    {
        return Institute::create([
            'name' => $name.' '.uniqid(),
            'slug' => \Illuminate\Support\Str::slug($name.' '.uniqid()),
            'status' => 'active',
        ]);
    }

    protected function roleId(string $slug): int
    {
        return Role::where('slug', $slug)->firstOrFail()->id;
    }

    protected function assign(User $user, Institute $institute, string $roleSlug): \App\Models\Membership
    {
        return (new MembershipService)->assign($user, $institute->id, $this->roleId($roleSlug));
    }

    protected function asUser(User $user, int $workspaceId): static
    {
        return $this->withSession([Workspace::SESSION_KEY => $workspaceId])->actingAs($user, 'web');
    }

    // --------------------------------------------------------- Tests

    public function test_security_dashboard_renders(): void
    {
        $inst = $this->institute('Sec Dashboard');
        $owner = $this->owner('step99-dash-'.uniqid().'@example.test');
        $this->assign($owner, $inst, 'institute-owner');

        $this->asUser($owner, (int) $inst->id)
            ->get(route('accounting.security.index'))
            ->assertOk()
            ->assertSee('Security Dashboard');
    }

    public function test_security_audit_logs_renders(): void
    {
        $inst = $this->institute('Sec Logs');
        $owner = $this->owner('step99-logs-'.uniqid().'@example.test');
        $this->assign($owner, $inst, 'institute-owner');

        $this->asUser($owner, (int) $inst->id)
            ->get(route('accounting.security.audit-logs'))
            ->assertOk()
            ->assertSee('Audit Trail');
    }

    public function test_security_service_returns_summary(): void
    {
        $inst = $this->institute('Sec Service');

        $service = app(SecurityAuditService::class);
        $summary = $service->getSecuritySummary((int) $inst->id);

        $this->assertArrayHasKey('permissions', $summary);
        $this->assertArrayHasKey('audit_logs', $summary);
        $this->assertArrayHasKey('rate_limiting', $summary);
        $this->assertArrayHasKey('password_strength', $summary);
        $this->assertArrayHasKey('overall_healthy', $summary);
        $this->assertArrayHasKey('checks_passed', $summary);
        $this->assertArrayHasKey('checks_total', $summary);
        $this->assertArrayHasKey('score', $summary);
        $this->assertIsNumeric($summary['score']);
        $this->assertGreaterThanOrEqual(0, $summary['score']);
        $this->assertLessThanOrEqual(100, $summary['score']);
    }

    public function test_security_headers_present(): void
    {
        $inst = $this->institute('Sec Headers');
        $owner = $this->owner('step99-headers-'.uniqid().'@example.test');
        $this->assign($owner, $inst, 'institute-owner');

        $response = $this->asUser($owner, (int) $inst->id)
            ->get(route('accounting.security.index'));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Content-Security-Policy');
    }

    public function test_permission_system_has_roles(): void
    {
        $roles = Role::all();
        $this->assertNotEmpty($roles, 'Roles table should be seeded');

        $ownerRole = Role::where('slug', 'institute-owner')->first();
        $this->assertNotNull($ownerRole, 'institute-owner role must exist');

        $permissions = Permission::all();
        $this->assertNotEmpty($permissions, 'Permissions table should be seeded');

        $ownerPermissions = $ownerRole->permissions;
        $this->assertNotEmpty($ownerPermissions, 'institute-owner role should have permissions');
    }
}
