<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cumulative GPA (CGPA) tracking: one record per
 * institute + student + academic level context.
 *
 * One row tracks the running CGPA for a student across all published
 * final results that contribute to it. The CGPA is always computed from
 * frozen snapshot data (academic_final_result_students), never from
 * mutable live marks.
 *
 * institute_id is TenantScoped on the model. The unique key prevents
 * duplicate CGPA records for the same student/level context.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('academic_cumulative_results')) {
            Schema::create('academic_cumulative_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->foreignId('academic_level_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('cumulative_gpa', 5, 2)->nullable();
                $table->string('gpa_mode', 20)->nullable();
                $table->decimal('total_grade_points', 10, 2)->default(0);
                $table->decimal('total_credits', 10, 2)->default(0);
                $table->unsignedSmallInteger('periods_completed')->default(0);
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->unique(
                    ['institute_id', 'student_id', 'academic_level_id'],
                    'acumr_institute_student_level_unique'
                );
                $table->index('student_id', 'acumr_student_idx');
                $table->index(['institute_id', 'status'], 'acumr_institute_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_cumulative_results');
    }
};
