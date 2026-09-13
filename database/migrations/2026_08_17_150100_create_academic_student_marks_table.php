<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Academic student marks: one row per (student, assessment component).
 *
 * Context is complete (academic_assessment / assessment_subject / component /
 * placement), never a generic student_marks table. The full-marks interpretation
 * is inherited from the linked assessment_subject_components row, so historical
 * subjects keep their mark configuration as long as that row exists.
 *
 * - status 'entered' -> obtained_mark holds the value (0 is a real zero).
 * - status 'absent'  -> obtained_mark is NULL; absence is NOT stored as zero.
 * - no row at all    -> not entered.
 *
 * Uniqueness (assessment_component_id, student_id) prevents duplicate current
 * marks for the same assessment+subject+component at the database level.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('academic_student_marks')) {
            Schema::create('academic_student_marks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
                $table->foreignId('academic_assessment_id')->constrained('academic_assessments')->cascadeOnDelete();
                $table->foreignId('assessment_subject_id')->constrained('assessment_subjects')->cascadeOnDelete();
                $table->foreignId('assessment_component_id')->constrained('assessment_subject_components')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->foreignId('academic_placement_id')->constrained('student_academic_placements')->cascadeOnDelete();
                $table->decimal('obtained_mark', 10, 2)->nullable();
                $table->string('status', 20)->default('entered');
                $table->foreignId('entered_by')->nullable()->constrained('institute_users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('institute_users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['assessment_component_id', 'student_id'], 'asm_component_student_unique');
                $table->index('student_id', 'asm_student_idx');
                $table->index('academic_assessment_id', 'asm_assessment_idx');
                $table->index('assessment_subject_id', 'asm_subject_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_student_marks');
    }
};
