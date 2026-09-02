<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each subject in an exam now carries its own pass mark. The value decides
 * pass/fail for that subject on the marks-entry screen (total >= pass_marks).
 * Existing rows are backfilled to 40% of each subject's recorded max marks,
 * matching the exam-level default that was applied before.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('exam_subjects', 'pass_marks')) {
            Schema::table('exam_subjects', function (Blueprint $table) {
                $table->decimal('pass_marks', 10, 2)->nullable()->after('attendance_marks');
            });
        }

        DB::statement(
            'UPDATE exam_subjects
             SET pass_marks = ROUND(
                 (COALESCE(written_marks, 0) + COALESCE(practical_marks, 0)
                 + COALESCE(viva_marks, 0) + COALESCE(attendance_marks, 0)
                 + COALESCE(other_marks, 0)) * 0.40, 2)
             WHERE pass_marks IS NULL OR pass_marks <= 0'
        );
    }

    public function down(): void
    {
        if (Schema::hasColumn('exam_subjects', 'pass_marks')) {
            Schema::table('exam_subjects', function (Blueprint $table) {
                $table->dropColumn('pass_marks');
            });
        }
    }
};
