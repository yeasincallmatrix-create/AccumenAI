<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 123-B/C/D/E/F/I — Comprehensive Database Index Analysis (READ-ONLY)
 */
class DatabaseIndexAnalysisService
{
    public function __construct(
        private readonly DatabaseIndexAuditService $indexAudit = new DatabaseIndexAuditService(),
        private readonly DatabasePerformanceService $perfService = new DatabasePerformanceService(),
    ) {}

    /**
     * Step 123-B — Analyze the six original recommendations individually.
     */
    public function analyzeSixRecommendations(): array
    {
        $recommendations = [
            ['students', ['institute_id', 'batch_id'], 'Tenant + batch filtering', ['WHERE institute_id = ?', 'WHERE institute_id = ? AND batch_id = ?']],
            ['teachers', ['institute_id'], 'Institute-scoped teacher listing', ['WHERE institute_id = ?']],
            ['attendance', ['institute_id', 'class_date'], 'Date-range attendance reports', ['WHERE institute_id = ? AND class_date BETWEEN ? AND ?']],
            ['student_enrollments', ['institute_id', 'student_id'], 'Enrollment lookup by tenant+student', ['WHERE institute_id = ? AND student_id = ?']],
            ['journals', ['institute_id'], 'Journal tenant filtering', ['WHERE institute_id = ?']],
            ['journal_entries', ['coa_id'], 'Ledger and trial balance', ['WHERE coa_id = ?', 'WHERE coa_id = ? AND journal_date BETWEEN ? AND ?']],
        ];

        return array_map(fn($r) => $this->analyzeRecommendation(...$r), $recommendations);
    }

    private function analyzeRecommendation(string $table, array $columns, string $benefit, array $queryPatterns): array
    {
        $exists = Schema::hasTable($table);
        $existingIndexes = $exists ? $this->indexAudit->getIndexes($table) : [];
        $fks = $exists ? $this->indexAudit->getForeignKeys($table) : [];
        $rowCount = $exists ? (int) DB::table($table)->count() : 0;

        $coversExisting = false;
        $coveringIndex = null;
        foreach ($existingIndexes as $idx) {
            if ($idx['columns'] === $columns) {
                $coversExisting = true;
                $coveringIndex = $idx['name'];
                break;
            }
            if (count($idx['columns']) >= count($columns) && array_slice($idx['columns'], 0, count($columns)) === $columns) {
                $coversExisting = true;
                $coveringIndex = $idx['name'];
                break;
            }
        }

        $prefixExists = false;
        $prefixIndex = null;
        foreach ($existingIndexes as $idx) {
            if (count($idx['columns']) < count($columns) && array_slice($columns, 0, count($idx['columns'])) === $idx['columns']) {
                $prefixExists = true;
                $prefixIndex = $idx['name'];
                break;
            }
        }

        $overlappingIndexes = [];
        foreach ($existingIndexes as $idx) {
            $intersection = array_intersect($idx['columns'], $columns);
            if (! empty($intersection) && $idx['columns'] !== $columns) {
                $overlappingIndexes[] = $idx['name'] . '(' . implode(',', $idx['columns']) . ')';
            }
        }

        $fkColumns = [];
        foreach ($fks as $fk) {
            if (in_array($fk['column'], $columns)) {
                $fkColumns[] = $fk['column'] . ' -> ' . $fk['referenced_table'] . '.' . $fk['referenced_column'];
            }
        }

        $decision = $this->decideRecommendation($table, $columns, $rowCount, $coversExisting, $prefixExists, $overlappingIndexes);

        return [
            'table' => $table,
            'proposed_columns' => $columns,
            'proposed_index_name' => 'idx_' . $table . '_' . implode('_', $columns),
            'benefit' => $benefit,
            'query_patterns' => $queryPatterns,
            'table_exists' => $exists,
            'row_count' => $rowCount,
            'estimated_table_size' => $this->estimateSize($rowCount),
            'existing_indexes' => array_map(fn($i) => $i['name'] . '(' . implode(',', $i['columns']) . ')', $existingIndexes),
            'foreign_keys' => $fkColumns,
            'covers_existing_index' => $coversExisting,
            'covering_index' => $coveringIndex,
            'prefix_exists' => $prefixExists,
            'prefix_index' => $prefixIndex,
            'overlapping_indexes' => $overlappingIndexes,
            'duplicate_risk' => $this->assessDuplicateRisk($coversExisting, $prefixExists, $overlappingIndexes),
            'estimated_impact' => $this->estimateImpact($rowCount),
            'recommendation' => $decision['decision'],
            'reason' => $decision['reason'],
        ];
    }

