<?php

/**
 * Per-Shard Test Runner — Full Clone Approach (Phase 9.1).
 *
 * For each shard:
 *   1. Snapshot the base test DB once (mysqldump --single-transaction
 *      --routines --triggers --events) — full 408-table clone.
 *   2. Per shard: DROP + CREATE monetix_test_shardN, restore snapshot,
 *      verify table count (>= 400), run the shard filter with
 *      DB_DATABASE=<shard> (real env beats .env.testing under
 *      phpdotenv-immutable), drop the shard DB unless KEEP_SHARD_DBS=1.
 *
 * Eliminates cross-shard contention on the shared monetix_test DB.
 *
 * Usage:
 *   php tests/shards/run-per-shard.php           # all shards
 *   php tests/shards/run-per-shard.php 1         # shard 1 only
 *   KEEP_SHARD_DBS=1 php tests/shards/run-per-shard.php  # keep DBs
 *
 * run.php (shared DB) is untouched and remains the fallback.
 */

// ─────────────────────────────────────────────────────────────────
// Configuration
// ─────────────────────────────────────────────────────────────────
$mysqlBin = is_file('C:\\xampp\\mysql\\bin\\mysql.exe')
    ? 'C:\\xampp\\mysql\\bin'
    : '';

$config = [
    'base_db'   => getenv('DB_DATABASE') ?: 'monetix_test',
    'db_host'   => getenv('DB_HOST') ?: '127.0.0.1',
    'db_port'   => getenv('DB_PORT') ?: '3306',
    'db_user'   => getenv('DB_USERNAME') ?: 'root',
    'db_pass'   => getenv('DB_PASSWORD') ?: '',
    'mysql'     => $mysqlBin !== '' ? $mysqlBin . DIRECTORY_SEPARATOR . 'mysql' : 'mysql',
    'mysqldump' => $mysqlBin !== '' ? $mysqlBin . DIRECTORY_SEPARATOR . 'mysqldump' : 'mysqldump',
    'snapshot'  => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'monetix_test_snapshot_' . date('Ymd_His') . '.sql',
    'processes' => 4,
    'shards'    => [
        1 => 'monetix_test_shard1',
        2 => 'monetix_test_shard2',
        3 => 'monetix_test_shard3',
        4 => 'monetix_test_shard4',
        5 => 'monetix_test_shard5',
        6 => 'monetix_test_shard6',
    ],
];

$passFlag = $config['db_pass'] !== '' ? '-p' . escapeshellarg($config['db_pass']) : '';

$targetShards = isset($argv[1]) ? [(int) $argv[1]] : array_keys($config['shards']);
foreach ($targetShards as $s) {
    if (! isset($config['shards'][$s])) {
        fwrite(STDERR, "Invalid shard: {$s}\n");
        exit(1);
    }
}

$projectRoot = dirname(__DIR__, 2);

echo "\n" . str_repeat('=', 70) . "\n";
echo "  PER-SHARD TEST RUNNER (Full Clone)\n";
echo "  Base DB: {$config['base_db']}\n";
echo "  Target shards: " . implode(', ', $targetShards) . "\n";
echo str_repeat('=', 70) . "\n\n";

// ─────────────────────────────────────────────────────────────────
// Step 1: Snapshot base DB (once)
// ─────────────────────────────────────────────────────────────────
echo "-> [1/4] Snapshotting {$config['base_db']}...\n";
$dumpCmd = sprintf(
    '%s -h %s -P %s -u %s %s --single-transaction --routines --triggers --events --skip-lock-tables %s > %s 2>&1',
    escapeshellarg($config['mysqldump']),
    escapeshellarg($config['db_host']),
    escapeshellarg($config['db_port']),
    escapeshellarg($config['db_user']),
    $passFlag,
    escapeshellarg($config['base_db']),
    escapeshellarg($config['snapshot'])
);
exec($dumpCmd, $dumpOut, $dumpCode);
if ($dumpCode !== 0) {
    fwrite(STDERR, "Snapshot FAILED (exit {$dumpCode})\n" . implode("\n", $dumpOut) . "\n");
    exit(1);
}
$snapshotMb = round(filesize($config['snapshot']) / 1024 / 1024, 2);
echo "   Snapshot: {$config['snapshot']} ({$snapshotMb} MB)\n";

// Completeness gate: count CREATE TABLEs in the dump (test tables are
// transaction-rolled-back empty, so byte size is NOT a valid signal —
// a full 408-table dump is only ~1.6 MB).
$createCount = 0;
$fh = @fopen($config['snapshot'], 'r');
if ($fh) {
    while (! feof($fh)) {
        $line = fgets($fh);
        if ($line !== false && str_starts_with($line, 'CREATE TABLE')) {
            $createCount++;
        }
    }
    fclose($fh);
}
echo "   Tables in dump: {$createCount}\n\n";

if ($createCount < 400) {
    fwrite(STDERR, "Snapshot incomplete ({$createCount} CREATE TABLEs, need >= 400) — STOP.\n");
    exit(1);
}

