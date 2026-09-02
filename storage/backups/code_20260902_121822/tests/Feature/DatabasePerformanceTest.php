<?php

namespace Tests\Feature;

use App\Services\System\DatabasePerformanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabasePerformanceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_logs_slow_query(): void
    {
        $service = app(DatabasePerformanceService::class);
        $service->log('SELECT * FROM students WHERE id = 1', 600, 'mysql', 'slow');

        $this->assertDatabaseHas('database_query_logs', [
            'query' => 'SELECT * FROM students WHERE id = 1',
            'status' => 'slow',
        ]);
    }

    public function test_logs_failed_query(): void
    {
        $service = app(DatabasePerformanceService::class);
        $service->recordFailed('SELECT * FROM missing', 'Table not found');

        $this->assertDatabaseHas('database_query_logs', [
            'status' => 'failed',
        ]);
    }

    public function test_stats_returns_metrics(): void
    {
        $service = app(DatabasePerformanceService::class);
        $service->log('SELECT 1', 100);
        $service->log('SELECT 2', 700, 'mysql', 'slow');
        $service->recordFailed('SELECT 3', 'error');

        $stats = $service->stats(24);

        $this->assertArrayHasKey('slow_query_count', $stats);
        $this->assertArrayHasKey('average_execution_time', $stats);
        $this->assertArrayHasKey('database_errors', $stats);
        $this->assertIsInt($stats['slow_query_count']);
    }

    public function test_widget_returns_dashboard_data(): void
    {
        $service = app(DatabasePerformanceService::class);
        $widget = $service->widget();

        $this->assertEquals('Database Performance', $widget['title']);
        $this->assertArrayHasKey('slow_query_count', $widget);
        $this->assertArrayHasKey('average_execution_time', $widget);
        $this->assertArrayHasKey('database_errors', $widget);
    }

    public function test_slow_threshold(): void
    {
        $this->assertEquals(500, DatabasePerformanceService::SLOW_THRESHOLD_MS);
    }

    public function test_table_exists(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('database_query_logs'));
    }
}
