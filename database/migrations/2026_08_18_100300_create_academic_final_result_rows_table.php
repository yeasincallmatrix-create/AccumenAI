<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per (placement, subject) graded-result snapshot of a locked/published final
 * result — one row per student × covered subject.
 *
 * Mirrors AcademicFinalResultService::subjectResult() output:
 *   status          computed | incomplete | absent_only | not_eligible |
 *                   no_grade_scale | no_band
 *   aggregate       the numeric aggregate (NULL when not computed)
 *   grade / point   grade + grade point from the resolved band (NULL if none)
 *   subject_status  PASS / FAIL (only for graded rows) or NULL
 *   gpa_included    whether the subject contributed to the student GPA
 *   credits         declared credit hours (credit-weighted GPA mode)
 *   optional        optional/elective subject flag
 *
 * Written once at LOCK from the backend-computed preview; never edited after.
 * Rows are reached only through their tenant-scoped parent result.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('academic_final_result_rows')) {
            Schema::create('academic_final_result_rows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('result_id')->constrained('academic_final_results')->cascadeOnDelete();
                $table->foreignId('placement_id')->constrained('student_academic_placements')->cascadeOnDelete();
                $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
                $table->string('status', 20);
                $table->decimal('aggregate', 5, 2)->nullable();
                $table->string('grade', 10)->nullable();
                $table->decimal('grade_point', 4, 2)->nullable();
                $table->string('subject_status', 10)->nullable();
                $table->boolean('gpa_included')->default(false);
                $table->decimal('credits', 4, 2)->nullable();
                $table->boolean('optional')->default(false);
                $table->string('incomplete_reason')->nullable();
                $table->timestamps();

                $table->unique(['result_id', 'placement_id', 'subject_id'], 'afrr_result_placement_subject_unique');
                $table->index('placement_id', 'afrr_placement_idx');
                $table->index('subject_id', 'afrr_subject_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_final_result_rows');
    }
};
