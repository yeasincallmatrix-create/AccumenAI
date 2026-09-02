<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 123-E — EXPLAIN-based query plan analysis (READ-ONLY).
 */
class DatabaseQueryPlanService
{
    public const HIGH_VALUE_QUERIES = [
        'student_listing' => 'SELECT * FROM students WHERE institute_id = 1',
        'student_batch' => 'SELECT * FROM students WHERE institute_id = 1 AND batch_id = 1',
        'attendance_date_range' => 'SELECT * FROM attendance WHERE institute_id = 1 AND class_date BETWEEN "2026-01-01" AND "2026-12-31"',
        'enrollment_student' => 'SELECT * FROM student_enrollments WHERE institute_id = 1 AND student_id = 1',
        'enrollment_batch' => 'SELECT * FROM student_enrollments WHERE batch_id = 1',
        'journal_listing' => 'SELECT * FROM journals WHERE institute_id = 1',
        'journal_date_range' => 'SELECT * FROM journals WHERE institute_id = 1 AND journal_date BETWEEN "2026-01-01" AND "2026-12-31"',
        'journal_entries_coa' => 'SELECT * FROM journal_entries WHERE coa_id = 1',
        'journal_entries_date' => 'SELECT * FROM journal_entries WHERE coa_id = 1 AND journal_date BETWEEN "2026-01-01" AND "2026-12-31"',
        'invoice_listing' => 'SELECT * FROM invoices WHERE institute_id = 1',
        'payment_listing' => 'SELECT * FROM payments WHERE institute_id = 1',
    ];

    public function analyzeAll(): array
    {
        $results = [];
        foreach (self::HIGH_VALUE_QUERIES as $name => $sql) {
            $results[$name] = $this->explainQuery($name, $sql);
        }
        return $results;
    }

    public function explainQuery(string $name, string $sql): array
    {
        $table = $this->extractTable($sql);
        if (! Schema::hasTable($table)) {
            return ['query' => $name, 'table' => $table, 'status' => 'TABLE_NOT_FOUND'];
        }

        try {
            $explain = DB::select("EXPLAIN $sql");
            $plans = [];
            $fullScan = false;
            foreach ($explain as $row) {
                $type = $row->type ?? '';
                if ($type === 'ALL' || $type === '') $fullScan = true;
                $plans[] = [
                    'table' => $row->table ?? '',
                    'access_type' => $type,
                    'possible_keys' => $row->possible_keys ?? null,
                    'chosen_key' => $row->key ?? null,
                    'rows_examined' => (int) ($row->rows ?? 0),
                    'filtered' => (float) ($row->filtered ?? 0),
                    'extra' => $row->Extra ?? '',
                ];
            }
            return [
                'query' => $name,
                'sql' => $sql,
                'table' => $table,
                'plans' => $plans,
                'full_table_scan' => $fullScan,
                'status' => $fullScan ? 'NEEDS_INDEX' : 'OK',
            ];
        } catch (\Throwable $e) {
            return ['query' => $name, 'sql' => $sql, 'status' => 'ERROR', 'error' => $e->getMessage()];
        }
    }

    private function extractTable(string $sql): string
    {
        preg_match('/FROM\s+(\w+)/i', $sql, $m);
        return $m[1] ?? '';
    }
}
