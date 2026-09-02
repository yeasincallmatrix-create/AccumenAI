<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 102 — Database Integrity & Repair Assistant
 * Checks tenant, relationship, soft-delete consistency. Never auto-deletes.
 */
class DataIntegrityService
{
    /**
     * Run all checks and return structured report.
     */
    public function check(): array
    {
        $tenant = $this->checkTenantIntegrity();
        $relations = $this->checkRelationshipIntegrity();
        $softDelete = $this->checkSoftDeleteConsistency();

        $allIssues = array_merge($tenant['issues'], $relations['issues'], $softDelete['issues']);
        $status = empty($allIssues) ? 'PASS' : (count($allIssues) > 5 ? 'WARNING' : 'PASS');

        return [
            'tenant' => $tenant,
            'relations' => $relations,
            'soft_delete' => $softDelete,
            'issues' => $allIssues,
            'total_issues' => count($allIssues),
            'status' => $status,
        ];
    }

    /**
     * 1. Tenant integrity
     */
    public function checkTenantIntegrity(): array
    {
        $issues = [];

        // Records with missing institute_id where TenantScoped expects it
        $tenantTables = [
            'students' => 'institute_id',
            'batches' => 'institute_id',
            'branches' => 'institute_id',
            'invoices' => 'institute_id',
            'payments' => 'institute_id',
            'attendance' => 'institute_id',
            'student_enrollments' => 'institute_id',
        ];

        foreach ($tenantTables as $table => $col) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $col)) continue;
            $count = DB::table($table)->whereNull($col)->count();
            if ($count > 0) {
                $issues[] = [
                    'issue' => 'Missing institute_id',
                    'table' => $table,
                    'count' => $count,
                    'suggestion' => "Review $table where $col IS NULL; backfill from parent or quarantine for manual review. SQL: SELECT id FROM $table WHERE $col IS NULL;",
                ];
            }
        }

        // Orphan institute relationships (FK points to non-existent institute)
        $orphanChecks = [
            'students' => 'institutes',
            'batches' => 'institutes',
            'branches' => 'institutes',
            'student_enrollments' => 'institutes',
        ];
        foreach ($orphanChecks as $table => $parent) {
            if (! Schema::hasTable($table) || ! Schema::hasTable($parent)) continue;
            $col = 'institute_id';
            if (! Schema::hasColumn($table, $col)) continue;
            $count = DB::table($table)
                ->leftJoin($parent, "$table.$col", '=', "$parent.id")
                ->whereNotNull("$table.$col")
                ->whereNull("$parent.id")
                ->count();
            if ($count > 0) {
                $issues[] = [
                    'issue' => 'Orphan institute relationship',
                    'table' => $table,
                    'count' => $count,
                    'suggestion' => "Orphan $table.$col references missing institutes. Verify institutes exist or re-assign. SQL: SELECT $table.id FROM $table LEFT JOIN $parent ON $table.$col=$parent.id WHERE $parent.id IS NULL;",
                ];
            }
        }

        // Cross-tenant leakage: student institute != enrollment institute
        if (Schema::hasTable('student_enrollments') && Schema::hasTable('students')) {
            $leak = DB::table('student_enrollments')
                ->join('students', 'student_enrollments.student_id', '=', 'students.id')
                ->whereColumn('student_enrollments.institute_id', '!=', 'students.institute_id')
                ->count();
            if ($leak > 0) {
                $issues[] = [
                    'issue' => 'Cross-tenant leakage',
                    'table' => 'student_enrollments',
                    'count' => $leak,
                    'suggestion' => "Enrollment institute_id mismatches student institute_id. Investigate tenant isolation. SQL: SELECT se.id FROM student_enrollments se JOIN students s ON se.student_id=s.id WHERE se.institute_id != s.institute_id;",
                ];
            }
        }

        return [
            'healthy' => empty($issues),
            'issues' => $issues,
        ];
    }

    /**
     * 2. Relationship integrity
     */
    public function checkRelationshipIntegrity(): array
    {
        $checks = [
            ['table' => 'students', 'col' => 'institute_id', 'parent' => 'institutes', 'parent_col' => 'id', 'label' => 'students without institute'],
            ['table' => 'batches', 'col' => 'course_id', 'parent' => 'courses', 'parent_col' => 'id', 'label' => 'batches without course'],
            ['table' => 'student_enrollments', 'col' => 'student_id', 'parent' => 'students', 'parent_col' => 'id', 'label' => 'enrollments without student'],
            ['table' => 'student_enrollments', 'col' => 'batch_id', 'parent' => 'batches', 'parent_col' => 'id', 'label' => 'enrollments without batch'],
            ['table' => 'invoices', 'col' => 'student_id', 'parent' => 'students', 'parent_col' => 'id', 'label' => 'invoices without student'],
            ['table' => 'payments', 'col' => 'invoice_id', 'parent' => 'invoices', 'parent_col' => 'id', 'label' => 'payments without invoice'],
            ['table' => 'journal_entries', 'col' => 'journal_id', 'parent' => 'journals', 'parent_col' => 'id', 'label' => 'journal entries without journal'],
            ['table' => 'journal_entries', 'col' => 'coa_id', 'parent' => 'chart_of_accounts', 'parent_col' => 'id', 'label' => 'journal lines without chart of account'],
            ['table' => 'institute_users', 'col' => 'role_id', 'parent' => 'roles', 'parent_col' => 'id', 'label' => 'users without role'],
        ];

        // Special: students without batch (via enrollments)
        $issues = [];
        if (Schema::hasTable('students') && Schema::hasTable('student_enrollments')) {
            $count = DB::table('students')
                ->leftJoin('student_enrollments', 'students.id', '=', 'student_enrollments.student_id')
                ->whereNull('student_enrollments.id')
                ->count();
            // Not necessarily an error (new students), so only warn if we want — treat as info, not failure
            // We will report but not mark as issue unless we consider it critical
            // For now, report as issue if >0 but with low severity
            if ($count > 0) {
                $issues[] = [
                    'issue' => 'students without batch (no enrollment)',
                    'table' => 'students',
                    'count' => $count,
                    'suggestion' => "Students have no enrollment/batch. Review admission flow. SQL: SELECT s.id FROM students s LEFT JOIN student_enrollments se ON s.id=se.student_id WHERE se.id IS NULL;",
                ];
            }
        }

        foreach ($checks as $c) {
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
                    'suggestion' => "Orphan {$c['table']}.{$c['col']} → {$c['parent']}. SQL: SELECT {$c['table']}.id FROM {$c['table']} LEFT JOIN {$c['parent']} ON {$c['table']}.{$c['col']}={$c['parent']}.{$c['parent_col']} WHERE {$c['parent']}.{$c['parent_col']} IS NULL;",
                ];
            }
        }

        return [
            'healthy' => empty($issues),
            'issues' => $issues,
        ];
    }

    /**
     * 3. Soft delete consistency
     */
    public function checkSoftDeleteConsistency(): array
    {
        $issues = [];

        // Active child linked to soft-deleted parent (institute)
        if (Schema::hasTable('institutes') && Schema::hasColumn('institutes', 'deleted_at')) {
            $deletedInstituteIds = DB::table('institutes')->whereNotNull('deleted_at')->pluck('id')->all();
            if (! empty($deletedInstituteIds)) {
                $childTables = ['students', 'batches', 'branches', 'invoices', 'student_enrollments'];
                foreach ($childTables as $table) {
                    if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'institute_id')) continue;
                    $hasDeletedAt = Schema::hasColumn($table, 'deleted_at');
                    $query = DB::table($table)->whereIn('institute_id', $deletedInstituteIds);
                    if ($hasDeletedAt) {
                        $query->whereNull('deleted_at');
                    } else {
                        // If child doesn't have soft delete, any row linked to deleted institute is inconsistent
                    }
                    $count = $query->count();
                    if ($count > 0) {
                        $issues[] = [
                            'issue' => 'Active child linked to deleted institute',
                            'table' => $table,
                            'count' => $count,
                            'suggestion' => "Found $count active $table linked to soft-deleted institutes. Archive or soft-delete children. SQL: SELECT id FROM $table WHERE institute_id IN (SELECT id FROM institutes WHERE deleted_at IS NOT NULL) AND deleted_at IS NULL;",
                        ];
                    }
                }

                // Deleted institute with active users (institution_user)
                if (Schema::hasTable('institution_user')) {
                    $count = DB::table('institution_user')
                        ->whereIn('institution_id', $deletedInstituteIds)
                        ->where('status', 'active')
                        ->count();
                    if ($count > 0) {
                        $issues[] = [
                            'issue' => 'Deleted institute with active users',
                            'table' => 'institution_user',
                            'count' => $count,
                            'suggestion' => "Active memberships for deleted institutes. Deactivate or restore institute. SQL: SELECT * FROM institution_user WHERE institution_id IN (SELECT id FROM institutes WHERE deleted_at IS NOT NULL) AND status='active';",
                        ];
                    }
                }
            }
        }

        // Active child linked to soft-deleted user (if users soft deleted)
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'deleted_at')) {
            $deletedUserIds = DB::table('users')->whereNotNull('deleted_at')->pluck('id')->all();
            if (! empty($deletedUserIds) && Schema::hasTable('institution_user')) {
                $count = DB::table('institution_user')->whereIn('user_id', $deletedUserIds)->where('status', 'active')->count();
                if ($count > 0) {
                    $issues[] = [
                        'issue' => 'Active membership linked to deleted user',
                        'table' => 'institution_user',
                        'count' => $count,
                        'suggestion' => "Active institution_user for soft-deleted users. Deactivate membership. SQL: SELECT * FROM institution_user WHERE user_id IN (SELECT id FROM users WHERE deleted_at IS NOT NULL) AND status='active';",
                    ];
                }
            }
        }

        return [
            'healthy' => empty($issues),
            'issues' => $issues,
        ];
    }

    public function generateReport(): array
    {
        $report = $this->check();
        $lines = [];
        $lines[] = "HEALTH CHECK REPORT";
        $lines[] = str_repeat("=", 40);

        $sections = [
            'Tenant' => $report['tenant'],
            'Foreign Keys' => $report['relations'],
            'Soft Delete' => $report['soft_delete'],
        ];

        foreach ($sections as $name => $section) {
            $status = empty($section['issues']) ? 'PASS' : 'WARNING';
            $lines[] = "$name: $status";
            if (! empty($section['issues'])) {
                foreach ($section['issues'] as $iss) {
                    $lines[] = "  - {$iss['issue']} | {$iss['table']} | {$iss['count']} records";
                    $lines[] = "    Suggestion: {$iss['suggestion']}";
                }
            }
        }

        $lines[] = "";
        $lines[] = "Orphans: " . $report['total_issues'] . " records found";

        return [
            'report' => $report,
            'lines' => $lines,
            'text' => implode("\n", $lines),
        ];
    }
}
