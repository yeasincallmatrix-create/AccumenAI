<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Step 127 — Database Control Center Tests.
 */
class DatabaseControlCenterTest extends TestCase
{
    use DatabaseTransactions;

    private PlatformAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = PlatformAdmin::first() ?? PlatformAdmin::firstOrReuseForTests([
            'name' => 'SA Control Center Test',
            'email' => 'sa-cc-' . uniqid() . '@test.com',
            'password_hash' => bcrypt('secret'),
        ]);
    }

    // 1. Super Admin can access control center
    public function test_super_admin_can_access_control_center(): void
    {
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.control-center'))
            ->assertOk();
    }

    // 2. Unauthorized user cannot access control center
    public function test_unauthorized_user_cannot_access_control_center(): void
    {
        $this->get(route('super-admin.database.control-center'))
            ->assertRedirect();
    }

    // 3. Normal institute user cannot access control center
    public function test_institute_user_cannot_access_control_center(): void
    {
        $user = User::first() ?? User::create([
            'name' => 'Test User',
            'email' => 'test-user-' . uniqid() . '@test.com',
            'password_hash' => bcrypt('secret'),
        ]);
        $this->actingAs($user, 'institute_user')
            ->get(route('super-admin.database.control-center'))
            ->assertForbidden();
    }

    // 4. Dashboard renders all major sections
    public function test_dashboard_renders_all_sections(): void
    {
        $response = $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.control-center'));
        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('Database Control Center', $content);
        $this->assertStringContainsString('tab-overview', $content);
        $this->assertStringContainsString('tab-health', $content);
        $this->assertStringContainsString('tab-backup', $content);
        $this->assertStringContainsString('tab-performance', $content);
        $this->assertStringContainsString('tab-operations', $content);
        $this->assertStringContainsString('tab-events', $content);
    }

    // 5. Dashboard shows certification score
    public function test_dashboard_shows_certification_score(): void
    {
        $response = $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.control-center'));
        $response->assertOk();
        $this->assertStringContainsString('Certification Score', $response->getContent());
    }

    // 6. Dashboard shows health score
    public function test_dashboard_shows_health_score(): void
    {
        $response = $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.control-center'));
        $response->assertOk();
        $this->assertStringContainsString('Database Health', $response->getContent());
    }

    // 7. Dashboard shows backup section
    public function test_dashboard_shows_backup_section(): void
    {
        $response = $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.control-center'));
        $response->assertOk();
        $this->assertStringContainsString('Backup &amp; Recovery', $response->getContent());
    }

    // 8. Dashboard shows performance section
    public function test_dashboard_shows_performance_section(): void
    {
        $response = $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.control-center'));
        $response->assertOk();
        $this->assertStringContainsString('Query Metrics', $response->getContent());
    }

    // 9. Dashboard shows N+1 detection section
    public function test_dashboard_shows_n1_section(): void
    {
        $response = $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.control-center'));
        $response->assertOk();
        $this->assertStringContainsString('N+1 Detection', $response->getContent());
    }

    // 10. Dashboard shows operations tab
    public function test_dashboard_shows_operations_tab(): void
    {
        $response = $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.control-center'));
        $response->assertOk();
        $this->assertStringContainsString('Database Operations', $response->getContent());
    }

    // 11. Dashboard shows recent events
    public function test_dashboard_shows_recent_events(): void
    {
        $response = $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.control-center'));
        $response->assertOk();
        $this->assertStringContainsString('Recent Database Events', $response->getContent());
    }

    // 12. JSON endpoint works
    public function test_json_endpoint_works(): void
    {
        $response = $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.control-center.json'));
        $response->assertOk();
        $data = $response->json();
        $this->assertArrayHasKey('certification', $data);
        $this->assertArrayHasKey('health', $data);
        $this->assertArrayHasKey('query_metrics', $data);
    }

    // 13. Dashboard shows READ ONLY badge
    public function test_dashboard_shows_read_only_badge(): void
    {
        $response = $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.control-center'));
        $response->assertOk();
        $this->assertStringContainsString('READ ONLY', $response->getContent());
    }

    // 14. Dashboard shows data integrity section
    public function test_dashboard_shows_integrity_section(): void
    {
        $response = $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.control-center'));
        $response->assertOk();
        $this->assertStringContainsString('Data Integrity', $response->getContent());
    }

    // 15. Dashboard shows index recommendations
    public function test_dashboard_shows_index_recommendations(): void
    {
        $response = $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.control-center'));
        $response->assertOk();
        $this->assertStringContainsString('Index Recommendations', $response->getContent());
    }

    // 16. Dashboard shows duplicate index evidence
    public function test_dashboard_shows_duplicate_index_evidence(): void
    {
        $response = $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.control-center'));
        $response->assertOk();
        $this->assertStringContainsString('Duplicate Index', $response->getContent());
    }

    // 17. No tenant data is modified
    public function test_no_tenant_data_modified(): void
    {
        $before = \Illuminate\Support\Facades\DB::table('students')->count();
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.control-center'))
            ->assertOk();
        $after = \Illuminate\Support\Facades\DB::table('students')->count();
        $this->assertEquals($before, $after);
    }

    // 18. No accounting data is modified
    public function test_no_accounting_data_modified(): void
    {
        $before = \Illuminate\Support\Facades\DB::table('journals')->count();
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.control-center'))
            ->assertOk();
        $after = \Illuminate\Support\Facades\DB::table('journals')->count();
        $this->assertEquals($before, $after);
    }

    // 19. No inventory data is modified
    public function test_no_inventory_data_modified(): void
    {
        $before = \Illuminate\Support\Facades\DB::table('inventory_stock_levels')->count();
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.control-center'))
            ->assertOk();
        $after = \Illuminate\Support\Facades\DB::table('inventory_stock_levels')->count();
        $this->assertEquals($before, $after);
    }

    // 20. Existing dashboard route still works
    public function test_existing_dashboard_route_still_works(): void
    {
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.dashboard'))
            ->assertOk();
    }

    // 21. Existing monitoring route still works
    public function test_existing_monitoring_route_still_works(): void
    {
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.monitoring'))
            ->assertOk();
    }

    // 22. Existing health route still works
    public function test_existing_health_route_still_works(): void
    {
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('super-admin.database.health'))
            ->assertOk();
    }
}
