<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Individual published results contributing to a CGPA.
 *
 * Each entry links one published AcademicFinalResult to the cumulative
 * record. Values are snapshots taken at the time the CGPA was computed
 * and are never re-derived from live marks — historical safety is
 * guaranteed by the immutable final-result snapshots.
 *
 * The unique(cumulative_result_id, final_result_id) constraint prevents
 * duplicate entries for the same published result.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('academic_cumulative_result_entries')) {
            Schema::create('academic_cumulative_result_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cumulative_result_id')->constrained('academic_cumulative_results')->cascadeOnDelete();
                $table->foreignId('final_result_id')->constrained('academic_final_results')->cascadeOnDelete();
                $table->decimal('gpa', 5, 2)->nullable();
                $table->decimal('grade_points_earned', 10, 2)->default(0);
                $table->decimal('credits_earned', 10, 2)->default(0);
                $table->unsignedSmallInteger('subjects_passed')->default(0);
                $table->unsignedSmallInteger('subjects_failed')->default(0);
                $table->timestamps();

                $table->unique(
                    ['cumulative_result_id', 'final_result_id'],
                    'acume_cumulative_final_unique'
                );
                $table->index('final_result_id', 'acume_final_result_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_cumulative_result_entries');
    }
};
