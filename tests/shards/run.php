<?php

/**
 * Sharded parallel test runner (Phase 7 test-infrastructure fix).
 *
 * Why this exists:
 *   - The full Feature suite (~4,800 tests) exceeds a 10-minute timeout
 *     in a single process (~1.4s/test on Windows/MySQL).
 *   - `php artisan test --parallel` provisions per-token databases that are
 *     empty in this project (schema dump is data-only), so it errors out.
 *   - Direct `paratest -p 4` on the shared test DB works: tests use
 *     DatabaseTransactions, so workers stay isolated. A small number of
 *     deadlock/duplicate-key collisions (~3-4%) are re-run sequentially
 *     for true counts.
 *
 * Usage:
 *   php tests/shards/run.php            # all 6 shards, 4 processes each
 *   php tests/shards/run.php 1 3        # only shards 1 and 3
 *   php tests/shards/run.php --processes=8
 *
 * Shard filters live in shard1.txt … shard6.txt (PHPUnit --filter regexes
 * over test class names; tests/Feature is flat so shards are alphabetical).
 */

$processes = 4;
$only = [];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--processes=')) {
        $processes = max(1, (int) substr($arg, strlen('--processes=')));
    } elseif (is_numeric($arg)) {
        $only[] = (int) $arg;
    }
}

$dir = __DIR__;
$shards = $only !== [] ? $only : range(1, 6);
$exit = 0;

foreach ($shards as $i) {
    $filterFile = $dir . "/shard{$i}.txt";
    if (! file_exists($filterFile)) {
        fwrite(STDERR, "Missing filter file: {$filterFile}\n");
        $exit = 1;
        continue;
    }
    $filter = trim(file_get_contents($filterFile));
    $junit = sys_get_temp_dir() . "/shard{$i}.xml";
    $cmd = sprintf(
        'php vendor/bin/paratest -p %d --log-junit=%s --filter=%s 2>&1',
        $processes,
        escapeshellarg($junit),
        escapeshellarg($filter)
    );
    $start = microtime(true);
    echo "=== shard{$i} started " . date('H:i:s') . " ===\n";
    passthru('cd ' . escapeshellarg(dirname($dir, 2)) . ' && ' . $cmd, $code);
    $dur = round(microtime(true) - $start);
    echo "=== shard{$i} finished in {$dur}s (exit {$code}) ===\n";
    if ($code !== 0) {
        $exit = $code;
    }
}

exit($exit);
