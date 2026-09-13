<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C3 — Structure Versioning / Archive (ClassGrade).
 *
 * Adds soft-delete support to class_grades so that a historical class/grade
 * remains readable via withTrashed() for already-placed students. The
 * ClassGrade model (SoftDeletes + deleting guard) prevents hard deletion
 * while placements/assignments/assessments reference the grade; deactivation
 * via status=false remains the operational archive path.
 *
 * Rollback-safe: checks hasColumn/hasTable, no data loss, no FK changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('class_grades') && ! Schema::hasColumn('class_grades', 'deleted_at')) {
            Schema::table('class_grades', function (Blueprint $table) {
                $table->softDeletes()->after('metadata');
            });
        }

        // Also support versioning snapshot on academic_groups if needed (no-op if exists).
        if (Schema::hasTable('academic_groups') && ! Schema::hasColumn('academic_groups', 'deleted_at')) {
            Schema::table('academic_groups', function (Blueprint $table) {
                $table->softDeletes()->after('metadata');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('class_grades') && Schema::hasColumn('class_grades', 'deleted_at')) {
            Schema::table('class_grades', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('academic_groups') && Schema::hasColumn('academic_groups', 'deleted_at')) {
            Schema::table('academic_groups', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