// ─────────────────────────────────────────────────────────────────
// Steps 2-4: per shard
// ─────────────────────────────────────────────────────────────────
$results = [];
$startTime = microtime(true);

foreach ($targetShards as $shardNum) {
    $shardDb = $config['shards'][$shardNum];
    $filterFile = __DIR__ . "/shard{$shardNum}.txt";

    echo str_repeat('-', 70) . "\n  SHARD {$shardNum}: {$shardDb}\n" . str_repeat('-', 70) . "\n";

    if (! file_exists($filterFile)) {
        echo "   Filter file missing: {$filterFile} — skipped\n\n";
        $results[$shardNum] = ['status' => 'skipped'];
        continue;
    }
    $filter = trim(file_get_contents($filterFile));

    $sql = function (string $stmt) use ($config): array {
        $cmd = sprintf(
            '%s -h %s -P %s -u %s %s -e %s 2>&1',
            escapeshellarg($config['mysql']),
            escapeshellarg($config['db_host']),
            escapeshellarg($config['db_port']),
            escapeshellarg($config['db_user']),
            $config['db_pass'] !== '' ? '-p' . escapeshellarg($config['db_pass']) : '',
            escapeshellarg($stmt)
        );
        exec($cmd, $out, $code);
        return [$out, $code];
    };

    echo "   -> Drop + create {$shardDb}...\n";
    $sql("DROP DATABASE IF EXISTS {$shardDb}");
    [, $createCode] = $sql("CREATE DATABASE {$shardDb} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    if ($createCode !== 0) {
        echo "   CREATE FAILED — skipped\n\n";
        $results[$shardNum] = ['status' => 'failed', 'reason' => 'create'];
        continue;
    }

    echo "   -> Restoring snapshot...\n";
    $restoreCmd = sprintf(
        '%s -h %s -P %s -u %s %s %s < %s 2>&1',
        escapeshellarg($config['mysql']),
        escapeshellarg($config['db_host']),
        escapeshellarg($config['db_port']),
        escapeshellarg($config['db_user']),
        $config['db_pass'] !== '' ? '-p' . escapeshellarg($config['db_pass']) : '',
        escapeshellarg($shardDb),
        escapeshellarg($config['snapshot'])
    );
    exec($restoreCmd, $restoreOut, $restoreCode);
    if ($restoreCode !== 0) {
        echo "   Restore FAILED (exit {$restoreCode})\n";
        $results[$shardNum] = ['status' => 'failed', 'reason' => 'restore'];
        continue;
    }

    [$countOut] = $sql("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='{$shardDb}'");
    $tableCount = 0;
    foreach ($countOut as $line) {
        if (is_numeric(trim($line))) {
            $tableCount = (int) trim($line);
        }
    }
    echo "   -> Tables restored: {$tableCount}\n";
    if ($tableCount < 400) {
        echo "   Expected >= 400 tables — skipped\n\n";
        $results[$shardNum] = ['status' => 'failed', 'reason' => 'table-count'];
        continue;
    }

    echo "   -> Running tests (DB_DATABASE={$shardDb})...\n";
    putenv("DB_DATABASE={$shardDb}");
    $_ENV['DB_DATABASE'] = $shardDb;
    $_SERVER['DB_DATABASE'] = $shardDb;
    $testStart = microtime(true);
    $testCmd = sprintf(
        'cd %s && php vendor/bin/paratest -p %d --filter=%s 2>&1',
        escapeshellarg($projectRoot),
        $config['processes'],
        escapeshellarg($filter)
    );
    passthru($testCmd, $testCode);
    $testDuration = round(microtime(true) - $testStart, 2);

    if (! getenv('KEEP_SHARD_DBS')) {
        echo "   -> Cleaning up {$shardDb}...\n";
        $sql("DROP DATABASE IF EXISTS {$shardDb}");
    } else {
        echo "   -> Kept {$shardDb} (KEEP_SHARD_DBS=1)\n";
    }

    $results[$shardNum] = [
        'status'   => $testCode === 0 ? 'pass' : 'fail',
        'exit'     => $testCode,
        'duration' => $testDuration,
        'tables'   => $tableCount,
    ];
    echo "   -> Exit: {$testCode} | Duration: {$testDuration}s\n\n";
}

// ─────────────────────────────────────────────────────────────────
// Summary
// ─────────────────────────────────────────────────────────────────
$totalDuration = round(microtime(true) - $startTime, 2);
echo "\n" . str_repeat('=', 70) . "\n  SUMMARY\n" . str_repeat('=', 70) . "\n";
foreach ($results as $num => $r) {
    $status = $r['status'] ?? 'unknown';
    $icon = $status === 'pass' ? '[PASS]' : ($status === 'fail' ? '[FAIL]' : '[SKIP]');
    echo "  {$icon} Shard {$num}: {$status}\n";
}
echo "\n  Total duration: {$totalDuration}s\n" . str_repeat('=', 70) . "\n";

@unlink($config['snapshot']);

$failed = array_filter($results, fn ($r) => ($r['status'] ?? '') === 'fail');
exit(empty($failed) ? 0 : 1);
