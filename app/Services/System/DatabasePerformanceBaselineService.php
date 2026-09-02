<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;

/**
 * Step 123-A — Database Performance Baseline (READ-ONLY)
 *
 * Captures a point-in-time performance baseline of the database:
 * row counts, largest tables, tenant-scoped sizes, existing indexes,
 * duplicate indexes, missing FK indexes, query-log stats.
 */
class DatabasePerformanceBaselineService
{
    public function __construct(
        private readonly DatabaseIndexAuditService $indexAudit = new DatabaseIndexAuditService(),
        private readonly DatabasePerformanceService $perfService = new DatabasePerformanceService(),
    ) {}

    public function baseline(): array
    {
        $tables = $this->allTableRowCounts();
        $largest = $this->largestTables($tables, 20);
        $tenantTables = $this->tenantScopedTableSizes();
        $duplicates = $this->duplicateIndexDetection();
        $missingFk = $this->missingForeignKeyIndexes();
        $recommendations = $this->indexRecommendations();
        $queryStats = $this->queryLogStats();
        $tableSizes = $this->tableAndIndexSizes();

        return [
            'generated_at' => now()->toIso8601String(),
            'table_count' => count($tables),
            'total_rows' => array_sum($tables),
            'largest_tables' => $largest,
            'tenant_scoped_tables' => $tenantTables,
            'duplicate_indexes' => $duplicates,
            'missing_fk_indexes' => $missingFk,
            'recommended_indexes' => $recommendations,
            'query_log_stats' => $queryStats,
            'table_sizes' => $tableSizes,
            'health' => $this->healthScore($duplicates, $missingFk, $recommendations, $queryStats),
        ];
    }

    public function allTableRowCounts(): array
    {
        $rows = DB::select(
            "SELECT t.table_name AS tbl, t.table_rows AS cnt FROM information_schema.tables t WHERE t.table_schema = DATABASE() ORDER BY t.table_rows DESC"
        );
        $counts = [];
        foreach ($rows as $row) {
            $counts[$row->tbl] = (int)($row->cnt ?? 0);
        }
        return $counts;
    }

    public function largestTables(array $counts, int $limit = 20): array
    {
        arsort($counts);
        return array_slice($counts, 0, $limit, true);
    }

    public function tenantScopedTableSizes(): array
    {
        $tenantCols = DB::select(
            "SELECT c.table_name AS tbl FROM information_schema.columns c WHERE c.table_schema = DATABASE() AND c.column_name = 'institute_id'"
        );

        $result = [];
        foreach ($tenantCols as $col) {
            $table = $col->tbl;
            try {
                $count = DB::table($table)->count();
                $distinctTenants = (int)DB::table($table)->distinct()->count('institute_id');
                $result[$table] = [
                    'row_count' => $count,
                    'tenant_count' => $distinctTenants,
                    'avg_per_tenant' => $distinctTenants > 0 ? (int)round($count / $distinctTenants) : 0,
                ];
            } catch (\Throwable $e) {
                // Skip
            }
        }
        uasort($result, fn($a, $b) => $b['row_count'] <=> $a['row_count']);
        return $result;
    }

