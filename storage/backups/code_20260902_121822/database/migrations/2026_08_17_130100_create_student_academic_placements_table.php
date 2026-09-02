<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Academic placement: one student in one academic year assigned to a
 * class/grade (and optionally an academic group/stream).
 *
 * Deliberately a SEPARATE table from student_enrollments — that table remains
 * the professional/training enrollment (course + batch) and is untouched by
 * the Education Engine. Academic placements are institute-scoped (TenantScoped
 * via the model) and inherit branch context through the student.
 *
 * class_grade_id / academic_group_id are nullOnDelete so historical placements
 * survive a global master being removed; a placement is unique per
 * (student, academic_year) which preserves year-over-year promotion history
 * without overwriting it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_academic_placements')) {
            Schema::create('student_academic_placements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
                $table->foreignId('class_grade_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('academic_group_id')->nullable()->constrained()->nullOnDelete();
                $table->string('status', 20)->default('active'); // active | completed | transferred | dropped
                $table->string('notes', 500)->nullable();
                // C3 — Structure Versioning / Archive: frozen snapshot of the effective structure at placement time.
                $table->json('structure_snapshot')->nullable()->after('notes');
                $table->unsignedInteger('structure_version')->default(1)->after('structure_snapshot');
                $table->string('class_grade_name_snapshot', 120)->nullable()->after('structure_version');
                $table->string('academic_group_name_snapshot', 120)->nullable()->after('class_grade_name_snapshot');
                $table->timestamps();

                $table->unique(['student_id', 'academic_year_id']);
                $table->index(['institute_id', 'academic_year_id', 'status'], 'sap_institute_year_status_idx');
            });
        }

        // Idempotent alteration for databases already migrated before C3 (rollback-safe, no data loss).
        if (Schema::hasTable('student_academic_placements')) {
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
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_academic_placements');
    }
};
