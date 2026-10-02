<?php

namespace App\Console\Commands;

use App\Models\WorkerHeartbeat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class QueueHealthCommand extends Command
{
    protected $signature = 'queue:health
                            {--stale-after=300 : Seconds before a worker is considered stale}
                            {--json : JSON output}';

    protected $description = 'Report queue worker health and stuck jobs';

    public function handle(): int
    {
        $staleAfter = (int) $this->option('stale-after');

        $pendingJobs = DB::table('jobs')->count();
        $failedJobs  = DB::table('failed_jobs')->count();
        $activeLocks = DB::table('cache_locks')->count();

        // Jobs waiting to be picked up (never reserved) vs jobs a worker owns right now.
        $availableJobs = DB::table('jobs')->whereNull('reserved_at')->count();
        $reservedJobs  = $pendingJobs - $availableJobs;

        $stuckJobs = DB::table('jobs')
            ->whereNotNull('reserved_at')
            ->where('reserved_at', '<', now()->subSeconds($staleAfter)->getTimestamp())
            ->count();

        $workers = WorkerHeartbeat::orderByDesc('last_seen_at')->get();

        $activeWorkers = $workers->filter(
            fn ($w) => $w->last_seen_at->greaterThanOrEqualTo(now()->subSeconds($staleAfter))
        )->count();

        $fail = false;
        $failReason = null;

        if ($pendingJobs > 0 && $activeWorkers === 0) {
            $fail = true;
            $failReason = 'Pending jobs but no active worker';
        }

        if ($stuckJobs > 0) {
            $fail = true;
            $failReason = $failReason ? $failReason . '; stuck jobs' : 'Stuck jobs detected';
        }

        if ($workers->isEmpty() && $pendingJobs > 0) {
            $fail = true;
            $failReason = $failReason ? $failReason . '; no heartbeats' : 'No worker heartbeats but jobs pending';
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'healthy'        => ! $fail,
                'reason'         => $failReason,
                'pending_jobs'   => $pendingJobs,
                'available_jobs' => $availableJobs,
                'reserved_jobs'  => $reservedJobs,
                'failed_jobs'    => $failedJobs,
                'active_locks'   => $activeLocks,
                'stuck_jobs'     => $stuckJobs,
                'active_workers' => $activeWorkers,
                'total_workers'  => $workers->count(),
                'stale_after'    => $staleAfter,
            ], JSON_PRETTY_PRINT));

            return $fail ? self::FAILURE : self::SUCCESS;
        }

        $this->info('=== Queue Health ===');
        $this->newLine();
        $this->line("Pending jobs:    {$pendingJobs} (available {$availableJobs} / reserved {$reservedJobs})");
        $this->line("Failed jobs:     {$failedJobs}");
        $this->line("Active locks:    {$activeLocks}");
        $this->line("Stuck jobs:      {$stuckJobs}");
        $this->line("Active workers:  {$activeWorkers} / {$workers->count()}");
        $this->newLine();

        if ($workers->isNotEmpty()) {
            $this->table(
                ['Worker', 'Host', 'PID', 'Last Seen', 'Jobs', 'Status'],
                $workers->map(fn ($w) => [
                    $w->worker_id,
                    $w->hostname,
                    $w->pid,
                    $w->last_seen_at->diffForHumans(),
                    $w->jobs_processed,
                    $w->last_seen_at->greaterThanOrEqualTo(now()->subSeconds($staleAfter))
                        ? 'active'
                        : 'STALE',
                ])
            );
        }

        $this->newLine();

        if ($fail) {
            $this->error("UNHEALTHY: {$failReason}");

            return self::FAILURE;
        }

        if ($pendingJobs === 0) {
            $this->info('Queue healthy (idle)');
        } else {
            $this->info('Queue healthy');
        }

        return self::SUCCESS;
    }
}
