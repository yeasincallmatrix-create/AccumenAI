<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Harden Subject FKs from CASCADE to RESTRICT for historical preservation.
     * SoftDeletes on subjects means hard delete (forceDelete) is the only
     * operation that triggers FK checks. RESTRICT ensures historical records
     * block forceDelete, while soft-delete (UPDATE deleted_at) is unaffected.
     *
     * Tables with SET NULL (student_subject_selections, calendar_events) are
     * left as-is for now; they are handled via SoftDeletes + withTrashed().
     */
    public function up(): void
    {
        // Helper to safely drop FK if exists and recreate with RESTRICT
        $restrict = function (string $table, string $column) {
            try {
                Schema::table($table, function (Blueprint $t) use ($column) {
                    // Drop by column name (Laravel resolves FK name)
                    try { $t->dropForeign([$column]); } catch (\Throwable $e) {}
                });
            } catch (\Throwable $e) {}
            // Re-add with RESTRICT (no cascade, no set null)
            try {
                Schema::table($table, function (Blueprint $t) use ($column) {
                    $t->foreign($column)->references('id')->on('subjects')->restrictOnDelete()->restrictOnUpdate();
                });
            } catch (\Throwable $e) {
                // If FK already restrict or table has orphan data, log and continue
                \Illuminate\Support\Facades\Log::warning('FK harden failed', ['table' => $table, 'column' => $column, 'error' => $e->getMessage()]);
            }
        };

        // Pre-flight: report orphans before changing
        $this->reportOrphans();

        $restrict('course_subjects', 'subject_id');
        $restrict('subject_academic_assignments', 'subject_id');
        $restrict('institute_subjects', 'subject_id');
        $restrict('exam_subjects', 'subject_id');
        $restrict('exam_results', 'subject_id');
        $restrict('assessment_subjects', 'subject_id');
        $restrict('academic_final_result_rows', 'subject_id');
        $restrict('teacher_academic_assignments', 'subject_id');
        // assessment_subject_components is indirect via assessment_subjects, no direct subject_id
        // student_subject_selections and calendar_events intentionally left as SET NULL for now
        // (historical reads use withTrashed, hard delete blocked via service anyway)
    }

    public function down(): void
    {
        $cascade = function (string $table, string $column, string $onDelete = 'cascade') {
            try {
                Schema::table($table, function (Blueprint $t) use ($column) {
                    try { $t->dropForeign([$column]); } catch (\Throwable $e) {}
                });
            } catch (\Throwable $e) {}
            try {
                Schema::table($table, function (Blueprint $t) use ($column, $onDelete) {
                    if ($onDelete === 'cascade') {
                        $t->foreign($column)->references('id')->on('subjects')->cascadeOnDelete();
                    } else {
                        $t->foreign($column)->references('id')->on('subjects')->nullOnDelete();
                    }
                });
            } catch (\Throwable $e) {}
        };
        $cascade('course_subjects', 'subject_id', 'cascade');
        $cascade('subject_academic_assignments', 'subject_id', 'cascade');
        $cascade('institute_subjects', 'subject_id', 'cascade');
        $cascade('exam_subjects', 'subject_id', 'cascade');
        $cascade('exam_results', 'subject_id', 'cascade');
        $cascade('assessment_subjects', 'subject_id', 'cascade');
        $cascade('academic_final_result_rows', 'subject_id', 'cascade');
        $cascade('teacher_academic_assignments', 'subject_id', 'cascade');
    }

    private function reportOrphans(): void
    {
        $tables = [
            'course_subjects', 'subject_academic_assignments', 'institute_subjects',
            'exam_subjects', 'exam_results', 'assessment_subjects',
            'academic_final_result_rows', 'teacher_academic_assignments',
        ];
        foreach ($tables as $table) {
            try {
                $orphans = DB::table($table)
                    ->leftJoin('subjects', 'subjects.id', '=', $table.'.subject_id')
                    ->whereNotNull($table.'.subject_id')
                    ->whereNull('subjects.id')
                    ->count();
                if ($orphans > 0) {
                    \Illuminate\Support\Facades\Log::warning('FK harden orphan detected', ['table' => $table, 'orphans' => $orphans]);
                }
            } catch (\Throwable $e) {}
        }
    }
};
