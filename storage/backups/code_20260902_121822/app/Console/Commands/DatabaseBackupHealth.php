<?php

namespace App\Console\Commands;

use App\Services\System\BackupHealthService;
use Illuminate\Console\Command;

/**
 * Step 125-D — Backup Health Command.
 */
class DatabaseBackupHealth extends Command
{
    protected $signature = 'database:backup-health
        {--json : Output as JSON}';

    protected $description = 'Check backup health, RPO compliance, and recovery readiness (read-only)';

    public function handle(BackupHealthService $service): int
    {
        $report = $service->check();
        $jsonOutput = $this->option('json');

        if ($jsonOutput) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->line('');
        $this->line('BACKUP HEALTH');
        $this->line(str_repeat('=', 50));
        $this->line("Overall: {$report['overall']}");
        $this->line('');

        foreach ($report['checks'] as $name => $check) {
            $status = $check['status'] ?? 'UNKNOWN';
            $label = ucfirst(str_replace('_', ' ', $name));
            $this->line("  {$label}: {$status}");
            if (isset($check['message'])) {
                $this->line("    {$check['message']}");
            }
        }

        $this->line('');
        return self::SUCCESS;
    }
}
