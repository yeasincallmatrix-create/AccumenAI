<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global, country-scoped subject ↔ class/grading assignments (shared reference
 * data — NOT tenant-scoped, no institute_id).
 *
 *   subjects (1) ─── has many subject_academic_assignments ─── (many) class_grades
 *
 * A class_grade assignment may optionally be narrowed to one academic_group
 * (stream). A row with academic_group_id = NULL means the subject is taught to
 * the whole class; otherwise it applies only to that group. `status` controls
 * whether the subject is active for that class (admin can disable it without
 * deleting the assignment).
 *
 * The virtual column `group_key` (= academic_group_id, or 0 when NULL) lets a
 * single UNIQUE index enforce "one assignment per subject/class/group" even
 * though MySQL treats NULLs as distinct inside UNIQUE indexes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_academic_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_grade_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('display_order')->default(0);
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->unsignedBigInteger('group_key')->virtualAs('IFNULL(academic_group_id, 0)');
            $table->unique(['subject_id', 'class_grade_id', 'group_key'], 'saa_subject_class_group_unique');
            $table->index(['class_grade_id', 'academic_group_id', 'status'], 'saa_class_group_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_academic_assignments');
    }
};
