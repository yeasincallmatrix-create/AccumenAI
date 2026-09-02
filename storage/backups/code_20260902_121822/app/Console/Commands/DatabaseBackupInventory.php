<?php

namespace App\Console\Commands;

use App\Services\System\BackupInventoryService;
use Illuminate\Console\Command;

/**
 * Step 125-C — Backup Inventory Command.
 */
class DatabaseBackupInventory extends Command
{
    protected $signature = 'database:backup-inventory
        {--json : Output as JSON}';

    protected $description = 'Audit backup file inventory and detect issues (read-only)';

    public function handle(BackupInventoryService $service): int
    {
        $report = $service->inventory();
        $jsonOutput = $this->option('json');

        if ($jsonOutput) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->line('');
        $this->line('BACKUP INVENTORY');
        $this->line(str_repeat('=', 50));
        $this->line("Total backups:     {$report['total_backups']}");
        $this->line("Verified:          {$report['verified_count']}");
        $this->line("Failed:            {$report['failed_count']}");
        $this->line("Total size:        {$report['total_size_mb']} MB");
        $this->line("Issues found:      {$report['issues_count']}");

        if ($report['issues_count'] > 0) {
            $this->line('');
            $this->line('Issues:');
            foreach ($report['issues'] as $issue) {
                $this->line("  [{$issue['type']}] {$issue['filename']}: {$issue['message']}");
            }
        }

        $this->line('');
        return self::SUCCESS;
    }
}
