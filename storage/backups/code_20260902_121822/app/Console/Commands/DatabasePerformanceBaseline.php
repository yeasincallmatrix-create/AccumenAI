<?php

namespace App\Console\Commands;

use App\Services\System\DatabasePerformanceBaselineService;
use Illuminate\Console\Command;

/**
 * Step 123-A — Performance Baseline Command.
 *
 * php artisan database:performance-baseline --json
 */
class DatabasePerformanceBaseline extends Command
{
    protected $signature = 'database:performance-baseline {--json : Output as JSON}';
    protected $description = 'Step 123-A — Database performance baseline report (read-only)';

    public function handle(DatabasePerformanceBaselineService $baseline): int
    {
        $data = $baseline->baseline();

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT));
            return 0;
        }

        $this->info('DATABASE PERFORMANCE BASELINE');
        $this->line(str_repeat('=', 60));
        $this->line('Generated: ' . $data['generated_at']);
        $this->line('Tables: ' . $data['table_count'] . ' | Total rows: ' . number_format($data['total_rows']));
        $this->line('Health: ' . $data['health']['score'] . '/100 (' . $data['health']['status'] . ')');
        $this->line('');

        $this->info('Largest Tables:');
        foreach ($data['largest_tables'] as $name => $count) {
            $this->line('  ' . str_pad($name, 40) . number_format($count) . ' rows');
        }

        $this->line('');
        $this->info('Tenant-Scoped Tables (top 10):');
        $tenantSlice = array_slice($data['tenant_scoped_tables'], 0, 10, true);
        foreach ($tenantSlice as $name => $info) {
            $this->line('  ' . str_pad($name, 40) . number_format($info['row_count']) . ' rows, ' . $info['tenant_count'] . ' tenants');
        }

        $this->line('');
        $this->info('Index Summary:');
        $this->line('  Duplicate indexes: ' . $data['health']['duplicate_count']);
        $this->line('  Missing FK indexes: ' . $data['health']['missing_fk_count']);

        if (! empty($data['recommended_indexes'])) {
            $this->line('');
            $this->info('Recommendations (recommendation only):');
            foreach ($data['recommended_indexes'] as $rec) {
                $this->line('  [' . $rec['recommendation'] . '] ' . $rec['table'] . '(' . $rec['columns'] . ') — impact: ' . $rec['estimated_impact'] . ', risk: ' . $rec['duplicate_risk']);
            }
        }

        $this->line('');
        $this->info('Query Log (24h):');
        $q = $data['query_log_stats']['last_24h'] ?? [];
        $this->line('  Total: ' . ($q['total_queries'] ?? 0) . ' | Slow: ' . ($q['slow_query_count'] ?? 0) . ' | Failed: ' . ($q['failed_query_count'] ?? 0) . ' | Avg: ' . ($q['average_execution_time'] ?? 0) . 'ms');

        $this->line('');
        $this->warn('Baseline only. No changes were made.');
        return 0;
    }
}
