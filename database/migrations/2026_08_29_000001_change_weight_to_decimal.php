<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2-3 — Fix Weight Precision in Aggregations (DECIMAL).
 * Changes weight from float-tolerant storage to exact DECIMAL(5,2).
 * 5,2 supports 0.00–999.99 but business validates 0..100, and DB enforces
 * 2 decimal places, eliminating 50+50=99.999999 float errors.
 * Backward compatible: existing 8,2 values round to 5,2 without loss.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('academic_result_aggregation_items') && Schema::hasColumn('academic_result_aggregation_items', 'weight')) {
            Schema::table('academic_result_aggregation_items', function (Blueprint $table) {
                // Requires doctrine/dbal for change(); fallback to raw statement if not available.
                try {
                    $table->decimal('weight', 5, 2)->change();
                } catch (\Throwable $e) {
                    // MySQL raw fallback (avoids DBAL requirement).
                    \Illuminate\Support\Facades\DB::statement('ALTER TABLE `academic_result_aggregation_items` MODIFY `weight` DECIMAL(5,2) NOT NULL DEFAULT 0.00');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('academic_result_aggregation_items') && Schema::hasColumn('academic_result_aggregation_items', 'weight')) {
            Schema::table('academic_result_aggregation_items', function (Blueprint $table) {
                try {
                    $table->decimal('weight', 8, 2)->change();
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\DB::statement('ALTER TABLE `academic_result_aggregation_items` MODIFY `weight` DECIMAL(8,2) NOT NULL DEFAULT 0.00');
                }
            });
        }
    }
};