    private function decideRecommendation(string $table, array $columns, int $rowCount, bool $coversExisting, bool $prefixExists, array $overlapping): array
    {
        if (! Schema::hasTable($table)) {
            return ['decision' => 'SKIP', 'reason' => 'Table does not exist'];
        }
        if ($coversExisting) {
            return ['decision' => 'SKIP', 'reason' => 'Already covered by existing index: ' . ($this->findCoveringIndex($table, $columns) ?? 'unknown')];
        }
        if ($prefixExists && $rowCount < 1000) {
            return ['decision' => 'DEFER', 'reason' => 'Prefix index exists and table is small (' . $rowCount . ' rows)'];
        }
        if (count($overlapping) > 2) {
            return ['decision' => 'REVIEW', 'reason' => 'Multiple overlapping indexes suggest consolidation opportunity'];
        }
        if ($rowCount < 100) {
            return ['decision' => 'DEFER', 'reason' => 'Table too small (' . $rowCount . ' rows) for measurable benefit'];
        }
        return ['decision' => 'CREATE', 'reason' => 'No covering index found, table has sufficient rows (' . $rowCount . ')'];
    }

    private function findCoveringIndex(string $table, array $columns): ?string
    {
        $indexes = $this->indexAudit->getIndexes($table);
        foreach ($indexes as $idx) {
            if ($idx['columns'] === $columns) return $idx['name'];
            if (count($idx['columns']) >= count($columns) && array_slice($idx['columns'], 0, count($columns)) === $columns) {
                return $idx['name'];
            }
        }
        return null;
    }

    private function assessDuplicateRisk(bool $coversExisting, bool $prefixExists, array $overlapping): string
    {
        if ($coversExisting) return 'HIGH — exact or prefix duplicate exists';
        if ($prefixExists) return 'MEDIUM — shorter prefix exists';
        if (count($overlapping) > 1) return 'MEDIUM — multiple overlapping indexes';
        if (count($overlapping) === 1) return 'LOW — one overlapping index';
        return 'LOW';
    }

    private function estimateImpact(int $rowCount): string
    {
        if ($rowCount > 10000) return 'HIGH';
        if ($rowCount > 1000) return 'MEDIUM';
        if ($rowCount > 100) return 'LOW';
        return 'MINIMAL';
    }

    private function estimateSize(int $rowCount): string
    {
        if ($rowCount > 100000) return 'LARGE';
        if ($rowCount > 10000) return 'MEDIUM';
        if ($rowCount > 1000) return 'SMALL';
        return 'TINY';
    }

    /**
     * Step 123-C — Tenant-aware composite index analysis.
     */
    public function tenantAwareAnalysis(): array
    {
        $tenantTables = DB::select(
            "SELECT c.table_name AS tbl FROM information_schema.columns c WHERE c.table_schema = DATABASE() AND c.column_name = 'institute_id'"
        );

        $findings = [];
        foreach ($tenantTables as $t) {
            $table = $t->tbl;
            if (! Schema::hasTable($table)) continue;
            try {
                $count = (int) DB::table($table)->count();
                if ($count < 2) continue;

                $indexes = $this->indexAudit->getIndexes($table);
                $hasInstituteOnly = false;
                $hasInstituteComposite = false;
                foreach ($indexes as $idx) {
                    if ($idx['columns'] === ['institute_id']) $hasInstituteOnly = true;
                    if (count($idx['columns']) > 1 && $idx['columns'][0] === 'institute_id') $hasInstituteComposite = true;
                }

                if ($hasInstituteOnly && ! $hasInstituteComposite) {
                    $findings[] = [
                        'table' => $table,
                        'row_count' => $count,
                        'current_index' => 'institute_id (single-column)',
                        'recommendation' => 'REVIEW',
                        'reason' => 'Composite index may improve tenant+FK queries',
                    ];
                }
            } catch (\Throwable $e) {}
        }
        return $findings;
    }

    /**
     * Step 123-D — Foreign key index audit.
     */
    public function foreignKeyIndexAudit(): array
    {
        $findings = [];
        $tables = DB::select(
            "SELECT t.table_name AS tbl FROM information_schema.tables t WHERE t.table_schema = DATABASE() AND t.table_type = 'BASE TABLE'"
        );

        foreach ($tables as $t) {
            $table = $t->tbl;
            if (! Schema::hasTable($table)) continue;
            try {
                $fks = $this->indexAudit->getForeignKeys($table);
                $indexes = $this->indexAudit->getIndexes($table);
                foreach ($fks as $fk) {
                    $col = $fk['column'];
                    $hasIndex = false;
                    $indexName = null;
                    $isPrefix = false;
                    foreach ($indexes as $idx) {
                        if (in_array($col, $idx['columns'])) {
                            $hasIndex = true;
                            $indexName = $idx['name'];
                            $isPrefix = ($idx['columns'][0] === $col);
                            break;
                        }
                    }
                    if (! $hasIndex) {
                        $findings[] = [
                            'table' => $table,
                            'column' => $col,
                            'references' => $fk['referenced_table'] . '.' . $fk['referenced_column'],
                            'status' => 'MISSING',
                            'severity' => 'HIGH',
                        ];
                    } elseif (! $isPrefix) {
                        $findings[] = [
                            'table' => $table,
                            'column' => $col,
                            'references' => $fk['referenced_table'] . '.' . $fk['referenced_column'],
                            'status' => 'NOT_PREFIX',
                            'index_name' => $indexName,
                            'severity' => 'LOW',
                        ];
                    }
                }
            } catch (\Throwable $e) {}
        }
        return $findings;
    }

