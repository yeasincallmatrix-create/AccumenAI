<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Slow Query Thresholds
    |--------------------------------------------------------------------------
    */
    'slow_query_ms' => env('DB_SLOW_QUERY_MS', 500),
    'critical_query_ms' => env('DB_CRITICAL_QUERY_MS', 2000),
    'p95_warning_ms' => env('DB_P95_WARNING_MS', 800),
    'p99_warning_ms' => env('DB_P99_WARNING_MS', 1500),

    /*
    |--------------------------------------------------------------------------
    | Alert Cooldowns (seconds)
    |--------------------------------------------------------------------------
    */
    'alert_cooldown' => [
        'slow_query' => 3600,
        'error_spike' => 1800,
        'volume_spike' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Capacity Warnings
    |--------------------------------------------------------------------------
    */
    'capacity_warning_gb' => env('DB_CAPACITY_WARNING_GB', 10),
    'capacity_critical_gb' => env('DB_CAPACITY_CRITICAL_GB', 20),
];
