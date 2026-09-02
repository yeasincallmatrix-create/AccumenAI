<?php

namespace App\Console\Commands;

use App\Services\System\BackupRetentionService;
use Illuminate\Console\Command;

/**
 * Step 125-B — Backup Retention Command.
 * Default is dry-run. --execute required for actual deletion.
 */
class DatabaseBackupRetention extends Command
{
    protected $signature = 'database:backup-retention
        {--json : Output as JSON}
        {--dry-run : Show what would be deleted (default)}
        {--execute : Actually delete expired backup files}';

    protected $description = 'Manage backup retention policy — dry-run by default';

    public function handle(BackupRetentionService $service): int
    {
        $jsonOutput = $this->option('json');
        $execute = $this->option('execute');

        if ($execute) {
            $result = $service->deleteExpired();
            if ($jsonOutput) {
                $this->line(json_encode($result, JSON_PRETTY_PRINT));
            } else {
                $this->line("Deleted {$result['deleted_count']} expired backup files.");
                foreach ($result['deleted'] as $d) {
                    $this->line("  - {$d['filename']} (file_deleted: " . ($d['file_deleted'] ? 'yes' : 'no') . ")");
                }
            }
            return self::SUCCESS;
        }

        $report = $service->report();

        if ($jsonOutput) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->line('');
        $this->line('BACKUP RETENTION POLICY (DRY-RUN)');
        $this->line(str_repeat('=', 55));

        $policy = $report['policy'];
        $this->line('');
        $this->line('Retention Policy:');
        $this->line('  Daily:    ' . ($policy['daily']['enabled'] ?? false ? 'KEEP ' . ($policy['daily']['retain_days'] ?? 14) . ' days' : 'DISABLED'));
        $this->line('  Weekly:   ' . ($policy['weekly']['enabled'] ?? false ? 'KEEP ' . ($policy['weekly']['retain_weeks'] ?? 8) . ' weeks' : 'DISABLED'));
        $this->line('  Monthly:  ' . ($policy['monthly']['enabled'] ?? false ? 'KEEP ' . ($policy['monthly']['retain_months'] ?? 12) . ' months' : 'DISABLED'));
        $this->line('  Manual:   ' . (($policy['manual']['retain_indefinitely'] ?? true) ? 'KEEP INDEFINITELY' : 'APPLY RETENTION'));
        $this->line('  Pre-op:   KEEP ' . ($policy['pre_operation']['retain_days'] ?? 30) . ' days');
        $this->line('  Max storage: ' . $this->formatBytes($policy['max_storage_bytes'] ?? 0));

        $this->line('');
        $this->line('Storage:');
        $this->line('  Total backups:     ' . $report['total_backups']);
        $this->line('  Protected:         ' . $report['protected_backups']);
        $this->line('  Current size:      ' . $report['storage']['total_mb'] . ' MB');
        $this->line('  Within limit:      ' . ($report['storage_within_limit'] ? 'YES' : 'NO'));

        $this->line('');
        $this->line("Expired backups: {$report['expired_count']}");
        if ($report['expired_count'] > 0) {
            foreach ($report['deletion_candidates'] as $d) {
                $this->line("  - [{$d['id']}] {$d['filename']} (type: {$d['type']}, created: {$d['created_at']})");
            }
            $this->line('');
            $this->line('Use --execute to delete expired backup files.');
        } else {
            $this->line('  No expired backups found.');
        }

        $this->line('');
        return self::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) return round($bytes / 1024 / 1024 / 1024, 2) . ' GB';
        if ($bytes >= 1024 * 1024) return round($bytes / 1024 / 1024, 2) . ' MB';
        return round($bytes / 1024, 2) . ' KB';
    }
}
