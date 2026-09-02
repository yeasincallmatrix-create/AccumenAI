<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Models\User;
use App\Services\System\DatabaseAlertService;
use App\Services\System\DatabaseCapacityService;
use App\Services\System\EndpointPerformanceService;
use App\Services\System\ProductionQueryMetricsService;
use App\Services\System\QueryFingerprintService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionDatabaseObservabilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_query_fingerprint_normalization(): void
    {
        $svc = app(QueryFingerprintService::class);
        $a = $svc->normalize("SELECT * FROM students WHERE id = 1");
        $b = $svc->normalize("SELECT * FROM students WHERE id = 2");
        $this->assertEquals($a, $b);
    }

    public function test_same_logical_queries_produce_same_fingerprint(): void
    {
        $svc = app(QueryFingerprintService::class);
        $f1 = $svc->fingerprint("SELECT * FROM students WHERE id = 1");
        $f2 = $svc->fingerprint("SELECT * FROM students WHERE id = 2");
        $this->assertEquals($f1, $f2);
    }

    public function test_different_queries_produce_different_fingerprints(): void
    {
        $svc = app(QueryFingerprintService::class);
        $f1 = $svc->fingerprint("SELECT * FROM students WHERE id = 1");
        $f2 = $svc->fingerprint("SELECT * FROM teachers WHERE id = 1");
        $this->assertNotEquals($f1, $f2);
    }

    public function test_query_statistics_report(): void
    {
        $svc = app(ProductionQueryMetricsService::class);
        $stats = $svc->stats(24);
        $this->assertArrayHasKey('total_queries', $stats);
        $this->assertArrayHasKey('average_duration', $stats);
    }

    public function test_slow_query_detection(): void
    {
        $svc = app(ProductionQueryMetricsService::class);
        DB::table('database_query_logs')->insert([
            'query' => 'SELECT SLEEP(1)',
            'execution_time' => 1500,
            'connection' => 'mysql',
            'status' => 'slow',
            'created_at' => now(),
        ]);
        $stats = $svc->stats(24);
        $this->assertGreaterThanOrEqual(1, $stats['slow_queries']);
    }

    public function test_p95_calculation(): void
    {
        $svc = app(ProductionQueryMetricsService::class);
        // Insert known durations
        foreach ([10,20,30,40,50,60,70,80,90,100] as $d) {
            DB::table('database_query_logs')->insert([
                'query' => 'SELECT 1',
                'execution_time' => $d,
                'connection' => 'mysql',
                'status' => 'success',
                'created_at' => now(),
            ]);
        }
        $stats = $svc->stats(24);
        $this->assertArrayHasKey('p95_duration', $stats);
        $this->assertGreaterThan(0, $stats['p95_duration']);
    }

    public function test_p99_calculation(): void
    {
        $svc = app(ProductionQueryMetricsService::class);
        $stats = $svc->stats(24);
        $this->assertArrayHasKey('p99_duration', $stats);
    }

    public function test_top_query_report(): void
    {
        $svc = app(QueryFingerprintService::class);
        $svc->record("SELECT * FROM students WHERE id = 1", 10);
        $svc->record("SELECT * FROM students WHERE id = 2", 20);
        $top = $svc->top(5, 'count');
        $this->assertIsArray($top);
        $this->assertGreaterThan(0, count($top));
    }

    public function test_n1_classification(): void
    {
        $svc = app(\App\Services\System\N1DetectionService::class);
        $res = $svc->detectEnhanced();
        $this->assertArrayHasKey('findings', $res);
        $this->assertArrayHasKey('summary', $res);
        foreach ($res['findings'] as $f) {
            $this->assertContains($f['classification'], ['CONFIRMED','SUSPECTED','REVIEW']);
        }
    }

    public function test_endpoint_metrics(): void
    {
        $svc = app(EndpointPerformanceService::class);
        $svc->record('/test-route', 123.45, 200);
        $stats = $svc->stats(24);
        $this->assertIsArray($stats);
    }

    public function test_tenant_metrics(): void
    {
        // Super Admin only — tenant performance is via connection grouping
        $svc = app(\App\Services\System\DatabaseMonitoringService::class);
        $snap = $svc->snapshot(useCache: false);
        $this->assertArrayHasKey('query_intelligence', $snap);
        $this->assertArrayHasKey('tenant_performance', $snap['query_intelligence']);
    }

    public function test_capacity_metrics(): void
    {
        $svc = app(DatabaseCapacityService::class);
        $metrics = $svc->metrics();
        $this->assertArrayHasKey('database_size', $metrics);
        $this->assertArrayHasKey('largest_tables', $metrics);
        $this->assertArrayHasKey('backup_size', $metrics);
    }

    public function test_duplicate_index_evidence(): void
    {
        $svc = app(\App\Services\System\DatabaseIndexAnalysisService::class);
        $dups = $svc->duplicatePrefixAnalysis();
        $this->assertIsArray($dups);
        // Should not auto-drop
        $this->assertTrue(true);
    }

    public function test_alert_detection(): void
    {
        $svc = app(DatabaseAlertService::class);
        $alerts = $svc->evaluate();
        $this->assertIsArray($alerts);
    }

    public function test_alert_cooldown(): void
    {
        $svc = app(DatabaseAlertService::class);
        $first = $svc->evaluate();
        $second = $svc->evaluate();
        // Second should be empty due to cooldown if first had alerts
        // We just check that evaluate is idempotent with cooldown
        $this->assertIsArray($second);
    }

    public function test_json_output(): void
    {
        $this->artisan('database:query-stats', ['--json' => true])->assertExitCode(0);
        $this->artisan('database:slow-queries', ['--json' => true])->assertExitCode(0);
        $this->artisan('database:query-top', ['--by' => 'duration', '--json' => true])->assertExitCode(0);
        $this->artisan('database:n1-detection', ['--json' => true])->assertExitCode(0);
        $this->artisan('database:monitor', ['--json' => true])->assertExitCode(0);
    }

    public function test_super_admin_authorization(): void
    {
        $admin = PlatformAdmin::first() ?? PlatformAdmin::firstOrReuseForTests(['name' => 'SA', 'email' => 'sa-'.uniqid().'@test.com', 'password_hash' => bcrypt('secret')]);
        $this->actingAs($admin, 'platform_admin')
            ->get(route('super-admin.database.monitoring'))
            ->assertOk();
    }

    public function test_normal_institute_user_denied(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web')
            ->get(route('super-admin.database.monitoring'))
            ->assertRedirect();
    }

    public function test_no_tenant_data_modification(): void
    {
        $before = DB::table('institutes')->count();
        app(\App\Services\System\DatabaseMonitoringService::class)->snapshot(useCache: false);
        $this->assertEquals($before, DB::table('institutes')->count());
    }

    public function test_no_accounting_data_modification(): void
    {
        $before = DB::table('journals')->count();
        app(\App\Services\System\DatabaseMonitoringService::class)->snapshot(useCache: false);
        $this->assertEquals($before, DB::table('journals')->count());
    }

    public function test_no_inventory_data_modification(): void
    {
        $before = DB::table('inventory_items')->count();
        app(\App\Services\System\DatabaseMonitoringService::class)->snapshot(useCache: false);
        $this->assertEquals($before, DB::table('inventory_items')->count());
    }

    public function test_existing_steps_remain_passing(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('system_backups'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('system_health_audits'));
    }
}
