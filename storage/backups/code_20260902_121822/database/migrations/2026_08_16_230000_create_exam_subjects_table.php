<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Exams can now cover multiple subjects. Each selected subject stores its own
 * max marks for the Written / Practical / Viva components. Student marks are
 * then recorded per subject per component in exam_results.
 *
 * exam_results uniqueness moves from (exam_id, student_id) to
 * (exam_id, student_id, subject_id) so every subject row can carry marks.
 * The old composite unique is dropped BEFORE the new one is added, and a
 * dedicated exam_id index keeps the existing fk_exam_results_exam satisfied.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exam_subjects')) {
            Schema::create('exam_subjects', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('exam_id');
                $table->unsignedBigInteger('subject_id');
                $table->decimal('written_marks', 6, 2)->default(0);
                $table->decimal('practical_marks', 6, 2)->default(0);
                $table->decimal('viva_marks', 6, 2)->default(0);
                $table->timestamps();

                $table->unique(['exam_id', 'subject_id']);
                $table->index('subject_id');
                $table->foreign('exam_id')->references('id')->on('exams')->onDelete('cascade');
                $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
            });
        }

        if (! Schema::hasColumn('exam_results', 'subject_id')) {
            DB::statement('ALTER TABLE exam_results ADD COLUMN subject_id BIGINT(20) UNSIGNED NULL AFTER student_id');
        }

        // Ensure a dedicated index exists so fk_exam_results_exam stays valid.
        try {
            DB::statement('ALTER TABLE exam_results ADD INDEX idx_exam_results_exam (exam_id)');
        } catch (Throwable $e) {
            // Already present.
        }

        try {
            DB::statement('ALTER TABLE exam_results DROP INDEX uq_exam_results_exam_student');
        } catch (Throwable $e) {
            // Already migrated / index name differs.
        }

        try {
            DB::statement('ALTER TABLE exam_results ADD UNIQUE INDEX uq_exam_results_exam_student_subject (exam_id, student_id, subject_id)');
        } catch (Throwable $e) {
            // Already present.
        }

        try {
            DB::statement('ALTER TABLE exam_results ADD INDEX idx_exam_results_subject (subject_id)');
        } catch (Throwable $e) {
            // Already present.
        }

        try {
            DB::statement('ALTER TABLE exam_results ADD CONSTRAINT fk_exam_results_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE CASCADE');
        } catch (Throwable $e) {
            // Already present.
        }
    }

    public function down(): void
    {
        Schema::table('exam_results', function (Blueprint $table) {
            try {
                DB::statement('ALTER TABLE exam_results DROP FOREIGN KEY fk_exam_results_subject');
            } catch (Throwable $e) {
                // Not present.
            }
            try {
                DB::statement('ALTER TABLE exam_results DROP INDEX idx_exam_results_subject');
            } catch (Throwable $e) {
                // Not present.
            }
            try {
                $table->dropUnique('uq_exam_results_exam_student_subject');
            } catch (Throwable $e) {
                // Not present.
            }
            if (Schema::hasColumn('exam_results', 'subject_id')) {
                $table->dropColumn('subject_id');
            }
        });

        try {
            Schema::table('exam_results', function (Blueprint $table) {
                $table->unique(['exam_id', 'student_id'], 'uq_exam_results_exam_student');
            });
        } catch (Throwable $e) {
            // Not present.
        }

        Schema::dropIfExists('exam_subjects');
    }
};
