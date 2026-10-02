<?php

namespace App\Jobs\Middleware;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconnectDatabase
{
    public function handle($job, $next): void
    {
        $connection = config('database.default');

        try {
            // Purge + reconnect forces a fresh PDO handle
            DB::purge($connection);
            DB::connection($connection)->select('SELECT 1');
        } catch (\Throwable $e) {
            Log::error('ReconnectDatabase: failed to reconnect', [
                'job'        => get_class($job),
                'connection' => $connection,
                'error'      => $e->getMessage(),
            ]);

            throw $e;
        }

        $next($job);
    }
}
