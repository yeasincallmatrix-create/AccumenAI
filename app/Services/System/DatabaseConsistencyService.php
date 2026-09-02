<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 112 — Database Consistency Audit (read-only, never deletes)
 */
class DatabaseConsistencyService
{
    public function check(): array
    {
        $tenant = $this->checkTenant();
        $relations = $this->checkRelationships();
        $softDelete = $this->checkSoftDelete();
        $accounting = $this->checkAccounting();
        $inventory = $this->checkInventory();
        $crossTenant = $this->checkCrossTenant();

        $overall = empty($tenant['issues']) && empty($relations['issues']) && empty($softDelete['issues'])
            && empty($accounting['issues']) && empty($inventory['issues']) && empty($crossTenant['issues'])
            ? 'CLEAN' : 'WARNING';

        return [
            'tenant' => $tenant,
            'relationships' => $relations,
            'soft_delete' => $softDelete,
            'accounting' => $accounting,
            'inventory' => $inventory,
            'cross_tenant' => $crossTenant,
            'overall' => $overall,
        ];
    }

    public function checkTenant(): array
    {
        $issues = [];
        // Use DataIntegrityService for tenant checks but also extra
        $svc = app(DataIntegrityService::class);
        $res = $svc->checkTenantIntegrity();
        foreach ($res['issues'] as $iss) {
            $issues[] = $iss;
        }

        // Additional: no NULL tenant where TenantScoped trait expects it
        $scoped = ['students','batches','invoices','journals','journal_entries'];
        foreach ($scoped as $tbl) {
            if (! Schema::hasTable($tbl) || ! Schema::hasColumn($tbl, 'institute_id')) continue;
            $nulls = DB::table($tbl)->whereNull('institute_id')->count();
            if ($nulls > 0 && ! collect($issues)->firstWhere('table', $tbl)) {
                $issues[] = [
                    'issue' => 'NULL tenant IDs where prohibited',
                    'table' => $tbl,
                    'count' => $nulls,
                    'suggestion' => "Backfill institute_id for $tbl. SQL: SELECT id FROM $tbl WHERE institute_id IS NULL;",
                ];
            }
        }

        return ['status' => empty($issues) ? 'PASS' : 'WARNING', 'issues' => $issues];
    }

    public function checkRelationships(): array
    {
        $svc = app(DataIntegrityService::class);
        $res = $svc->checkRelationshipIntegrity();
        $issues = $res['issues'];

        // Additional relationships from step 112 list
        $extra = [
            ['table' => 'institutes', 'col' => 'id', 'parent' => 'users', 'parent_col' => 'id', 'label' => 'institutes → users (owner)'],
            // courses → batches already covered, but add explicit
            ['table' => 'batches', 'col' => 'institute_id', 'parent' => 'institutes', 'parent_col' => 'id', 'label' => 'batches without institute'],
            ['table' => 'invoices', 'col' => 'institute_id', 'parent' => 'institutes', 'parent_col' => 'id', 'label' => 'invoices without institute'],
            ['table' => 'customers', 'col' => 'institute_id', 'parent' => 'institutes', 'parent_col' => 'id', 'label' => 'customers without institute'],
        ];
        // Only check if tables exist
        foreach ($extra as $c) {
            if (! Schema::hasTable($c['table']) || ! Schema::hasTable($c['parent'])) continue;
            if (! Schema::hasColumn($c['table'], $c['col'])) continue;
            $count = DB::table($c['table'])
                ->leftJoin($c['parent'], $c['table'].'.'.$c['col'], '=', $c['parent'].'.'.$c['parent_col'])
                ->whereNotNull($c['table'].'.'.$c['col'])
                ->whereNull($c['parent'].'.'.$c['parent_col'])
                ->count();
            if ($count > 0) {
                $issues[] = [
                    'issue' => $c['label'],
                    'table' => $c['table'],
                    'count' => $count,
                    'suggestion' => "Orphan {$c['table']}.{$c['col']} → {$c['parent']}",
                ];
            }
        }

        return ['status' => empty($issues) ? 'PASS' : 'WARNING', 'issues' => $issues];
    }