    /**
     * Step 123-I — Duplicate prefix analysis across all tables.
     */
    public function duplicatePrefixAnalysis(): array
    {
        $findings = [];
        $tables = DB::select(
            "SELECT t.table_name AS tbl FROM information_schema.tables t WHERE t.table_schema = DATABASE() AND t.table_type = 'BASE TABLE'"
        );

        foreach ($tables as $t) {
            $table = $t->tbl;
            if (! Schema::hasTable($table)) continue;
            try {
                $indexes = $this->indexAudit->getIndexes($table);
                $count = count($indexes);
                for ($i = 0; $i < $count; $i++) {
                    for ($j = $i + 1; $j < $count; $j++) {
                        $a = $indexes[$i];
                        $b = $indexes[$j];
                        if ($a['columns'] === $b['columns']) {
                            $findings[] = [
                                'table' => $table,
                                'type' => 'EXACT_DUPLICATE',
                                'index_a' => $a['name'],
                                'index_b' => $b['name'],
                                'columns' => implode(',', $a['columns']),
                                'recommendation' => 'REVIEW',
                            ];
                        } elseif (count($a['columns']) < count($b['columns']) && array_slice($b['columns'], 0, count($a['columns'])) === $a['columns']) {
                            $findings[] = [
                                'table' => $table,
                                'type' => 'PREFIX_COVERS',
                                'shorter' => $a['name'] . '(' . implode(',', $a['columns']) . ')',
                                'longer' => $b['name'] . '(' . implode(',', $b['columns']) . ')',
                                'recommendation' => 'REVIEW',
                            ];
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }
        return $findings;
    }

    /**
     * Step 123-E — Safe EXPLAIN analysis for high-value queries.
     */
    public function explainAnalysis(): array
    {
        $queries = [
            'student_listing' => 'SELECT * FROM students WHERE institute_id = 1',
            'student_batch' => 'SELECT * FROM students WHERE institute_id = 1 AND batch_id = 1',
            'attendance_date' => 'SELECT * FROM attendance WHERE institute_id = 1 AND class_date BETWEEN ? AND ?',
            'enrollment_student' => 'SELECT * FROM student_enrollments WHERE institute_id = 1 AND student_id = 1',
            'journal_listing' => 'SELECT * FROM journals WHERE institute_id = 1',
            'journal_entries_coa' => 'SELECT * FROM journal_entries WHERE coa_id = 1',
            'invoice_listing' => 'SELECT * FROM invoices WHERE institute_id = 1',
            'payment_listing' => 'SELECT * FROM payments WHERE institute_id = 1',
        ];

        $results = [];
        foreach ($queries as $name => $sql) {
            $results[$name] = $this->safeExplain($name, $sql);
        }
        return $results;
    }

    private function safeExplain(string $name, string $sql): array
    {
        try {
            $table = $this->extractTable($sql);
            if (! Schema::hasTable($table)) {
                return ['query' => $name, 'table' => $table, 'status' => 'TABLE_NOT_FOUND'];
            }
            $explain = DB::select("EXPLAIN $sql");
            $rows = [];
            foreach ($explain as $row) {
                $rows[] = [
                    'table' => $row->table ?? '',
                    'type' => $row->type ?? '',
                    'possible_keys' => $row->possible_keys ?? '',
                    'key' => $row->key ?? '',
                    'rows_examined' => (int) ($row->rows ?? 0),
                    'filtered' => $row->filtered ?? '',
                    'extra' => $row->Extra ?? '',
                ];
            }
            return ['query' => $name, 'sql' => $sql, 'explain' => $rows, 'status' => 'OK'];
        } catch (\Throwable $e) {
            return ['query' => $name, 'sql' => $sql, 'status' => 'ERROR', 'error' => $e->getMessage()];
        }
    }

    private function extractTable(string $sql): string
    {
        preg_match('/FROM\s+(\w+)/i', $sql, $m);
        return $m[1] ?? '';
    }

    /**
     * Step 123-F — Slow query report from database_query_logs.
     */
    public function slowQueryReport(int $limit = 20, int $minMs = 100): array
    {
        try {
            $queries = DB::table('database_query_logs')
                ->where('execution_time', '>=', $minMs)
                ->orderByDesc('execution_time')
                ->limit($limit)
                ->get(['query', 'execution_time', 'status', 'created_at']);

            $grouped = [];
            foreach ($queries as $q) {
                $hash = md5(preg_replace('/\s+/', ' ', trim($q->query)));
                if (! isset($grouped[$hash])) {
                    $grouped[$hash] = [
                        'query_preview' => substr($q->query, 0, 200),
                        'max_ms' => (float) $q->execution_time,
                        'count' => 0,
                        'status' => $q->status,
                    ];
                }
                $grouped[$hash]['count']++;
                $grouped[$hash]['max_ms'] = max($grouped[$hash]['max_ms'], (float) $q->execution_time);
            }

            uasort($grouped, fn($a, $b) => $b['max_ms'] <=> $a['max_ms']);
            return array_values($grouped);
        } catch (\Throwable $e) {
            return [['error' => $e->getMessage()]];
        }
    }
}
