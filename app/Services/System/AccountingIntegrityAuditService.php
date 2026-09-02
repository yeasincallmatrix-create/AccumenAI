<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 116 — Accounting Data Integrity Audit (read-only)
 */
class AccountingIntegrityAuditService
{
    public function audit(): array
    {
        $issues = [];

        // Journals: debit = credit
        if (Schema::hasTable('journals') && Schema::hasTable('journal_entries')) {
            $journals = DB::table('journals')->pluck('id')->all();
            foreach (array_chunk($journals, 100) as $chunk) {
                $sums = DB::table('journal_entries')->whereIn('journal_id', $chunk)
                    ->select('journal_id', DB::raw('SUM(debit) as d'), DB::raw('SUM(credit) as c'))
                    ->groupBy('journal_id')->get();
                foreach ($sums as $row) {
                    if (abs((float)$row->d - (float)$row->c) > 0.01) {
                        $issues[] = "Journal {$row->journal_id} debit {$row->d} != credit {$row->c}";
                    }
                }
            }
        }

        // Trial balance: total debit = total credit
        if (Schema::hasTable('journal_entries')) {
            $totals = DB::table('journal_entries')->select(DB::raw('SUM(debit) as d'), DB::raw('SUM(credit) as c'))->first();
            if ($totals && abs((float)$totals->d - (float)$totals->c) > 0.01) {
                $issues[] = "Trial balance mismatch: debit {$totals->d} vs credit {$totals->c}";
            }
        }

        // Invoices without student
        if (Schema::hasTable('invoices') && Schema::hasTable('students')) {
            $count = DB::table('invoices')->leftJoin('students', 'invoices.student_id', '=', 'students.id')->whereNull('students.id')->whereNotNull('invoices.student_id')->count();
            if ($count > 0) $issues[] = "Invoices without student: $count";
        }

        // Payments without invoice
        if (Schema::hasTable('payments') && Schema::hasTable('invoices')) {
            $count = DB::table('payments')->leftJoin('invoices', 'payments.invoice_id', '=', 'invoices.id')->whereNull('invoices.id')->count();
            if ($count > 0) $issues[] = "Payments without invoice: $count";
        }

        return [
            'healthy' => empty($issues),
            'issues' => $issues,
            'count' => count($issues),
        ];
    }

    public function report(): string
    {
        $res = $this->audit();
        $lines = ["ACCOUNTING INTEGRITY AUDIT", str_repeat("=", 40)];
        $lines[] = $res['healthy'] ? "Status: PASS" : "Status: FAIL";
        foreach ($res['issues'] as $iss) $lines[] = "  - $iss";
        if (empty($res['issues'])) $lines[] = "  No issues found";
        return implode("\n", $lines);
    }
}
