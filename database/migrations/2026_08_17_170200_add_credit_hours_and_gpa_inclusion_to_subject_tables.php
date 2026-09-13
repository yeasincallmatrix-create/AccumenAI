<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credit hours + GPA inclusion flags for subjects.
 *
 *   - credit_hours nullable decimal → how many credits the subject carries.
 *     NULL / 0 on the GLOBAL assignment (subject_academic_assignments) means
 *     "no credit declared" — the GPA resolver must NEVER invent a credit value
 *     (the spec forbids fabricating credits). An institute may optionally
 *     declare credits per subject via institute_subjects.credit_hours, which
 *     acts as the override in the same Global → Country → Institute ladder
 *     used everywhere else (null = inherit).
 *
 *   - gpa_included boolean → whether the subject's grade point may enter the
 *     GPA. This is the subject-level inclusion switch (used for non-credit,
 *     pass/fail-only or institution-excluded subjects and optional subjects
 *     when the scale policy = excluded). Like credit_hours, it exists on the
 *     global assignment (default true) and may be overridden per institute
 *     (null = inherit global).
 *
 * Columns are nullable on institute_subjects and single-valued defaults on
 * subject_academic_assignments so untouched rows keep inheriting cleanly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subject_academic_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('subject_academic_assignments', 'credit_hours')) {
                $table->decimal('credit_hours', 5, 2)->nullable()->after('selection_group_id');
            }
            if (! Schema::hasColumn('subject_academic_assignments', 'gpa_included')) {
                $table->boolean('gpa_included')->default(true)->after('credit_hours');
            }
        });

        Schema::table('institute_subjects', function (Blueprint $table) {
            if (! Schema::hasColumn('institute_subjects', 'credit_hours')) {
                $table->decimal('credit_hours', 5, 2)->nullable()->after('maximum_selection');
            }
            if (! Schema::hasColumn('institute_subjects', 'gpa_included')) {
                $table->boolean('gpa_included')->nullable()->after('credit_hours');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subject_academic_assignments', function (Blueprint $table) {
            foreach (['gpa_included', 'credit_hours'] as $column) {
                if (Schema::hasColumn('subject_academic_assignments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('institute_subjects', function (Blueprint $table) {
            foreach (['gpa_included', 'credit_hours'] as $column) {
                if (Schema::hasColumn('institute_subjects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
