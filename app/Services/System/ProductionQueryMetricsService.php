<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;

/**
 * Step 124-A — Production Query Metrics (aggregate, safe)
 */
class ProductionQueryMetricsService
{
    public function stats(int $hours = 24, int $limit = 10): array
    {
        $since = now()->subHours($hours);

        $total = DB::table('database_query_logs')->where('created_at', '>=', $since)->count();
        $select = DB::table('database_query_logs')->where('created_at', '>=', $since)->where('query', 'like', 'select%')->count();
        $insert = DB::table('database_query_logs')->where('created_at', '>=', $since)->where('query', 'like', 'insert%')->count();
        $update = DB::table('database_query_logs')->where('created_at', '>=', $since)->where('query', 'like', 'update%')->count();
        $delete = DB::table('database_query_logs')->where('created_at', '>=', $since)->where('query', 'like', 'delete%')->count();
        $failed = DB::table('database_query_logs')->where('created_at', '>=', $since)->where('status', 'failed')->count();
        $slow = DB::table('database_query_logs')->where('created_at', '>=', $since)->where('status', 'slow')->count();

        $avg = DB::table('database_query_logs')->where('created_at', '>=', $since)->avg('execution_time') ?? 0;
        $max = DB::table('database_query_logs')->where('created_at', '>=', $since)->max('execution_time') ?? 0;

        // p95, p99
        $p95 = $this->percentile($since, 95);
        $p99 = $this->percentile($since, 99);

        $perMinute = $hours > 0 ? round($total / ($hours * 60), 2) : 0;
        $perHour = $hours > 0 ? round($total / $hours, 2) : 0;

        // Top fingerprints
        $fingerprintService = app(QueryFingerprintService::class);
        try {
            $topSlow = $fingerprintService->top($limit, 'duration');
            $topFrequent = $fingerprintService->top($limit, 'count');
        } catch (\Throwable $e) {
            $topSlow = [];
            $topFrequent = [];
        }

        return [
            'total_queries' => $total,
            'select_count' => $select,
            'insert_count' => $insert,
            'update_count' => $update,
            'delete_count' => $delete,
            'failed_queries' => $failed,
            'slow_queries' => $slow,
            'average_duration' => round((float)$avg, 2),
            'p95_duration' => $p95,
            'p99_duration' => $p99,
            'maximum_duration' => round((float)$max, 2),
            'queries_per_minute' => $perMinute,
            'queries_per_hour' => $perHour,
            'top_slow_query_fingerprints' => $topSlow,
            'top_frequently_executed_query_fingerprints' => $topFrequent,
        ];
    }

    private function percentile($since, int $percentile): float
    {
        $count = DB::table('database_query_logs')->where('created_at', '>=', $since)->count();
        if ($count === 0) return 0;

        $offset = (int)ceil($count * ($percentile / 100)) - 1;
        $offset = max(0, $offset);

        $row = DB::table('database_query_logs')
            ->where('created_at', '>=', $since)
            ->orderBy('execution_time')
            ->offset($offset)
            ->limit(1)
            ->value('execution_time');

        return round((float)($row ?? 0), 2);
    }

    public function fingerprintStats(): array
    {
        try {
            return DB::table('query_fingerprints')->selectRaw('COUNT(*) as total, SUM(execution_count) as executions, AVG(average_duration) as avg')->first();
        } catch (\Throwable $e) {
            return (object)['total' => 0, 'executions' => 0, 'avg' => 0];
        }
    }
}
