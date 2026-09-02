<?php

namespace App\Console\Commands;

use App\Services\System\TestDataCleanupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupTestData extends Command
{
    protected $signature = 'test:cleanup
                            {--dry-run : Preview without deleting}
                            {--execute : Execute destructive cleanup (requires APP_ENV=testing and explicit confirmation)}
                            {--force : Force execution without interactive confirmation (still requires environment guards)}';

    protected $description = 'Isolated test data cleanup — ONLY deletes records explicitly marked is_test=true. Protected: UNKNOWN/REAL => DO NOT DELETE.';

    public function handle(TestDataCleanupService $service): int
    {
        $dryRun = ! $this->option('execute');

        if ($this->option('dry-run')) {
            $dryRun = true;
        }

        try {
            $preview = $service->preview();
        } catch (\Throwable $e) {
            $this->error('SAFETY BLOCKED: '.$e->getMessage());
            Log::warning('test_cleanup.blocked', ['error' => $e->getMessage(), 'env' => app()->environment(), 'db' => config('database.connections.mysql.database')]);
            return self::FAILURE;
        }

        $this->info('TEST DATA CLEANUP — ISOLATED TO is_test=true ONLY');
        $this->line('Environment: '.app()->environment().' | Database: '.config('database.connections.mysql.database'));
        $this->line('Counts (only is_test=true will be deleted):');
        foreach ($preview['counts'] ?? [] as $k => $v) {
            if ($v !== null) $this->line("  {$k}: {$v}");
        }
        if (! empty($preview['email_pattern_counts_blocked'])) {
            $this->line('Email-pattern counts (BLOCKED, never used for deletion):');
            foreach ($preview['email_pattern_counts_blocked'] as $k => $v) {
                $this->line("  {$k}: {$v} (BLOCKED)");
            }
        }

        if ($dryRun) {
            $this->warn('DRY RUN — no records deleted. Use --execute to perform destructive cleanup (requires APP_ENV=testing).');
            return self::SUCCESS;
        }

        // Additional confirmation for destructive path
        if (! $this->option('force')) {
            if (! $this->confirm('You are about to PERMANENTLY DELETE all is_test=true records. This cannot be undone without a backup. Continue?')) {
                $this->info('Aborted.');
                return self::SUCCESS;
            }
        }

        try {
            $result = $service->execute(false);
            $this->info('Test cleanup completed.');
            $this->line('Backup: '.($result['backup']->filename ?? 'none'));
            foreach ($result['deleted'] ?? [] as $k => $v) {
                $this->line("  Deleted {$k}: {$v}");
            }
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Cleanup failed: '.$e->getMessage());
            return self::FAILURE;
        }
    }
}
