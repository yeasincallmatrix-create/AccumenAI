<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subject-level pass rule for an assessment+subject.
 *
 * Step 7 supports three subject pass rules:
 *   total_only          - subject passes when SUM(obtained) >= SUM(component pass marks)
 *   mandatory_components- subject passes when every mandatory_pass component is passed
 *   both                - SUM(obtained) >= SUM(pass marks) AND all mandatory components pass
 *
 * Component pass config (pass_mark / mandatory_pass) lives on
 * assessment_subject_components and is NOT duplicated here; overall pass mark is
 * derived as the sum of component pass marks (single source of truth).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('assessment_subjects', 'pass_rule')) {
            Schema::table('assessment_subjects', function (Blueprint $table) {
                $table->string('pass_rule', 30)->default('total_only')->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('assessment_subjects', 'pass_rule')) {
            Schema::table('assessment_subjects', function (Blueprint $table) {
                $table->dropColumn('pass_rule');
            });
        }
    }
};
