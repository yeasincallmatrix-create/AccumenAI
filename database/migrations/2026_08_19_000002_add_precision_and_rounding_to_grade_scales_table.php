<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add configurable decimal precision and rounding mode to grade scales.
 *
 * Safe defaults match the current hardcoded behavior:
 *   marks_decimal_places    = 2
 *   percentage_decimal_places = 2
 *   gpa_decimal_places      = 2
 *   cgpa_decimal_places     = 2
 *   rounding_mode           = half_up
 *
 * Existing behavior before this change: 2 decimals everywhere.
 * Existing behavior after this change: 2 decimals everywhere.
 * Only changed when an institute explicitly updates the configuration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grade_scales', function (Blueprint $table) {
            if (! Schema::hasColumn('grade_scales', 'marks_decimal_places')) {
                $table->unsignedTinyInteger('marks_decimal_places')->default(2)->after('optional_subject_gpa');
            }
            if (! Schema::hasColumn('grade_scales', 'percentage_decimal_places')) {
                $table->unsignedTinyInteger('percentage_decimal_places')->default(2)->after('marks_decimal_places');
            }
            if (! Schema::hasColumn('grade_scales', 'gpa_decimal_places')) {
                $table->unsignedTinyInteger('gpa_decimal_places')->default(2)->after('percentage_decimal_places');
            }
            if (! Schema::hasColumn('grade_scales', 'cgpa_decimal_places')) {
                $table->unsignedTinyInteger('cgpa_decimal_places')->default(2)->after('gpa_decimal_places');
            }
            if (! Schema::hasColumn('grade_scales', 'rounding_mode')) {
                $table->string('rounding_mode', 20)->default('half_up')->after('cgpa_decimal_places');
            }
        });
    }

    public function down(): void
    {
        Schema::table('grade_scales', function (Blueprint $table) {
            foreach (['marks_decimal_places', 'percentage_decimal_places', 'gpa_decimal_places', 'cgpa_decimal_places', 'rounding_mode'] as $column) {
                if (Schema::hasColumn('grade_scales', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
