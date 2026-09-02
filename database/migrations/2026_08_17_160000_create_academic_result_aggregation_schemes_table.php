<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggregation schemes: one "final result" configuration per academic
 * context (year + class + optional group).
 *
 * A scheme does NOT change any assessment instance or marks row; it only
 * declares WHICH assessments participate in the aggregated final result and
 * (via academic_result_aggregation_items) the manually configured weight of
 * each participating assessment. The same assessment can appear in many
 * schemes, so weight is stored per (scheme, assessment), never on the
 * assessment master.
 *
 * Branch rule mirrors academic_assessments: branch_id NULL = whole-institute
 * scheme; otherwise the scheme belongs to one branch. The global branch scope
 * (AcademicResultAggregationScheme::booted) enforces visibility; branch_id is
 * never read from request input.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('academic_result_aggregation_schemes')) {
            Schema::create('academic_result_aggregation_schemes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
                $table->foreignId('class_grade_id')->constrained('class_grades')->cascadeOnDelete();
                $table->foreignId('academic_group_id')->nullable()->constrained('academic_groups')->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('status', 20)->default('draft');
                $table->unsignedInteger('display_order')->default(0);
                $table->foreignId('created_by')->nullable()->constrained('institute_users')->nullOnDelete();
                $table->timestamps();

                $table->index(['academic_year_id', 'class_grade_id'], 'ars_year_class_idx');
                $table->index('institute_id', 'ars_institute_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_result_aggregation_schemes');
    }
};
