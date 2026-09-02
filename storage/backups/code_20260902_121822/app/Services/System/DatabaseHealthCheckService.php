<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 101 — Database Health Audit System.
 * Checks migration status, missing tables, seed records, orphans, indexes, tenant isolation.
 */
class DatabaseHealthCheckService
{
    public function __construct(
        private readonly SeedIntegrityService $seedIntegrity = new SeedIntegrityService()
    ) {}

    public function run(bool $persist = true): array
    {
        $checks = [];
        $score = 100;

        // 1. Migration status
        $migration = $this->checkMigrations();
        $checks['migrations'] = $migration;
        if (! $migration['healthy']) $score -= 20;

        // 2. Missing tables
        $tables = $this->checkMissingTables();
        $checks['missing_tables'] = $tables;
        if (! $tables['healthy']) $score -= 25;

        // 3. Missing required seed records
        $seeds = $this->seedIntegrity->check();
        $checks['seeds'] = $seeds;
        if (! $seeds['healthy']) $score -= 10;

        // 4. Orphan foreign keys (warning level — may include test data)
        $orphans = $this->checkOrphans();
        $checks['orphans'] = $orphans;
        if (! $orphans['healthy']) $score -= 5;

        // 5. Missing indexes
        $indexes = $this->checkMissingIndexes();
        $checks['indexes'] = $indexes;
        if (! $indexes['healthy']) $score -= 10;

        // 6. Tenant isolation
        $isolation = $this->checkTenantIsolation();
        $checks['tenant_isolation'] = $isolation;
        if (! $isolation['healthy']) $score -= 15;

        $score = max(0, $score);
        $status = $score >= 90 ? 'healthy' : ($score >= 70 ? 'warning' : 'critical');

        $result = [
            'status' => $status,
            'score' => $score,
            'checks' => $checks,
            'missing_tables' => $tables['missing'] ?? [],
            'missing_seeds' => $seeds['missing'] ?? [],
            'orphans' => $orphans['details'] ?? [],
            'missing_indexes' => $indexes['missing'] ?? [],
            'timestamp' => now()->toIso8601String(),
        ];

        if ($persist) {
            \App\Models\SystemHealthAudit::create([
                'status' => $status,
                'score' => $score,
                'checks' => $checks,
                'missing_tables' => $tables['missing'] ?? [],
                'missing_seeds' => $seeds['missing'] ?? [],
                'orphans' => $orphans['details'] ?? [],
                'missing_indexes' => $indexes['missing'] ?? [],
                'created_by' => auth()->id(),
            ]);
        }

        return $result;
    }

    public function checkMigrations(): array
    {
        $ran = DB::table('migrations')->pluck('migration')->all();
        $files = glob(database_path('migrations/*.php'));
        $all = array_map(fn($f) => basename($f, '.php'), $files);
        $pending = array_values(array_diff($all, $ran));

        return [
            'healthy' => empty($pending),
            'ran' => count($ran),
            'total' => count($all),
            'pending' => $pending,
            'details' => empty($pending) ? 'All migrations applied' : count($pending).' pending',
        ];
    }

    public function checkMissingTables(): array
    {
        $expected = $this->expectedTables();
        $existingNames = [];
        foreach (DB::select('SHOW TABLES') as $row) {
            $vals = array_values((array)$row);
            $existingNames[] = strtolower($vals[0]);
        }

        $missing = array_values(array_diff($expected, $existingNames));

        return [
            'healthy' => empty($missing),
            'expected' => count($expected),
            'existing' => count($existingNames),
            'missing' => $missing,
            'details' => empty($missing) ? 'All tables present' : count($missing).' missing',
        ];
    }

    private function expectedTables(): array
    {
        // Derive expected tables from migrations that create tables
        $files = glob(database_path('migrations/*.php'));
        $tables = [];
        foreach ($files as $f) {
            $content = file_get_contents($f);
            if (preg_match_all("/Schema::create\('([^']+)'/", $content, $m)) {
                foreach ($m[1] as $t) $tables[] = strtolower($t);
            }
            if (preg_match_all('/Schema::create\("([^"]+)"/', $content, $m2)) {
                foreach ($m2[1] as $t) $tables[] = strtolower($t);
            }
        }
        // Add known core tables that are not created via Schema::create string literal
        $tables = array_unique($tables);
        sort($tables);
        return $tables;
    }

    public function checkOrphans(): array
    {
        $checks = [
            'students' => ['institute_id' => 'institutes'],
            'batches' => ['institute_id' => 'institutes'],
            'branches' => ['institute_id' => 'institutes'],
            'student_enrollments' => ['student_id' => 'students', 'batch_id' => 'batches'],
            'institution_user' => ['institution_id' => 'institutes', 'user_id' => 'users'],
        ];

        $orphans = [];
        $details = [];
        foreach ($checks as $table => $fks) {
            if (! Schema::hasTable($table)) continue;
            foreach ($fks as $col => $parent) {
                if (! Schema::hasTable($parent)) continue;
                $parentIdCol = 'id';
                $count = DB::table($table)
                    ->leftJoin($parent, "$table.$col", '=', "$parent.$parentIdCol")
                    ->whereNotNull("$table.$col")
                    ->whereNull("$parent.$parentIdCol")
                    ->count();
                if ($count > 0) {
                    $orphans[] = "$table.$col -> $parent ($count orphans)";
                    $details["$table.$col"] = $count;
                }
            }
        }

        return [
            'healthy' => empty($orphans),
            'orphans' => $orphans,
            'details' => $details,
        ];
    }

    public function checkMissingIndexes(): array
    {
        // Expected critical indexes for tenant isolation and performance
        $expected = [
            'students' => ['institute_id', 'student_id_number'],
            'institutes' => ['slug'],
            'institution_user' => ['institution_id', 'user_id'],
            'users' => ['email'],
        ];

        $missing = [];
        foreach ($expected as $table => $cols) {
            if (! Schema::hasTable($table)) continue;
            $indexes = DB::select("SHOW INDEX FROM `$table`");
            $indexedCols = array_unique(array_map(fn($r) => $r->Column_name ?? $r->column_name ?? '', $indexes));
            $indexedCols = array_map('strtolower', $indexedCols);
            foreach ($cols as $col) {
                if (! in_array(strtolower($col), $indexedCols)) {
                    $missing[] = "$table.$col";
                }
            }
        }

        return [
            'healthy' => empty($missing),
            'missing' => $missing,
            'details' => empty($missing) ? 'All critical indexes present' : count($missing).' missing',
        ];
    }

    public function checkTenantIsolation(): array
    {
        $issues = [];
        $details = [];

        // TenantScoped models should never have null institute_id
        $scopedTables = [
            'students' => 'institute_id',
            'batches' => 'institute_id',
            'branches' => 'institute_id',
            'invoices' => 'institute_id',
        ];

        foreach ($scopedTables as $table => $col) {
            if (! Schema::hasTable($table)) continue;
            $nulls = DB::table($table)->whereNull($col)->count();
            if ($nulls > 0) {
                $issues[] = "$table has $nulls rows with null $col";
                $details[$table] = $nulls;
            }
        }

        // Check for cross-tenant data leakage via students not in institutes
        // (already covered by orphans, but explicit)

        return [
            'healthy' => empty($issues),
            'issues' => $issues,
            'details' => $details,
        ];
    }

    public function safetyScore(): int
    {
        return $this->run(persist: false)['score'];
    }
}
