<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DatabaseOperationsDashboardTest extends TestCase
{
    use DatabaseTransactions;

    private PlatformAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = PlatformAdmin::first() ?? PlatformAdmin::firstOrReuseForTests([
            'name' => 'SA Dashboard Test',
            'email' => 'sa-dash-' . uniqid() . '@test.com',
            'password_hash' => bcrypt('secret'),
        ]);
    }

    // 1. Super Admin can access dashboard
    public function test_super_admin_can_access_dashboard(): void
    {
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.dashboard'))
            ->assertOk();
    }

    // 2. Super Admin can access backups page
    public function test_super_admin_can_access_backups(): void
    {
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.backups'))
            ->assertOk();
    }

    // 3. Super Admin can access recovery page
    public function test_super_admin_can_access_recovery(): void
    {
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.recovery'))
            ->assertOk();
    }

    // 4. Super Admin can access health page
    public function test_super_admin_can_access_health(): void
    {
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.health'))
            ->assertOk();
    }

    // 5. Super Admin can access performance page
    public function test_super_admin_can_access_performance(): void
    {
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.performance'))
            ->assertOk();
    }

    // 6. Super Admin can access integrity page
    public function test_super_admin_can_access_integrity(): void
    {
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.integrity'))
            ->assertOk();
    }

    // 7. Super Admin can access certification page
    public function test_super_admin_can_access_certification(): void
    {
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.certification'))
            ->assertOk();
    }

    // 8. Super Admin can access audit page
    public function test_super_admin_can_access_audit(): void
    {
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.audit'))
            ->assertOk();
    }

    // 9. Normal user denied from dashboard
    public function test_normal_user_denied_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web')
            ->get(route('super-admin.database.dashboard'))
            ->assertRedirect();
    }

    // 10. Normal user denied from backups
    public function test_normal_user_denied_backups(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web')
            ->get(route('super-admin.database.backups'))
            ->assertRedirect();
    }

    // 11. Normal user denied from health
    public function test_normal_user_denied_health(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web')
            ->get(route('super-admin.database.health'))
            ->assertRedirect();
    }

    // 12. Normal user denied from certification
    public function test_normal_user_denied_certification(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web')
            ->get(route('super-admin.database.certification'))
            ->assertRedirect();
    }

    // 13. Unauthenticated user redirected
    public function test_unauthenticated_redirected(): void
    {
        $this->get(route('super-admin.database.dashboard'))
            ->assertRedirect();
    }

    // 14. Dashboard contains certification score
    public function test_dashboard_contains_cert_score(): void
    {
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.dashboard'))
            ->assertSee('Certification Score')
            ->assertSee('Database Health');
    }

    // 15. Backups page contains backup table
    public function test_backups_page_contains_table(): void
    {
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.backups'))
            ->assertSee('Backup History')
            ->assertSee('Retention Policy');
    }

    // 16. Health page contains migrations section
    public function test_health_page_contains_migrations(): void
    {
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.health'))
            ->assertSee('Migrations')
            ->assertSee('Foreign Keys')
            ->assertSee('Tenant Isolation');
    }

    // 17. Performance page contains query performance
    public function test_performance_page_contains_queries(): void
    {
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.performance'))
            ->assertSee('Query Performance')
            ->assertSee('Index Recommendations')
            ->assertSee('N+1 Detection');
    }

    // 18. Integrity page contains tenant isolation
    public function test_integrity_page_contains_tenant(): void
    {
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.integrity'))
            ->assertSee('Tenant Isolation')
            ->assertSee('Accounting Integrity')
            ->assertSee('Inventory Integrity');
    }

    // 19. Certification page contains scorecard
    public function test_certification_page_contains_score(): void
    {
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.certification'))
            ->assertSee('Overall Score')
            ->assertSee('Category Scores');
    }

    // 20. Audit page contains log table
    public function test_audit_page_contains_logs(): void
    {
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.audit'))
            ->assertSee('Database Audit Logs');
    }

    // 21. Recovery page contains RPO/RTO
    public function test_recovery_page_contains_rpo_rto(): void
    {
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.recovery'))
            ->assertSee('Recovery Point Objective')
            ->assertSee('Recovery Time Objective')
            ->assertSee('Restore Drill');
    }

    // 22. Dashboard refresh works
    public function test_dashboard_refresh(): void
    {
        $this->actingAs($this->admin, 'platform_admin')
            ->post(route('super-admin.database.refresh'))
            ->assertRedirect();
    }

    // 23. JSON status endpoint
    public function test_json_status_endpoint(): void
    {
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.status'))
            ->assertOk()
            ->assertJsonStructure(['generated_at', 'health_status', 'cert_status']);
    }

    // 24. Backup creation authorization
    public function test_backup_creation_requires_auth(): void
    {
        $this->post(route('super-admin.database.backups.create'), ['type' => 'manual'])
            ->assertRedirect();
    }

    // 25. Previous database tests remain passing
    public function test_previous_steps_remain_passing(): void
    {
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.monitoring'))
            ->assertOk();
    }

    // 26. Sidebar contains database navigation
    public function test_sidebar_contains_database_nav(): void
    {
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.dashboard'))
            ->assertSee('Database Dashboard')
            ->assertSee('Database Health')
            ->assertSee('Performance')
            ->assertSee('Audit Logs');
    }
}
