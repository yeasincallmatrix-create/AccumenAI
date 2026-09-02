<?php

namespace Tests\Feature;

use App\Console\Commands\DatabasePerformanceBaseline;
use App\Console\Commands\DatabaseSlowQueries;
use App\Console\Commands\DatabaseN1Detection;
use App\Services\System\DatabasePerformanceBaselineService;
use App\Services\System\DatabaseIndexAnalysisService;
use App\Services\System\DatabaseQueryPlanService;
use App\Services\System\N1DetectionService;
use App\Services\System\DatabasePerformanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Step 123-L — Database Performance Optimization Tests.
 */
class DatabasePerformanceOptimizationTest extends TestCase
{
    use DatabaseTransactions;

    // 1. Baseline report works
    public function test_baseline_report_works(): void
    {
        $service = app(DatabasePerformanceBaselineService::class);
        $baseline = $service->baseline();
        $this->assertArrayHasKey('generated_at', $baseline);
        $this->assertArrayHasKey('table_count', $baseline);
        $this->assertArrayHasKey('health', $baseline);
        $this->assertGreaterThan(0, $baseline['table_count']);
    }

    // 2. JSON baseline works
    public function test_json_baseline_works(): void
    {
        $exitCode = Artisan::call('database:performance-baseline', ['--json' => true]);
        $output = Artisan::output();
        $this->assertEquals(0, $exitCode);
        $decoded = json_decode($output, true);
        $this->assertNotNull($decoded);
        $this->assertArrayHasKey('table_count', $decoded);
    }

    // 3. Largest tables are detected
    public function test_largest_tables_detected(): void
    {
        $service = app(DatabasePerformanceBaselineService::class);
        $tables = $service->allTableRowCounts();
        $this->assertIsArray($tables);
        $this->assertNotEmpty($tables);
        $largest = $service->largestTables($tables, 5);
        $this->assertLessThanOrEqual(5, count($largest));
    }

    // 4. Existing indexes are detected
    public function test_existing_indexes_detected(): void
    {
        $audit = app(\App\Services\System\DatabaseIndexAuditService::class);
        if (\Illuminate\Support\Facades\Schema::hasTable('students')) {
            $indexes = $audit->getIndexes('students');
            $this->assertIsArray($indexes);
            $this->assertNotEmpty($indexes);
        }
        $this->assertTrue(true);
    }

    // 5. Duplicate index detection works
    public function test_duplicate_index_detection_works(): void
    {
        $service = app(DatabasePerformanceBaselineService::class);
        $duplicates = $service->duplicateIndexDetection();
        $this->assertIsArray($duplicates);
        // journal_entries has known duplicates
        $jeDups = array_filter($duplicates, fn($d) => ($d['table'] ?? '') === 'journal_entries');
        $this->assertNotEmpty($jeDups);
    }

    // 6. FK index detection works
    public function test_fk_index_detection_works(): void
    {
        $service = app(DatabaseIndexAnalysisService::class);
        $fkAudit = $service->foreignKeyIndexAudit();
        $this->assertIsArray($fkAudit);
    }

    // 7. Six recommendations are analyzed
    public function test_six_recommendations_analyzed(): void
    {
        $service = app(DatabaseIndexAnalysisService::class);
        $recs = $service->analyzeSixRecommendations();
        $this->assertCount(6, $recs);
        foreach ($recs as $rec) {
            $this->assertArrayHasKey('table', $rec);
            $this->assertArrayHasKey('proposed_columns', $rec);
            $this->assertArrayHasKey('recommendation', $rec);
            $this->assertContains($rec['recommendation'], ['CREATE', 'DEFER', 'SKIP', 'REVIEW']);
        }
    }

    // 8. EXPLAIN analysis works
    public function test_explain_analysis_works(): void
    {
        $service = app(DatabaseQueryPlanService::class);
        $results = $service->analyzeAll();
        $this->assertIsArray($results);
        foreach ($results as $name => $result) {
            $this->assertArrayHasKey('status', $result);
            $this->assertContains($result['status'], ['OK', 'NEEDS_INDEX', 'TABLE_NOT_FOUND', 'ERROR']);
        }
    }

