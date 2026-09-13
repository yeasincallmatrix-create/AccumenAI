<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-student GPA snapshot of a locked/published final result.
 *
 * Rows are ONLY written by AcademicFinalResultLifecycleService::lock() from
 * the backend-computed Step-9 preview, so the numbers shown after LOCK are
 * frozen even if a placement or its marks change later. There is deliberately
 * no institute_id here: rows are always reached through their tenant-scoped
 * parent (academic_final_results), never queried standalone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('academic_final_result_students')) {
            Schema::create('academic_final_result_students', function (Blueprint $table) {
                $table->id();
                $table->foreignId('result_id')->constrained('academic_final_results')->cascadeOnDelete();
                $table->foreignId('placement_id')->constrained('student_academic_placements')->cascadeOnDelete();
                $table->decimal('gpa', 4, 2)->nullable();
                $table->string('gpa_status', 20)->default('computed');
                $table->string('gpa_mode', 20)->nullable();
                $table->text('gpa_reason')->nullable();
                $table->unsignedSmallInteger('passed_count')->default(0);
                $table->unsignedSmallInteger('failed_count')->default(0);
                $table->timestamps();

                $table->unique(['result_id', 'placement_id'], 'afrs_result_placement_unique');
                $table->index('placement_id', 'afrs_placement_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_final_result_students');
    }
};
