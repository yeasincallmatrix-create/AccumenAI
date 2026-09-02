<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Step 124-K — Alerts (lightweight, cooldown, auditable)
 */
class DatabaseAlertService
{
    public function evaluate(): array
    {
        $alerts = [];

        $metrics = app(ProductionQueryMetricsService::class)->stats(1);
        $capacity = app(DatabaseCapacityService::class)->metrics();

        $slowThreshold = config('database-monitoring.slow_query_ms', 500);
        $criticalThreshold = config('database-monitoring.critical_query_ms', 2000);

        // Critical slow queries
        if (($metrics['maximum_duration'] ?? 0) > $criticalThreshold) {
            $alerts[] = $this->alert('critical_slow_query', 'Critical slow query detected', 'critical', ['max' => $metrics['maximum_duration']]);
        } elseif (($metrics['slow_queries'] ?? 0) > 10) {
            $alerts[] = $this->alert('slow_query_spike', 'Slow query spike', 'warning', ['count' => $metrics['slow_queries']]);
        }

        // Error spikes
        if (($metrics['failed_queries'] ?? 0) > 5) {
            $alerts[] = $this->alert('error_spike', 'Failed query spike', 'critical', ['failed' => $metrics['failed_queries']]);
        }

        // p95/p99
        $p95Warn = config('database-monitoring.p95_warning_ms', 800);
        $p99Warn = config('database-monitoring.p99_warning_ms', 1500);
        if (($metrics['p95_duration'] ?? 0) > $p95Warn) {
            $alerts[] = $this->alert('p95_degradation', 'p95 degradation', 'warning', ['p95' => $metrics['p95_duration']]);
        }
        if (($metrics['p99_duration'] ?? 0) > $p99Warn) {
            $alerts[] = $this->alert('p99_degradation', 'p99 degradation', 'warning', ['p99' => $metrics['p99_duration']]);
        }

        // DB growth
        if (($capacity['database_size'] ?? 0) > 5 * 1024 * 1024 * 1024) {
            $alerts[] = $this->alert('db_growth', 'Database growth spike', 'warning', ['size' => $capacity['database_size']]);
        }

        // Filter by cooldown
        $filtered = [];
        foreach ($alerts as $alert) {
            if ($alert && ! $this->isCooldown($alert['type'])) {
                $filtered[] = $alert;
                $this->store($alert);
            }
        }

        return array_filter($filtered);
    }

    private function alert(string $type, string $message, string $severity, array $metadata = []): ?array
    {
        $key = "db_alert:$type";
        if (Cache::has($key)) return null; // cooldown

        Cache::put($key, true, config('database-monitoring.alert_cooldown.' . $type, 3600));

        $data = [
            'type' => $type,
            'message' => $message,
            'severity' => $severity,
            'metadata' => $metadata,
            'alerted_at' => now()->toIso8601String(),
        ];

        // Auditable record
        try {
            DB::table('database_alerts')->insert([
                'type' => $type,
                'message' => $message,
                'severity' => $severity,
                'metadata' => json_encode($metadata),
                'alerted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {}

        return $data;
    }

    private function isCooldown(string $type): bool
    {
        return Cache::has("db_alert:$type");
    }

    private function store(array $alert): void
    {
        // Already stored in DB above
    }

    public function recent(int $limit = 10): array
    {
        try {
            return DB::table('database_alerts')->orderByDesc('created_at')->limit($limit)->get()->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