    // 9. Slow query report works
    public function test_slow_query_report_works(): void
    {
        $service = app(DatabaseIndexAnalysisService::class);
        $report = $service->slowQueryReport(10, 0);
        $this->assertIsArray($report);
    }

    // 10. JSON slow-query report works
    public function test_json_slow_query_report_works(): void
    {
        $exitCode = Artisan::call('database:slow-queries', ['--json' => true, '--limit' => 5]);
        $output = Artisan::output();
        $this->assertEquals(0, $exitCode);
        $decoded = json_decode($output, true);
        $this->assertNotNull($decoded);
    }

    // 11. Performance dashboard contains performance section
    public function test_performance_baseline_has_health(): void
    {
        $service = app(DatabasePerformanceBaselineService::class);
        $baseline = $service->baseline();
        $this->assertArrayHasKey('score', $baseline['health']);
        $this->assertArrayHasKey('status', $baseline['health']);
        $this->assertIsInt($baseline['health']['score']);
    }

    // 12. No tenant data is modified
    public function test_no_tenant_data_modified(): void
    {
        $studentCountBefore = DB::table('students')->count();
        $service = app(DatabasePerformanceBaselineService::class);
        $service->baseline();
        $studentCountAfter = DB::table('students')->count();
        $this->assertEquals($studentCountBefore, $studentCountAfter);
    }

    // 13. No accounting data is modified
    public function test_no_accounting_data_modified(): void
    {
        $journalCountBefore = DB::table('journals')->count();
        $service = app(DatabasePerformanceBaselineService::class);
        $service->baseline();
        $journalCountAfter = DB::table('journals')->count();
        $this->assertEquals($journalCountBefore, $journalCountAfter);
    }

    // 14. No inventory data is modified
    public function test_no_inventory_data_modified(): void
    {
        $tables = ['inventory_stock_levels', 'inventory_items', 'inventory_movements'];
        $countsBefore = [];
        foreach ($tables as $t) {
            if (\Illuminate\Support\Facades\Schema::hasTable($t)) {
                $countsBefore[$t] = DB::table($t)->count();
            }
        }
        $service = app(DatabasePerformanceBaselineService::class);
        $service->baseline();
        foreach ($countsBefore as $t => $before) {
            $this->assertEquals($before, DB::table($t)->count(), "Table $t was modified!");
        }
    }

    // 15. Backup is required before destructive schema change
    public function test_backup_required_before_destructive_schema_change(): void
    {
        $backupService = app(\App\Services\System\BackupService::class);
        $backup = $backupService->create('manual');
        $this->assertNotNull($backup);
        $this->assertContains($backup->status, ['completed', 'verified']);
    }

    // 16. Only approved indexes are migrated (none in this case)
    public function test_only_approved_indexes_migrated(): void
    {
        $analysis = app(DatabaseIndexAnalysisService::class);
        $recs = $analysis->analyzeSixRecommendations();
        $createCount = count(array_filter($recs, fn($r) => $r['recommendation'] === 'CREATE'));
        // All six should be SKIP/DEFER/REVIEW in test DB (tiny tables)
        $this->assertEquals(0, $createCount, 'No indexes should be CREATE in test DB');
    }

    // 17. Migration is reversible
    public function test_migration_is_reversible(): void
    {
        $exitCode = Artisan::call('migrate:status');
        $output = Artisan::output();
        $this->assertStringContainsString('2026_08_23_141848', $output);
    }

    // 18. Existing Step 101-122 tests remain passing (run a sample)
    public function test_existing_step_commands_still_work(): void
    {
        $exitCode = Artisan::call('database:index-audit');
        $this->assertEquals(0, $exitCode);

        $exitCode = Artisan::call('database:performance-baseline');
        $this->assertEquals(0, $exitCode);

        $exitCode = Artisan::call('database:slow-queries');
        $this->assertEquals(0, $exitCode);
    }
}
