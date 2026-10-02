<?php

namespace App\Jobs\Middleware;

use App\Models\WorkerHeartbeat as HeartbeatModel;

class WorkerHeartbeat
{
    public function handle($job, $next): void
    {
        // Activity signal (never fails the job)
        HeartbeatModel::recordJob();

        $next($job);
    }
}
