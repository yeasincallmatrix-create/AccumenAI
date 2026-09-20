<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B85: retarget aggregation-item assessment FK to academic_assessments.
 *
 * Drift: academic_result_aggregation_items.academic_assessment_id references
 * academic_result_aggregation_schemes(id) (likely a copy-paste of the
 * scheme_id FK), but the entire app — AcademicResultAggregationItem::
 * assessment() belongsTo, service joins to academic_assessments, item
 * writers storing $assessment->id — uses academic_assessments(id).
 * Tests creating items for real assessments hit 1452 (69 errors).
 *
 * Data safety (verified 2026-09-20): the items table holds 0 rows in both
 * accumen_ai and monetix_test, so no existing row can violate the target.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('academic_result_aggregation_items')) {
            return;
        }

        if ($this->referencedTable('academic_result_aggregation_items_academic_assessment_id_foreign') === 'academic_assessments') {
            return;
        }

        Schema::table('academic_result_aggregation_items', function (Blueprint $table) {
            $table->dropForeign(['academic_assessment_id']);
        });

        Schema::table('academic_result_aggregation_items', function (Blueprint $table) {
            $table->foreign('academic_assessment_id', 'academic_result_aggregation_items_academic_assessment_id_foreign')
                ->references('id')->on('academic_assessments')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('academic_result_aggregation_items')) {
            return;
        }

        Schema::table('academic_result_aggregation_items', function (Blueprint $table) {
            $table->dropForeign(['academic_assessment_id']);
        });

        Schema::table('academic_result_aggregation_items', function (Blueprint $table) {
            $table->foreign('academic_assessment_id', 'academic_result_aggregation_items_academic_assessment_id_foreign')
                ->references('id')->on('academic_result_aggregation_schemes')
                ->restrictOnDelete();
        });
    }

    private function referencedTable(string $constraint): ?string
    {
        $row = DB::selectOne(
            "SELECT REFERENCED_TABLE_NAME AS t FROM information_schema.key_column_usage
             WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ?",
            [$constraint]
        );

        return $row?->t;
    }
};
