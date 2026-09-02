<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 105 — Database Index Audit
 */
class DatabaseIndexAuditService
{
    /**
     * Expected critical indexes (table => [columns])
     */
    public const EXPECTED_INDEXES = [
        'students' => [
            ['columns' => ['institute_id'], 'name' => 'idx_students_institute'],
            ['columns' => ['institute_id', 'batch_id'], 'name' => 'idx_students_institute_batch'],
            ['columns' => ['student_id_number'], 'name' => 'idx_students_number'],
        ],
        'teachers' => [
            ['columns' => ['institute_id'], 'name' => 'idx_teachers_institute'],
        ],
        'attendance' => [
            ['columns' => ['institute_id', 'class_date'], 'name' => 'idx_attendance_institute_date'],
            ['columns' => ['student_id'], 'name' => 'idx_attendance_student'],
        ],
        'student_enrollments' => [
            ['columns' => ['institute_id', 'student_id'], 'name' => 'idx_enrollments_institute_student'],
            ['columns' => ['batch_id'], 'name' => 'idx_enrollments_batch'],
        ],
        'payments' => [
            ['columns' => ['institute_id'], 'name' => 'idx_payments_institute'],
            ['columns' => ['invoice_id'], 'name' => 'idx_payments_invoice'],
        ],
        'invoices' => [
            ['columns' => ['institute_id'], 'name' => 'idx_invoices_institute'],
            ['columns' => ['student_id'], 'name' => 'idx_invoices_student'],
        ],
        'journals' => [
            ['columns' => ['institute_id'], 'name' => 'idx_journals_institute'],
        ],
        'journal_entries' => [
            ['columns' => ['journal_id'], 'name' => 'idx_je_journal'],
            ['columns' => ['coa_id'], 'name' => 'idx_je_coa'],
        ],
        'audit_logs' => [
            ['columns' => ['institute_id'], 'name' => 'idx_audit_institute'],
            ['columns' => ['created_at'], 'name' => 'idx_audit_created'],
        ],
        'activity_logs' => [
            ['columns' => ['institute_id'], 'name' => 'idx_activity_institute'],
        ],
        'notifications' => [
            ['columns' => ['institute_id'], 'name' => 'idx_notifications_institute'],
        ],
    ];

    public function audit(): array
    {
        $report = [];
        $missing = [];
        $duplicates = [];
        $unused = [];
        $fkIndexes = [];

        foreach (self::EXPECTED_INDEXES as $table => $expected) {
            if (! Schema::hasTable($table)) {
                $report[$table] = ['status' => 'missing_table', 'missing' => $expected];
                continue;
            }

            $existing = $this->getIndexes($table);
            $existingByCols = [];
            foreach ($existing as $idx) {
                $key = implode(',', $idx['columns']);
                $existingByCols[$key] = $idx;
            }

            $tableMissing = [];
            foreach ($expected as $exp) {
                $key = implode(',', $exp['columns']);
                if (! isset($existingByCols[$key])) {
                    $tableMissing[] = $exp;
                    $missing[] = "$table (" . implode(',', $exp['columns']) . ")";
                }
            }

            // Duplicate indexes: same columns, different names
            $seen = [];
            foreach ($existing as $idx) {
                $key = implode(',', $idx['columns']);
                if (isset($seen[$key])) {
                    $duplicates[] = "$table: {$idx['name']} duplicates {$seen[$key]}";
                } else {
                    $seen[$key] = $idx['name'];
                }
            }

            // Unused: indexes not in expected (potential, but not critical)
            // We will not flag as missing, just report

            $report[$table] = [
                'existing' => $existing,
                'missing' => $tableMissing,
                'status' => empty($tableMissing) ? 'ok' : 'missing',
            ];
        }

        // Foreign key indexes check
        foreach (array_keys(self::EXPECTED_INDEXES) as $table) {
            if (! Schema::hasTable($table)) continue;
            $fks = $this->getForeignKeys($table);
            foreach ($fks as $fk) {
                $col = $fk['column'];
                $hasIndex = $this->hasIndexForColumn($table, $col);
                if (! $hasIndex) {
                    $fkIndexes[] = "$table.$col (FK to {$fk['referenced_table']}.{$fk['referenced_column']})";
                }
            }
        }

        return [
            'report' => $report,
            'missing' => $missing,
            'duplicates' => $duplicates,
            'unused' => $unused,
            'fk_missing_indexes' => $fkIndexes,
            'total_missing' => count($missing),
        ];
    }

