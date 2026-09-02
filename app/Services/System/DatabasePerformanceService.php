<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;

/**
 * Step 106 — Query Performance Monitoring
 */
class DatabasePerformanceService
{
    public const SLOW_THRESHOLD_MS = 500;

    public function log(string $query, float $time, string $connection = 'mysql', string $status = 'success', ?string $error = null): void
    {
        try {
            DB::table('database_query_logs')->insert([
                'query' => substr($query, 0, 65535),
                'execution_time' => $time,
                'connection' => $connection,
                'status' => $status,
                'error' => $error ? substr($error, 0, 65535) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Never break main flow
        }
    }

    public function recordSlow(string $query, float $timeMs): void
    {
        if ($timeMs >= self::SLOW_THRESHOLD_MS) {
            $this->log($query, $timeMs, 'mysql', 'slow');
        }
    }

    public function recordFailed(string $query, string $error): void
    {
        $this->log($query, 0, 'mysql', 'failed', $error);
    }

    public function stats(int $hours = 24): array
    {
        $since = now()->subHours($hours);

        $total = DB::table('database_query_logs')->where('created_at', '>=', $since)->count();
        $slow = DB::table('database_query_logs')->where('created_at', '>=', $since)->where('status', 'slow')->count();
        $failed = DB::table('database_query_logs')->where('created_at', '>=', $since)->where('status', 'failed')->count();
        $avg = DB::table('database_query_logs')->where('created_at', '>=', $since)->avg('execution_time') ?? 0;
        $errors = DB::table('database_query_logs')->where('created_at', '>=', $since)->where('status', 'failed')->limit(5)->pluck('error')->all();

        return [
            'slow_query_count' => $slow,
            'failed_query_count' => $failed,
            'total_queries' => $total,
            'average_execution_time' => round((float)$avg, 2),
            'database_errors' => $errors,
            'period_hours' => $hours,
        ];
    }

    public function widget(): array
    {
        $stats = $this->stats(24);
        return [
            'title' => 'Database Performance',
            'slow_query_count' => $stats['slow_query_count'],
            'average_execution_time' => $stats['average_execution_time'],
            'database_errors' => $stats['failed_query_count'],
            'total' => $stats['total_queries'],
            'status' => $stats['failed_query_count'] > 0 ? 'warning' : 'healthy',
        ];
    }

    public function slowQueries(int $limit = 10)
    {
        return DB::table('database_query_logs')
            ->where('status', 'slow')
            ->orderByDesc('execution_time')
            ->limit($limit)
            ->get();
    }
}
