<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Selection groups for academic subject requirement types.
 *
 * A selection group is a named set of subjects within one class/grade (and
 * optionally one academic group/stream) from which students select a number of
 * subjects (minimum_selection .. maximum_selection). Members are declared by
 * pointing a subject_academic_assignments row's selection_group_id at a group.
 *
 *   - class_grade_id     → required; the group belongs to one class/grade
 *   - academic_group_id  → optional; null = whole-class group
 *   - selection_type     → 'optional' | 'elective' (mandatory subjects cannot
 *                          be members of a selection group)
 *   - minimum_selection  → min subjects the student must pick (default 1)
 *   - maximum_selection  → max subjects the student may pick (default = size)
 *
 * Global reference data, country-scoped via the class — NOT TenantScoped
 * (the same structure applies across institutes; institutes override per
 * subject through institute_subjects).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_selection_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_grade_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('code', 40);
            $table->string('selection_type', 20)->default('optional');
            $table->unsignedInteger('minimum_selection')->nullable();
            $table->unsignedInteger('maximum_selection')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->unique(['class_grade_id', 'code'], 'asg_class_code_unique');
            $table->index(['class_grade_id', 'status', 'display_order'], 'asg_class_status_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_selection_groups');
    }
};
