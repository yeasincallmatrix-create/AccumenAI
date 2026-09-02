<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup Disk & Path
    |--------------------------------------------------------------------------
    */
    'disk' => env('BACKUP_DISK', 'local'),
    'path' => env('BACKUP_PATH', 'backups'),

    /*
    |--------------------------------------------------------------------------
    | Backup Types (enabled/disabled)
    |--------------------------------------------------------------------------
    */
    'daily' => [
        'enabled' => env('BACKUP_DAILY_ENABLED', true),
        'schedule' => env('BACKUP_DAILY_SCHEDULE', '01:00'),
    ],

    'weekly' => [
        'enabled' => env('BACKUP_WEEKLY_ENABLED', true),
        'schedule' => env('BACKUP_WEEKLY_SCHEDULE', '02:00'),
        'day' => env('BACKUP_WEEKLY_DAY', 'sunday'),
    ],

    'manual' => [
        'enabled' => true,
    ],

    'pre_restore' => [
        'enabled' => true,
    ],

    'pre_orphan_cleanup' => [
        'enabled' => true,
    ],

    'pre_destructive' => [
        'enabled' => true,
    ],

    'health_check' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Verification
    |--------------------------------------------------------------------------
    */
    'verification' => [
        'enabled' => env('BACKUP_VERIFICATION_ENABLED', true),
        'algorithm' => env('BACKUP_CHECKSUM_ALGORITHM', 'sha256'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Minimum acceptable backup size in bytes
    |--------------------------------------------------------------------------
    */
    'min_size_bytes' => (int) env('BACKUP_MIN_SIZE_BYTES', 100),

    /*
    |--------------------------------------------------------------------------
    | Maximum backup execution time in seconds
    |--------------------------------------------------------------------------
    */
    'max_execution_time' => (int) env('BACKUP_MAX_EXECUTION_TIME', 300),

    /*
    |--------------------------------------------------------------------------
    | Concurrency lock
    |--------------------------------------------------------------------------
    */
    'lock' => [
        'key' => 'backup_lock_running',
        'timeout' => (int) env('BACKUP_LOCK_TIMEOUT', 300),
        'wait' => (int) env('BACKUP_LOCK_WAIT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification threshold (hours since last verified backup)
    |--------------------------------------------------------------------------
    */
    'notification_threshold_hours' => (int) env('BACKUP_NOTIFICATION_THRESHOLD_HOURS', 48),

    /*
    |--------------------------------------------------------------------------
    | mysqldump binary path (null = auto-detect)
    |--------------------------------------------------------------------------
    */
    'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH', null),

    /*
    |--------------------------------------------------------------------------
    | Step 125-A — Retention Policy (configurable, never hard-coded)
    |--------------------------------------------------------------------------
    */
    'retention' => [
        'daily' => [
            'enabled' => (bool) env('BACKUP_RETENTION_DAILY_ENABLED', true),
            'retain_days' => (int) env('BACKUP_RETENTION_DAILY_DAYS', 14),
        ],
        'weekly' => [
            'enabled' => (bool) env('BACKUP_RETENTION_WEEKLY_ENABLED', true),
            'retain_weeks' => (int) env('BACKUP_RETENTION_WEEKLY_WEEKS', 8),
        ],
        'monthly' => [
            'enabled' => (bool) env('BACKUP_RETENTION_MONTHLY_ENABLED', true),
            'retain_months' => (int) env('BACKUP_RETENTION_MONTHLY_MONTHS', 12),
        ],
        'manual' => [
            'enabled' => true,
            'retain_indefinitely' => (bool) env('BACKUP_RETENTION_MANUAL_INDEFINITE', true),
        ],
        'pre_operation' => [
            'enabled' => true,
            'retain_days' => (int) env('BACKUP_RETENTION_PREOP_DAYS', 30),
        ],
        'max_storage_bytes' => (int) env('BACKUP_RETENTION_MAX_STORAGE', 10 * 1024 * 1024 * 1024),
    ],

    /*
    |--------------------------------------------------------------------------
    | Step 125-E — Recovery Point Objective (RPO)
    |--------------------------------------------------------------------------
    */
    'rpo' => [
        'target_minutes' => (int) env('BACKUP_RPO_TARGET_MINUTES', 1440),
        'warning_minutes' => (int) env('BACKUP_RPO_WARNING_MINUTES', 1080),
        'critical_minutes' => (int) env('BACKUP_RPO_CRITICAL_MINUTES', 2880),
    ],

    /*
    |--------------------------------------------------------------------------
    | Step 125-F — Recovery Time Objective (RTO)
    |--------------------------------------------------------------------------
    */
    'rto' => [
        'target_minutes' => (int) env('BACKUP_RTO_TARGET_MINUTES', 60),
        'warning_minutes' => (int) env('BACKUP_RTO_WARNING_MINUTES', 45),
        'critical_minutes' => (int) env('BACKUP_RTO_CRITICAL_MINUTES', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Step 125-G — Restore Drill
    |--------------------------------------------------------------------------
    */
    'restore_drill' => [
        'enabled' => (bool) env('BACKUP_RESTORE_DRILL_ENABLED', true),
        'schedule' => env('BACKUP_RESTORE_DRILL_SCHEDULE', 'weekly'),
        'temp_database' => env('BACKUP_RESTORE_DRILL_TEMP_DB', 'monetix_dr_test'),
        'sample_tables' => ['institutes', 'users', 'students', 'journals'],
        'max_drill_duration_seconds' => (int) env('BACKUP_RESTORE_DRILL_MAX_SECONDS', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Step 125-H — Off-site Backup Storage
    |--------------------------------------------------------------------------
    */
    'offsite' => [
        'enabled' => (bool) env('BACKUP_OFFSITE_ENABLED', false),
        'disk' => env('BACKUP_OFFSITE_DISK', null),
        'path' => env('BACKUP_OFFSITE_PATH', 'backups-offsite'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Step 125-I — Encryption
    |--------------------------------------------------------------------------
    */
    'encryption' => [
        'enabled' => (bool) env('BACKUP_ENCRYPTION_ENABLED', false),
        'key_env' => env('BACKUP_ENCRYPTION_KEY_ENV', 'BACKUP_ENCRYPTION_KEY'),
        'algorithm' => env('BACKUP_ENCRYPTION_ALGORITHM', 'aes-256-cbc'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Step 125-J — Failure & Retry
    |--------------------------------------------------------------------------
    */
    'failure' => [
        'max_retries' => (int) env('BACKUP_MAX_RETRIES', 3),
        'retry_delay_seconds' => (int) env('BACKUP_RETRY_DELAY_SECONDS', 60),
        'record_failures' => true,
        'consecutive_failure_alert_threshold' => (int) env('BACKUP_FAILURE_ALERT_THRESHOLD', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | App name used in filenames
    |--------------------------------------------------------------------------
    */
    'app_name' => env('BACKUP_APP_NAME', 'monetix'),

];
