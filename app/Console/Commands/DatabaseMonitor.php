<?php

namespace App\Console\Commands;

use App\Services\System\DatabaseMonitoringService;
use Illuminate\Console\Command;

class DatabaseMonitor extends Command
{
    protected $signature = 'database:monitor {--json : JSON output} {--refresh : Force refresh (ignore cache)}';

    protected $description = 'Step 121 — Database Monitoring (read-only)';

    public function handle(DatabaseMonitoringService $service): int
    {
        $snapshot = $service->snapshot(useCache: ! $this->option('refresh'));

        if ($this->option('json')) {
            $this->line(json_encode($snapshot, JSON_PRETTY_PRINT));
            return 0;
        }

        $this->info("DATABASE MONITORING SNAPSHOT");
        $this->line("Generated at: {$snapshot['generated_at']}");
        $this->line("Health: {$snapshot['health']['status']} ({$snapshot['health']['score']})");
        $this->line("Certification: {$snapshot['certification']['status']} ({$snapshot['certification']['overall_score']})");
        $this->line("Backup: {$snapshot['backup']['status']} (count {$snapshot['backup']['backup_count']})");
        $this->line("Performance: slow {$snapshot['performance']['slow_query_count']}, failed {$snapshot['performance']['failed_query_count']}, avg {$snapshot['performance']['average_query_time']}ms");
        $this->line("Indexes missing: ".count($snapshot['indexes']['audit']['missing'] ?? [])." (RECOMMENDATION ONLY)");
        $this->line("Orphans: ".($snapshot['health']['orphan_status'] ?? 'unknown'));
        $this->line("Use --json for full details");

        return 0;
    }
}
