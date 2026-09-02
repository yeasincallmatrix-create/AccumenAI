<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Institute-level requirement overrides on institute_subjects.
 *
 * An institute may deviate from the global curriculum per subject:
 *
 *   - requirement_type   → null = inherit global assignment value
 *   - selection_group_id → null = inherit global membership
 *   - minimum_selection / maximum_selection → when set on any member subject
 *     of a selection group, they define the institute's rule for that group
 *     (e.g. "Choose 1 from Group A"). The resolver picks the values from the
 *     first overridden member (lowest display order) and falls back to the
 *     group defaults for any null field.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institute_subjects', function (Blueprint $table) {
            if (! Schema::hasColumn('institute_subjects', 'requirement_type')) {
                $table->string('requirement_type', 20)->nullable()->after('display_order');
            }
            if (! Schema::hasColumn('institute_subjects', 'selection_group_id')) {
                $table->foreignId('selection_group_id')->nullable()->after('requirement_type')
                    ->constrained('academic_selection_groups')->nullOnDelete();
            }
            if (! Schema::hasColumn('institute_subjects', 'minimum_selection')) {
                $table->unsignedInteger('minimum_selection')->nullable()->after('selection_group_id');
            }
            if (! Schema::hasColumn('institute_subjects', 'maximum_selection')) {
                $table->unsignedInteger('maximum_selection')->nullable()->after('minimum_selection');
            }
        });
    }

    public function down(): void
    {
        Schema::table('institute_subjects', function (Blueprint $table) {
            foreach (['maximum_selection', 'minimum_selection', 'selection_group_id', 'requirement_type'] as $column) {
                if (Schema::hasColumn('institute_subjects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