    public function getIndexes(string $table): array
    {
        try {
            if (! Schema::hasTable($table)) return [];
            $rows = DB::select("SHOW INDEX FROM `$table`");
            $grouped = [];
            foreach ($rows as $row) {
                $name = $row->Key_name ?? $row->key_name ?? '';
                if (! isset($grouped[$name])) {
                    $grouped[$name] = ['name' => $name, 'columns' => [], 'unique' => (bool)($row->Non_unique == 0)];
                }
                $grouped[$name]['columns'][] = $row->Column_name ?? $row->column_name ?? '';
            }
            return array_values($grouped);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getForeignKeys(string $table): array
    {
        try {
            $rows = DB::select("
                SELECT COLUMN_NAME as `column`, REFERENCED_TABLE_NAME as referenced_table, REFERENCED_COLUMN_NAME as referenced_column
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
            ", [$table]);
            return array_map(fn($r) => (array)$r, $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function hasIndexForColumn(string $table, string $column): bool
    {
        $indexes = $this->getIndexes($table);
        foreach ($indexes as $idx) {
            if (in_array($column, $idx['columns'])) return true;
        }
        return false;
    }

    public function detailedRecommendations(): array
    {
        $audit = $this->audit();
        $details = [];
        foreach ($audit['report'] as $table => $data) {
            foreach ($data['missing'] ?? [] as $miss) {
                $cols = implode(',', $miss['columns']);
                $current = array_map(fn($i) => $i['name'].':('.implode(',', $i['columns']).')', $data['existing'] ?? []);
                $details[] = [
                    'table' => $table,
                    'columns' => $cols,
                    'current_indexes' => $current,
                    'query_benefit' => $this->queryBenefit($table, $miss['columns']),
                    'estimated_impact' => $this->estimatedImpact($table),
                    'duplicate_risk' => $this->duplicateRisk($table, $miss['columns']),
                    'recommendation' => $this->recommendation($table, $miss),
                ];
            }
        }
        return $details;
    }

    private function queryBenefit(string $table, array $cols): string
    {
        $map = [
            'students' => 'tenant + batch filtering, enrollment queries',
            'teachers' => 'institute-scoped teacher queries',
            'attendance' => 'date-range attendance reports',
            'student_enrollments' => 'enrollment and batch queries',
            'journals' => 'accounting journal tenant queries',
            'journal_entries' => 'ledger and trial balance',
        ];
        return $map[$table] ?? 'general filtering';
    }

    private function estimatedImpact(string $table): string
    {
        try {
            $count = \Illuminate\Support\Facades\DB::table($table)->count();
            if ($count > 10000) return 'high';
            if ($count > 1000) return 'medium';
            return 'low';
        } catch (\Throwable $e) { return 'unknown'; }
    }

    private function duplicateRisk(string $table, array $cols): string
    {
        $existing = $this->getIndexes($table);
        foreach ($existing as $idx) {
            if ($idx['columns'] === $cols) return 'high — exact duplicate';
            if (count(array_intersect($idx['columns'], $cols)) > 0) return 'medium — overlapping';
        }
        return 'low';
    }

    private function recommendation(string $table, array $miss): string
    {
        $impact = $this->estimatedImpact($table);
        $risk = $this->duplicateRisk($table, $miss['columns']);
        if ($impact === 'high' && $risk === 'low') return 'CREATE — clearly justified';
        if ($impact === 'low') return 'DEFER — low benefit';
        if (str_contains($risk, 'high')) return 'SKIP — duplicate';
        return 'REVIEW — verify query patterns';
    }

    public function generateReport(): string
    {
        $audit = $this->audit();
        $lines = ["Index Report:", str_repeat("=", 40)];
        foreach ($audit['report'] as $table => $data) {
            $lines[] = "";
            $lines[] = "Table: $table";
            if (! empty($data['missing'])) {
                $lines[] = "Missing:";
                foreach ($data['missing'] as $m) {
                    $lines[] = "  (" . implode(',', $m['columns']) . ") - recommended: {$m['name']}";
                }
            } else {
                $lines[] = "Missing: none";
            }
        }
        if (! empty($audit['duplicates'])) {
            $lines[] = "";
            $lines[] = "Duplicate indexes:";
            foreach ($audit['duplicates'] as $d) $lines[] = "  $d";
        }
        if (! empty($audit['fk_missing_indexes'])) {
            $lines[] = "";
            $lines[] = "FK without index:";
            foreach ($audit['fk_missing_indexes'] as $f) $lines[] = "  $f";
        }
        $lines[] = "";
        $lines[] = "Recommendation only. Do not automatically modify indexes.";
        // Detailed per-index
        $details = $this->detailedRecommendations();
        if (! empty($details)) {
            $lines[] = "";
            $lines[] = "Detailed Recommendations:";
            foreach ($details as $d) {
                $lines[] = "  Table: {$d['table']}";
                $lines[] = "  Columns: {$d['columns']}";
                $lines[] = "  Current indexes: ".implode(', ', $d['current_indexes']);
                $lines[] = "  Query benefit: {$d['query_benefit']}";
                $lines[] = "  Estimated impact: {$d['estimated_impact']}";
                $lines[] = "  Duplicate risk: {$d['duplicate_risk']}";
                $lines[] = "  Recommendation: {$d['recommendation']}";
                $lines[] = "";
            }
        }
        return implode("\n", $lines);
    }
}
