<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkerHeartbeat extends Model
{
    protected $fillable = [
        'worker_id', 'hostname', 'pid', 'queue',
        'started_at', 'last_seen_at', 'jobs_processed',
    ];

    protected $casts = [
        'started_at'     => 'datetime',
        'last_seen_at'   => 'datetime',
        'jobs_processed' => 'integer',
    ];

    /**
     * Record worker activity.
     * - Creates row on first job (started_at = now)
     * - Always refreshes last_seen_at (activity, not liveness)
     * - Increments jobs_processed
     * - Never throws: a heartbeat failure must not fail a job
     */
    public static function recordJob(): void
    {
        $workerId = gethostname() . ':' . getmypid();

        try {
            $row = static::firstOrNew(['worker_id' => $workerId]);

            if (! $row->exists) {
                $row->hostname   = gethostname();
                $row->pid        = getmypid();
                $row->queue      = 'default,notifications';
                $row->started_at = now();
            }

            $row->last_seen_at = now();
            $row->save();

            $row->increment('jobs_processed');
        } catch (\Throwable $e) {
            \Log::warning('WorkerHeartbeat::recordJob failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function purgeOld(int $days = 7): int
    {
        return static::where('last_seen_at', '<', now()->subDays($days))->delete();
    }

    public static function activeCount(int $staleAfter = 300): int
    {
        return static::where('last_seen_at', '>=', now()->subSeconds($staleAfter))->count();
    }
}