    public function duplicateIndexDetection(): array
    {
        $duplicates = [];
        $tables = DB::select(
            "SELECT t.table_name AS tbl FROM information_schema.tables t WHERE t.table_schema = DATABASE() AND t.table_type = 'BASE TABLE'"
        );

        foreach ($tables as $t) {
            $name = $t->tbl;
            try {
                $indexes = $this->indexAudit->getIndexes($name);
                $byCols = [];
                foreach ($indexes as $idx) {
                    $key = implode(',', $idx['columns']);
                    if (isset($byCols[$key])) {
                        $duplicates[] = [
                            'table' => $name,
                            'duplicate_indexes' => [$byCols[$key], $idx['name']],
                            'columns' => $key,
                        ];
                    } else {
                        $byCols[$key] = $idx['name'];
                    }
                }
                // Prefix duplicate: INDEX(a,b,c) covers INDEX(a)
                $sorted = $indexes;
                usort($sorted, fn($a, $b) => count($a['columns']) <=> count($b['columns']));
                $count = count($sorted);
                for ($i = 0; $i < $count; $i++) {
                    for ($j = $i + 1; $j < $count; $j++) {
                        $short = $sorted[$i]['columns'];
                        $long = $sorted[$j]['columns'];
                        if (count($short) < count($long) && array_slice($long, 0, count($short)) === $short) {
                            $duplicates[] = [
                                'table' => $name,
                                'type' => 'prefix',
                                'shorter_index' => $sorted[$i]['name'],
                                'longer_index' => $sorted[$j]['name'],
                                'columns' => implode(',', $short) . ' is prefix of ' . implode(',', $long),
                            ];
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }
        return $duplicates;
    }

    public function missingForeignKeyIndexes(): array
    {
        $missing = [];
        $tables = DB::select(
            "SELECT t.table_name AS tbl FROM information_schema.tables t WHERE t.table_schema = DATABASE() AND t.table_type = 'BASE TABLE'"
        );

        foreach ($tables as $t) {
            $name = $t->tbl;
            try {
                $fks = $this->indexAudit->getForeignKeys($name);
                foreach ($fks as $fk) {
                    if (! $this->indexAudit->hasIndexForColumn($name, $fk['column'])) {
                        $missing[] = [
                            'table' => $name,
                            'column' => $fk['column'],
                            'references' => $fk['referenced_table'] . '.' . $fk['referenced_column'],
                        ];
                    }
                }
            } catch (\Throwable $e) {}
        }
        return $missing;
    }

    public function indexRecommendations(): array
    {
        return $this->indexAudit->detailedRecommendations();
    }

    public function queryLogStats(): array
    {
        try {
            return [
                'last_24h' => $this->perfService->stats(24),
                'last_7d' => $this->perfService->stats(168),
                'top_slow_queries' => $this->perfService->slowQueries(10),
            ];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function tableAndIndexSizes(): array
    {
        try {
            $rows = DB::select(
                "SELECT t.table_name AS tbl, t.data_length AS dl, t.index_length AS il, (t.data_length + t.index_length) AS tl
                 FROM information_schema.tables t WHERE t.table_schema = DATABASE() AND t.table_type = 'BASE TABLE'
                 ORDER BY tl DESC LIMIT 30"
            );
            $sizes = [];
            foreach ($rows as $r) {
                $sizes[$r->tbl] = [
                    'data_bytes' => (int)$r->dl,
                    'index_bytes' => (int)$r->il,
                    'total_bytes' => (int)$r->tl,
                    'data_mb' => round((int)$r->dl / 1048576, 2),
                    'index_mb' => round((int)$r->il / 1048576, 2),
                    'total_mb' => round((int)$r->tl / 1048576, 2),
                ];
            }
            return $sizes;
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function healthScore(array $duplicates, array $missingFk, array $recommendations, array $queryStats): array
    {
        $score = 100;
        $score -= count($duplicates) * 2;
        $score -= count($missingFk) * 1;
        $slowCount = $queryStats['last_24h']['slow_query_count'] ?? 0;
        $score -= min($slowCount * 3, 20);
        $failedCount = $queryStats['last_24h']['failed_query_count'] ?? 0;
        $score -= min($failedCount * 5, 25);
        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'status' => $score >= 90 ? 'EXCELLENT' : ($score >= 75 ? 'GOOD' : ($score >= 60 ? 'FAIR' : 'NEEDS_ATTENTION')),
            'duplicate_count' => count($duplicates),
            'missing_fk_count' => count($missingFk),
            'slow_query_count' => $slowCount,
            'failed_query_count' => $failedCount,
        ];
    }
}