    public function checkSoftDelete(): array
    {
        $svc = app(DataIntegrityService::class);
        $res = $svc->checkSoftDeleteConsistency();
        return ['status' => empty($res['issues']) ? 'PASS' : 'WARNING', 'issues' => $res['issues']];
    }

    public function checkAccounting(): array
    {
        $issues = [];
        // Basic checks: journal entries without journal, invoice without student, etc. are already in relationships
        // Here we check soft-delete consistency for accounting: deleted journal with active entries
        if (Schema::hasTable('journals') && Schema::hasColumn('journals', 'deleted_at') && Schema::hasTable('journal_entries')) {
            $deleted = DB::table('journals')->whereNotNull('deleted_at')->pluck('id')->all();
            if (! empty($deleted)) {
                $count = DB::table('journal_entries')->whereIn('journal_id', $deleted)->whereNull('deleted_at')->count();
                if ($count > 0) {
                    $issues[] = ['issue' => 'Active journal entries for deleted journal', 'table' => 'journal_entries', 'count' => $count, 'suggestion' => 'Archive entries with parent'];
                }
            }
        }
        return ['status' => empty($issues) ? 'PASS' : 'WARNING', 'issues' => $issues];
    }

    public function checkInventory(): array
    {
        $issues = [];
        // Check inventory → warehouses consistency if tables exist
        if (Schema::hasTable('inventory_items') && Schema::hasTable('inventory_warehouses')) {
            // No direct FK, skip
        }
        if (Schema::hasTable('inventory_stock_levels')) {
            $neg = DB::table('inventory_stock_levels')->where('quantity', '<', 0)->count();
            if ($neg > 0) {
                $issues[] = ['issue' => 'Negative stock', 'table' => 'inventory_stock_levels', 'count' => $neg, 'suggestion' => 'Review stock adjustments'];
            }
        }
        return ['status' => empty($issues) ? 'PASS' : 'WARNING', 'issues' => $issues];
    }

    public function checkCrossTenant(): array
    {
        $issues = [];
        // Cross-tenant FK: student_enrollments institute_id != students institute_id (already in tenant)
        if (Schema::hasTable('student_enrollments') && Schema::hasTable('students')) {
            $count = DB::table('student_enrollments')
                ->join('students', 'student_enrollments.student_id', '=', 'students.id')
                ->whereColumn('student_enrollments.institute_id', '!=', 'students.institute_id')
                ->count();
            if ($count > 0) {
                $issues[] = ['issue' => 'Cross-tenant enrollment', 'table' => 'student_enrollments', 'count' => $count, 'suggestion' => 'Fix institute_id mismatch'];
            }
        }
        return ['status' => empty($issues) ? 'PASS' : 'WARNING', 'issues' => $issues];
    }

    public function report(): string
    {
        $res = $this->check();
        $lines = ["DATABASE CONSISTENCY REPORT", str_repeat("=", 40)];
        $map = [
            'Tenant Integrity' => $res['tenant'],
            'Relationship Integrity' => $res['relationships'],
            'Soft Delete Integrity' => $res['soft_delete'],
            'Accounting Integrity' => $res['accounting'],
            'Inventory Integrity' => $res['inventory'],
            'Cross Tenant Integrity' => $res['cross_tenant'],
        ];
        foreach ($map as $name => $data) {
            $status = $data['status'] ?? (empty($data['issues']) ? 'PASS' : 'WARNING');
            $lines[] = "$name: $status";
            if (! empty($data['issues'])) {
                foreach ($data['issues'] as $iss) {
                    $lines[] = "  - {$iss['issue']} | {$iss['table']} | {$iss['count']}";
                }
            }
        }
        $lines[] = "Overall: {$res['overall']}";
        return implode("\n", $lines);
    }
}
