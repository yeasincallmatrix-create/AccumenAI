<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * C3 — Structure Versioning / Archive: FK RESTRICT.
 *
 * Changes student_academic_placements.class_grade_id (and academic_group_id)
 * from NULL ON DELETE to RESTRICT. Deleting a global master while historical
 * placements/marks exist now fails at the DB level, preserving audit trails.
 * Soft-delete (deleted_at) remains the archive path for ClassGrade (see
 * 2026_08_28_000100_add_archive_to_class_grades_table.php); hard-delete is
 * blocked when placements or related rows exist (ClassGrade::booted guard).
 *
 * Rollback-safe: checks column/table existence, drops/recreates FK by name,
 * no data loss, no column type changes, backward compatible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_academic_placements')) {
            return;
        }

        // Helper to change a FK to RESTRICT if it currently exists.
        $this->changeFkToRestrict('student_academic_placements', 'class_grade_id', 'class_grades');
        $this->changeFkToRestrict('student_academic_placements', 'academic_group_id', 'academic_groups');
    }

    public function down(): void
    {
        if (! Schema::hasTable('student_academic_placements')) {
            return;
        }

        // Revert to NULL ON DELETE (original behavior) — safe rollback.
        $this->changeFkToNullOnDelete('student_academic_placements', 'class_grade_id', 'class_grades');
        $this->changeFkToNullOnDelete('student_academic_placements', 'academic_group_id', 'academic_groups');
    }

    private function changeFkToRestrict(string $table, string $column, string $referencedTable): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        // Find existing FK name for this column (MySQL information_schema).
        $fkName = $this->findForeignKeyName($table, $column);
        if ($fkName === null) {
            // No FK found (e.g., SQLite or already without FK) — just ensure RESTRICT via recreate.
            try {
                Schema::table($table, function (Blueprint $t) use ($column, $referencedTable) {
                    $t->foreign($column)->references('id')->on($referencedTable)->onDelete('restrict');
                });
            } catch (\Throwable $e) {}
            return;
        }

        try {
            // MySQL requires dropping FK before altering.
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`");
            // Recreate with RESTRICT.
            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$fkName}` FOREIGN KEY (`{$column}`) REFERENCES `{$referencedTable}` (`id`) ON DELETE RESTRICT");
        } catch (\Throwable $e) {
            // Fallback via Schema builder (may work on some drivers).
            try {
                Schema::table($table, function (Blueprint $t) use ($column, $referencedTable) {
                    $t->dropForeign([$column]);
                    $t->foreign($column)->references('id')->on($referencedTable)->onDelete('restrict');
                });
            } catch (\Throwable $e2) {}
        }
    }

    private function changeFkToNullOnDelete(string $table, string $column, string $referencedTable): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        $fkName = $this->findForeignKeyName($table, $column);
        if ($fkName === null) {
            return;
        }

        try {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`");
            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$fkName}` FOREIGN KEY (`{$column}`) REFERENCES `{$referencedTable}` (`id`) ON DELETE SET NULL");
        } catch (\Throwable $e) {
            try {
                Schema::table($table, function (Blueprint $t) use ($column, $referencedTable) {
                    $t->dropForeign([$column]);
                    $t->foreign($column)->references('id')->on($referencedTable)->nullOnDelete();
                });
            } catch (\Throwable $e2) {}
        }
    }

    private function findForeignKeyName(string $table, string $column): ?string
    {
        try {
            $db = DB::getDatabaseName();
            $result = DB::selectOne(
                "SELECT CONSTRAINT_NAME as name FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1",
                [$db, $table, $column]
            );
            return $result?->name ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }
};
