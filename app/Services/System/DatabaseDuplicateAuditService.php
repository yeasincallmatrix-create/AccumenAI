<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 114 — Duplicate Data Audit
 */
class DatabaseDuplicateAuditService
{
    public const CHECKS = [
        'institutes' => ['slug' => 'institute code', 'email' => 'institute email'],
        'users' => ['email' => 'user email'],
        'students' => ['student_id_number' => 'registration number'],
        'courses' => ['course_code' => 'course code', 'slug' => 'course slug'],
        'batches' => ['batch_code' => 'batch identifiers'],
        'invoices' => ['invoice_number' => 'invoice number'],
        'journals' => ['journal_no' => 'journal number'],
        'purchase_orders' => ['po_number' => 'purchase order number'],
        'sales_orders' => ['order_number' => 'sales order number'],
        'customers' => ['code' => 'customer identifiers'],
        'suppliers' => ['code' => 'supplier identifiers'],
    ];

    public function audit(): array
    {
        $critical = 0;
        $warnings = 0;
        $details = [];

        foreach (self::CHECKS as $table => $cols) {
            if (! Schema::hasTable($table)) continue;
            foreach ($cols as $col => $label) {
                if (! Schema::hasColumn($table, $col)) continue;
                $dup = DB::table($table)
                    ->select($col, DB::raw('COUNT(*) as cnt'), DB::raw('GROUP_CONCAT(id) as ids'))
                    ->whereNotNull($col)
                    ->where($col, '!=', '')
                    ->groupBy($col)
                    ->having('cnt', '>', 1)
                    ->get();

                foreach ($dup as $row) {
                    $critical++;
                    $details[] = [
                        'table' => $table,
                        'column' => $col,
                        'value' => $row->$col,
                        'count' => $row->cnt,
                        'ids' => $row->ids,
                        'label' => $label,
                    ];
                }
            }
        }

        return [
            'critical' => $critical,
            'warnings' => $warnings,
            'details' => $details,
            'safe' => $critical === 0,
        ];
    }

    public function report(): string
    {
        $res = $this->audit();
        $lines = ["DUPLICATE DATA AUDIT", str_repeat("=", 40)];
        $lines[] = "Critical duplicates: {$res['critical']}";
        $lines[] = "Warnings: {$res['warnings']}";
        $lines[] = "Safe: " . ($res['safe'] ? 'YES' : 'NO');
        if (! empty($res['details'])) {
            foreach (array_slice($res['details'], 0, 5) as $d) {
                $lines[] = "  {$d['table']}.{$d['column']}='{$d['value']}' count {$d['count']} ids {$d['ids']}";
            }
        }
        return implode("\n", $lines);
    }
}
