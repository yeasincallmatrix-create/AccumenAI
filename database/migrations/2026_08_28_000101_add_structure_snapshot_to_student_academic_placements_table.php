<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C3 — Structure Versioning / Archive (StudentAcademicPlacement snapshot).
 *
 * Complements the in-place edit to 2026_08_17_130100 for fresh installs.
 * For databases already migrated before C3, this migration backfills the
 * snapshot columns so historical placements retain the effective structure
 * (class/group names + full snapshot JSON + version) even after a master
 * class is renamed/archived/deactivated.
 *
 * Rollback-safe: hasColumn guards, default values preserve existing rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_academic_placements')) {
            return;
        }

        Schema::table('student_academic_placements', function (Blueprint $table) {
            if (! Schema::hasColumn('student_academic_placements', 'structure_snapshot')) {
                $table->json('structure_snapshot')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('student_academic_placements', 'structure_version')) {
                $table->unsignedInteger('structure_version')->default(1)->after('structure_snapshot');
            }
            if (! Schema::hasColumn('student_academic_placements', 'class_grade_name_snapshot')) {
                $table->string('class_grade_name_snapshot', 120)->nullable()->after('structure_version');
            }
            if (! Schema::hasColumn('student_academic_placements', 'academic_group_name_snapshot')) {
                $table->string('academic_group_name_snapshot', 120)->nullable()->after('class_grade_name_snapshot');
            }
        });

        // Backfill existing rows with current names where null (best-effort, no overwrite).
        try {
            \Illuminate\Support\Facades\DB::table('student_academic_placements')
                ->whereNull('class_grade_name_snapshot')
                ->whereNotNull('class_grade_id')
                ->join('class_grades', 'class_grades.id', '=', 'student_academic_placements.class_grade_id')
                ->update([
                    'student_academic_placements.class_grade_name_snapshot' => \Illuminate\Support\Facades\DB::raw('class_grades.name'),
                ]);
        } catch (\Throwable $e) {
            // Non-fatal: snapshot will be populated on next placement create/update.
        }

        try {
            \Illuminate\Support\Facades\DB::table('student_academic_placements')
                ->whereNull('academic_group_name_snapshot')
                ->whereNotNull('academic_group_id')
                ->join('academic_groups', 'academic_groups.id', '=', 'student_academic_placements.academic_group_id')
                ->update([
                    'student_academic_placements.academic_group_name_snapshot' => \Illuminate\Support\Facades\DB::raw('academic_groups.name'),
                ]);
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        if (! Schema::hasTable('student_academic_placements')) {
            return;
        }

        Schema::table('student_academic_placements', function (Blueprint $table) {
            if (Schema::hasColumn('student_academic_placements', 'academic_group_name_snapshot')) {
                $table->dropColumn('academic_group_name_snapshot');
            }
            if (Schema::hasColumn('student_academic_placements', 'class_grade_name_snapshot')) {
                $table->dropColumn('class_grade_name_snapshot');
            }
            if (Schema::hasColumn('student_academic_placements', 'structure_version')) {
                $table->dropColumn('structure_version');
            }
            if (Schema::hasColumn('student_academic_placements', 'structure_snapshot')) {
                $table->dropColumn('structure_snapshot');
            }
        });
    }
};
