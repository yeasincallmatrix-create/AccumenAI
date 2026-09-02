<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;

/**
 * Step 124-F — Endpoint Performance Monitoring (read-only aggregate)
 */
class EndpointPerformanceService
{
    public function record(string $route, float $durationMs, int $statusCode): void
    {
        try {
            DB::table('endpoint_performance_logs')->insert([
                'route' => substr($route, 0, 255),
                'request_count' => 1,
                'average_response_time' => $durationMs,
                'maximum_response_time' => $durationMs,
                'error_count' => $statusCode >= 500 ? 1 : 0,
                'http_4xx_count' => ($statusCode >= 400 && $statusCode < 500) ? 1 : 0,
                'http_5xx_count' => $statusCode >= 500 ? 1 : 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {}
    }

    public function stats(int $hours = 24): array
    {
        $since = now()->subHours($hours);
        $rows = DB::table('endpoint_performance_logs')->where('created_at', '>=', $since)->get();

        $grouped = [];
        foreach ($rows as $row) {
            $key = $row->route;
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'route' => $key,
                    'request_count' => 0,
                    'total_time' => 0,
                    'max_time' => 0,
                    'error_count' => 0,
                    '4xx' => 0,
                    '5xx' => 0,
                    'durations' => [],
                ];
            }
            $grouped[$key]['request_count']++;
            $grouped[$key]['total_time'] += $row->average_response_time;
            $grouped[$key]['max_time'] = max($grouped[$key]['max_time'], $row->maximum_response_time);
            $grouped[$key]['error_count'] += $row->error_count;
            $grouped[$key]['4xx'] += $row->http_4xx_count;
            $grouped[$key]['5xx'] += $row->http_5xx_count;
            $grouped[$key]['durations'][] = $row->average_response_time;
        }

        $result = [];
        foreach ($grouped as $route => $data) {
            sort($data['durations']);
            $count = count($data['durations']);
            $avg = $count ? round($data['total_time'] / $count, 2) : 0;
            $p95 = $count ? $data['durations'][(int)ceil($count * 0.95) - 1] : 0;
            $p99 = $count ? $data['durations'][(int)ceil($count * 0.99) - 1] : 0;
            $result[] = [
                'route' => $route,
                'request_count' => $data['request_count'],
                'average_response_time' => $avg,
                'p95_response_time' => round($p95, 2),
                'p99_response_time' => round($p99, 2),
                'maximum_response_time' => round($data['max_time'], 2),
                'error_count' => $data['error_count'],
                'http_4xx_count' => $data['4xx'],
                'http_5xx_count' => $data['5xx'],
            ];
        }

        usort($result, fn($a,$b) => $b['average_response_time'] <=> $a['average_response_time']);
        return $result;
    }
}
