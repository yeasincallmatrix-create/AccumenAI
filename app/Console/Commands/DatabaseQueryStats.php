<?php

namespace App\Console\Commands;

use App\Services\System\ProductionQueryMetricsService;
use Illuminate\Console\Command;

class DatabaseQueryStats extends Command
{
    protected $signature = 'database:query-stats {--json} {--limit=10}';

    protected $description = 'Step 124-D — Production Query Metrics (read-only)';

    public function handle(ProductionQueryMetricsService $service): int
    {
        $stats = $service->stats(24, (int)$this->option('limit'));

        if ($this->option('json')) {
            $this->line(json_encode($stats, JSON_PRETTY_PRINT));
            return 0;
        }

        $this->info("QUERY STATS (24h)");
        $this->line("Total: {$stats['total_queries']} | SELECT: {$stats['select_count']} | INSERT: {$stats['insert_count']} | UPDATE: {$stats['update_count']} | DELETE: {$stats['delete_count']}");
        $this->line("Failed: {$stats['failed_queries']} | Slow: {$stats['slow_queries']} | Avg: {$stats['average_duration']}ms | p95: {$stats['p95_duration']}ms | p99: {$stats['p99_duration']}ms | Max: {$stats['maximum_duration']}ms");
        $this->line("Per minute: {$stats['queries_per_minute']} | Per hour: {$stats['queries_per_hour']}");

        return 0;
    }
}
