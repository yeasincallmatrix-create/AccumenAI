<?php

namespace App\Console\Commands;

use App\Services\System\BackupService;
use Illuminate\Console\Command;

/**
 * Step 122-J — Backup Status Command.
 *
 * php artisan database:backup-status --json
 */
class DatabaseBackupStatusCommand extends Command
{
    protected $signature = 'database:backup-status
        {--json : Output as JSON}';

    protected $description = 'Show backup automation status (read-only)';

    public function handle(BackupService $backupService): int
    {
        $stats = $backupService->getBackupStats();
        $jsonOutput = $this->option('json');

        if ($jsonOutput) {
            $this->line(json_encode($stats, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->line('');
        $this->line('BACKUP AUTOMATION STATUS');
        $this->line(str_repeat('=', 50));

        $this->line('');
        $this->line('Schedule:');
        $this->line('  Daily:   ' . ($stats['daily_enabled'] ? 'ENABLED' : 'DISABLED') . ' at ' . $stats['daily_schedule']);
        $this->line('  Weekly:  ' . ($stats['weekly_enabled'] ? 'ENABLED' : 'DISABLED') . ' on ' . ucfirst($stats['weekly_day']) . ' at ' . $stats['weekly_schedule']);
        $this->line('  Verification: ' . ($stats['verification_enabled'] ? 'ENABLED' : 'DISABLED'));

        $this->line('');
        $this->line('Backups:');
        $this->line('  Total verified:  ' . $stats['total_verified']);
        $this->line('  Total failed:    ' . $stats['total_failed']);
        $this->line('  Total completed: ' . $stats['total_completed']);

        if ($stats['latest_backup']) {
            $lb = $stats['latest_backup'];
            $this->line('');
            $this->line('Latest Backup:');
            $this->line('  File:     ' . $lb['filename']);
            $this->line('  Type:     ' . $lb['type']);
            $this->line('  Status:   ' . strtoupper($lb['status']));
            $this->line('  Size:     ' . number_format($lb['size_bytes']) . ' bytes');
            $this->line('  Created:  ' . $lb['created_at']);
        }

        if ($stats['latest_verified']) {
            $lv = $stats['latest_verified'];
            $this->line('');
            $this->line('Latest Verified:');
            $this->line('  File:     ' . $lv['filename']);
            $this->line('  Type:     ' . $lv['type']);
            $this->line('  Created:  ' . $lv['created_at']);
            $this->line('  Size:     ' . number_format($lv['size_bytes']) . ' bytes');
            $this->line('  SHA256:   ' . $lv['checksum']);
        }

        if ($stats['latest_failed']) {
            $lf = $stats['latest_failed'];
            $this->line('');
            $this->line('Latest Failed:');
            $this->line('  File:     ' . $lf['filename']);
            $this->line('  Type:     ' . $lf['type']);
            $this->line('  Created:  ' . $lf['created_at']);
            $this->line('  Reason:   ' . $lf['reason']);
        }

        $this->line('');
        $this->line('Health:');
        $this->line('  Backup age:        ' . ($stats['backup_age_hours'] !== null ? $stats['backup_age_hours'] . ' hours' : 'N/A'));
        $this->line('  Running:           ' . ($stats['is_running'] ? 'YES' : 'NO'));
        $this->line('  No verified:       ' . ($stats['no_verified_backup'] ? 'WARNING' : 'OK'));
        $this->line('  Age warning:       ' . ($stats['age_warning'] ? 'WARNING (>' . config('backup.notification_threshold_hours', 48) . 'h)' : 'OK'));
        $this->line('');

        return self::SUCCESS;
    }
}
