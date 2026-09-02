<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widen exam mark columns so component marks can exceed 9999 (e.g. 30000).
 * decimal(6,2) caps at 9999.99; decimal(10,2) caps at 99999999.99.
 */
return new class extends Migration
{
    private const TABLES = [
        'exam_subjects' => ['written_marks', 'practical_marks', 'viva_marks', 'other_marks'],
        'exam_results' => ['written_marks', 'practical_marks', 'viva_marks', 'other_marks'],
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $columns) {
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }
                DB::statement("ALTER TABLE `$table` MODIFY `$column` DECIMAL(10,2) ".($table === 'exam_results' ? 'NULL' : 'NOT NULL DEFAULT 0'));
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => $columns) {
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }
                DB::statement("ALTER TABLE `$table` MODIFY `$column` DECIMAL(6,2)");
            }
        }
    }
};
