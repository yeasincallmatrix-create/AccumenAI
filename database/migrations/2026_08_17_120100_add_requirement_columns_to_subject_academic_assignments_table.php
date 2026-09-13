<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requirement type + selection group membership on subject_academic_assignments.
 *
 *   - requirement_type  → 'mandatory' | 'optional' | 'elective'
 *   - selection_group_id→ optional membership in an academic_selection_groups
 *                         row for the same class/grade. Mandatory subjects
 *                         should not be members of a selection group (the
 *                         admin UI enforces this).
 *
 * Deleting a selection group keeps the assignment but resets membership to
 * NULL, so the subject simply falls back to its own requirement type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subject_academic_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('subject_academic_assignments', 'requirement_type')) {
                $table->string('requirement_type', 20)->default('mandatory')->after('academic_group_id');
            }
            if (! Schema::hasColumn('subject_academic_assignments', 'selection_group_id')) {
                $table->foreignId('selection_group_id')->nullable()->after('requirement_type')
                    ->constrained('academic_selection_groups')->nullOnDelete();
            }
        });

        if (! Schema::hasIndex('subject_academic_assignments', 'saa_requirement_type_idx')) {
            Schema::table('subject_academic_assignments', function (Blueprint $table) {
                $table->index(['class_grade_id', 'requirement_type'], 'saa_requirement_type_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::table('subject_academic_assignments', function (Blueprint $table) {
            foreach (['selection_group_id', 'requirement_type'] as $column) {
                if (Schema::hasColumn('subject_academic_assignments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
