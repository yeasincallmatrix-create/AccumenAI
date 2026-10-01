<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BackupAutoCommand extends Command
{
    protected $signature = 'backup:auto
                            {--tenant= : Backup single tenant only}
                            {--dry-run : List tenants, no backup}
                            {--force : Skip disk space check}';
    protected $description = 'Automatically backup all active tenants (weekly schedule)';

    public function __construct(private BackupService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!config('backup.auto_backup_enabled', true)) {
            $this->warn('Auto-backup disabled in config.');
            return self::SUCCESS;
        }

        // Get tenants (institutes has no owner_user_id — owner resolved separately)
        $query = DB::table('institutes')->where('status', 'active');
        if ($this->option('tenant')) {
            $query->where('id', $this->option('tenant'));
        }
        $tenants = $query->get(['id', 'name']);

        $this->info("Auto-backup: {$tenants->count()} active tenant(s)");

        if ($this->option('dry-run')) {
            foreach ($tenants as $t) {
                $ownerId = $this->getOwnerId($t->id);
                $this->line(sprintf(
                    '  [%d] %s — owner: %s',
                    $t->id,
                    $t->name,
                    $ownerId ?: 'NONE (would skip)'
                ));
            }
            $this->info('Dry-run complete. No backups created.');
            return self::SUCCESS;
        }

        if ($tenants->isEmpty()) {
            $this->warn('No active tenants to backup.');
            return self::SUCCESS;
        }

        // Disk space check
        $freeMb = disk_free_space(storage_path()) / 1024 / 1024;
        $refuseMb = (int) config('backup.refuse_free_space_mb', 512);
        $warnMb = (int) config('backup.min_free_space_mb', 1024);

        if ($freeMb < $refuseMb && !$this->option('force')) {
            $this->error("Disk critically low ({$freeMb} MB free < {$refuseMb} MB refuse threshold). ABORT.");
            Log::critical('Auto-backup aborted: disk critically low', ['free_mb' => $freeMb]);
            return self::FAILURE;
        }

        if ($freeMb < $warnMb) {
            $this->warn("Disk low ({$freeMb} MB free). Proceeding but may fail.");
            Log::warning('Auto-backup running with low disk', ['free_mb' => $freeMb]);
        }

        $success = 0;
        $failed = 0;

        foreach ($tenants as $tenant) {
            try {
                $ownerId = $this->getOwnerId($tenant->id);
                if (!$ownerId) {
                    $this->warn("  [{$tenant->id}] {$tenant->name}: no owner, skipping");
                    $failed++;
                    continue;
                }

                $backup = $this->service->createBackup($tenant->id, $ownerId);
                $this->line(sprintf(
                    '  [%d] OK %s (%s MB)',
                    $tenant->id,
                    $backup->filename,
                    number_format($backup->size_bytes / 1048576, 2)
                ));
                $success++;

            } catch (\Throwable $e) {
                $this->error("  [{$tenant->id}] FAIL: {$e->getMessage()}");
                Log::error('Auto-backup failed for tenant', [
                    'tenant_id' => $tenant->id,
                    'error'     => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        $this->info("Auto-backup complete: {$success} success, {$failed} failed");

        // Enforce retention + max after backup run
        $this->call('backup:cleanup');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Resolve tenant owner from institute_users.
     * Schema: institute_users.institute_id + role_id (1 = "Institute Owner").
     * institutes has no owner_user_id column.
     */
    private function getOwnerId(int $tenantId): ?int
    {
        $id = DB::table('institute_users')
            ->where('institute_id', $tenantId)
            ->where('role_id', 1)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }
}
