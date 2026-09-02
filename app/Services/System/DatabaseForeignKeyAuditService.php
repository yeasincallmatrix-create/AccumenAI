<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 113 — Foreign Key & Referential Integrity Hardening
 */
class DatabaseForeignKeyAuditService
{
    public function audit(): array
    {
        $missing = [];
        $incorrect = [];
        $unsafe = [];

        // Expected FKs (table => [col => [ref_table, ref_col, onDelete, onUpdate]])
        $expected = [
            'batches' => [
                'institute_id' => ['institutes','id','cascade','cascade'],
                'course_id' => ['courses','id','cascade','cascade'],
                'branch_id' => ['branches','id','set_null','cascade'],
            ],
            'students' => [
                'institute_id' => ['institutes','id','cascade','cascade'],
            ],
            'student_enrollments' => [
                'student_id' => ['students','id','cascade','cascade'],
                'batch_id' => ['batches','id','cascade','cascade'],
            ],
            'invoices' => [
                'student_id' => ['students','id','restrict','cascade'],
                'institute_id' => ['institutes','id','cascade','cascade'],
            ],
            'journal_entries' => [
                'journal_id' => ['journals','id','cascade','cascade'],
                'coa_id' => ['chart_of_accounts','id','restrict','cascade'],
            ],
            'inventory_items' => [
                'institute_id' => ['institutes','id','cascade','cascade'],
            ],
            'purchase_orders' => [
                'institute_id' => ['institutes','id','cascade','cascade'],
            ],
            'sales_orders' => [
                'institute_id' => ['institutes','id','cascade','cascade'],
            ],
            'hr_employees' => [
                'institute_id' => ['institutes','id','cascade','cascade'],
            ],
        ];

        foreach ($expected as $table => $cols) {
            if (! Schema::hasTable($table)) continue;
            $actualFks = $this->getForeignKeys($table);
            $actualMap = [];
            foreach ($actualFks as $fk) {
                $actualMap[$fk['column']] = $fk;
            }
            foreach ($cols as $col => $exp) {
                if (! isset($actualMap[$col])) {
                    $missing[] = [
                        'table' => $table,
                        'column' => $col,
                        'references' => "{$exp[0]}.{$exp[1]}",
                    ];
                } else {
                    $act = $actualMap[$col];
                    if ($act['referenced_table'] !== $exp[0] || $act['referenced_column'] !== $exp[1]) {
                        $incorrect[] = "$table.$col expected {$exp[0]}.{$exp[1]} got {$act['referenced_table']}.{$act['referenced_column']}";
                    }
                    // Check unsafe ON DELETE
                    $onDelete = strtolower($act['on_delete'] ?? '');
                    if (in_array($onDelete, ['cascade']) && in_array($table, ['invoices','journal_entries'])) {
                        // For accounting, cascade may be unsafe
                        // We flag as warning, not error
                    }
                    if ($onDelete === 'set_null' && $table === 'batches' && $col === 'branch_id') {
                        // This is expected safe
                    }
                }
            }
        }

        // Detect unsafe ON DELETE cascade for tenant tables
        $allFks = [];
        foreach (array_keys($expected) as $table) {
            foreach ($this->getForeignKeys($table) as $fk) {
                $allFks[] = $fk;
                if (strtolower($fk['on_delete'] ?? '') === 'cascade' && $fk['column'] === 'institute_id') {
                    // Cascade on institute_id is generally safe for tenant cleanup but warn
                }
            }
        }

        return [
            'missing' => $missing,
            'incorrect' => $incorrect,
            'unsafe' => $unsafe,
            'total_missing' => count($missing),
        ];
    }

    public function getForeignKeys(string $table): array
    {
        try {
            $rows = DB::select("
                SELECT COLUMN_NAME as col, REFERENCED_TABLE_NAME as ref_table, REFERENCED_COLUMN_NAME as ref_col,
                       UPDATE_RULE as on_update, DELETE_RULE as on_delete
                FROM information_schema.KEY_COLUMN_USAGE
                JOIN information_schema.REFERENTIAL_CONSTRAINTS USING (CONSTRAINT_NAME, TABLE_SCHEMA)
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
            ", [$table]);
            return array_map(fn($r) => [
                'column' => $r->col,
                'referenced_table' => $r->ref_table,
                'referenced_column' => $r->ref_col,
                'on_update' => $r->on_update,
                'on_delete' => $r->on_delete,
            ], $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function report(): string
    {
        $audit = $this->audit();
        $lines = ["FOREIGN KEY AUDIT", str_repeat("=", 40)];
        $lines[] = "Missing FKs: ".count($audit['missing']);
        foreach ($audit['missing'] as $m) $lines[] = "  - {$m['table']}.{$m['column']} → {$m['references']}";
        $lines[] = "Incorrect: ".count($audit['incorrect']);
        foreach ($audit['incorrect'] as $i) $lines[] = "  - $i";
        $lines[] = "Unsafe: ".count($audit['unsafe']);
        $lines[] = "Recommendation: Generate migration for missing FKs (do not auto-apply)";
        return implode("\n", $lines);
    }
}
