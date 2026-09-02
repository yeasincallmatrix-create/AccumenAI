<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SystemHealthCheck extends Command
{
    protected $signature = 'health:check';

    protected $description = 'Run system-wide health checks: database, cache, queue, disk, PHP & Laravel versions';

    public function handle(): int
    {
        $failures = 0;

        $this->newLine();
        $this->info('=== SYSTEM HEALTH CHECK ===');
        $this->newLine();

        // 1. Database
        $this->line('1. Database connectivity...');
        try {
            DB::select('SELECT 1');
            $this->info('   OK');
        } catch (\Exception $e) {
            $this->error('   FAIL: ' . $e->getMessage());
            $failures++;
        }

        // 2. Cache
        $this->line('2. Cache read/write...');
        try {
            $key = 'health_check_cmd_' . uniqid();
            Cache::put($key, 'ok', 10);
            $val = Cache::get($key);
            Cache::forget($key);

            if ($val === 'ok') {
                $this->info('   OK');
            } else {
                $this->error('   FAIL: Cache value mismatch');
                $failures++;
            }
        } catch (\Exception $e) {
            $this->error('   FAIL: ' . $e->getMessage());
            $failures++;
        }

        // 3. Queue tables
        $this->line('3. Queue tables...');
        try {
            $hasJobs = DB::getSchemaBuilder()->hasTable('jobs');
            $hasFailedJobs = DB::getSchemaBuilder()->hasTable('failed_jobs');

            $failedCount = 0;
            if ($hasFailedJobs) {
                $failedCount = DB::table('failed_jobs')->count();
            }

            if ($hasJobs) {
                $this->info("   OK (jobs table present, failed_jobs: $failedCount)");
            } else {
                $this->warn('   WARN: No jobs table (sync driver?)');
            }

            if ($failedCount > 0) {
                $this->warn("   WARN: $failedCount failed jobs in queue");
            }
        } catch (\Exception $e) {
            $this->error('   FAIL: ' . $e->getMessage());
            $failures++;
        }

        // 4. Disk space
        $this->line('4. Disk space...');
        $free = @disk_free_space(base_path());
        if ($free !== false) {
            $freeGb = round($free / (1024 * 1024 * 1024), 2);
            if ($freeGb > 1) {
                $this->info("   OK ({$freeGb} GB free)");
            } else {
                $this->warn("   WARN: Low disk space ({$freeGb} GB free)");
            }
        } else {
            $this->info('   OK (disk_free_space unavailable)');
        }

        // 5. PHP version
        $this->line('5. PHP version...');
        $this->info('   ' . PHP_VERSION);

        // 6. Laravel version
        $this->line('6. Laravel version...');
        $this->info('   ' . app()->version());

        // Summary
        $this->newLine();
        $this->info('=== SUMMARY ===');
        if ($failures > 0) {
            $this->error("FAILURES: $failures");
            return 1;
        }

        $this->info('ALL CHECKS PASSED');

        return 0;
    }
}
