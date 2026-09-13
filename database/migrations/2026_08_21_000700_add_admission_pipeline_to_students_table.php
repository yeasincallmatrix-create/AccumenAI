<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 38 — CRM → Education admission pipeline.
 *
 * The admission pipeline funnel already lives on the students row
 * (admission_status, admission_source, applied_course_id,
 * applied_academic_year_id, application_number) so no parallel entity is
 * added. Only the two genuinely-missing admission-pipeline fields are added:
 *   - preferred_batch_id        : the batch the applicant prefers (used at
 *                                 enrollment time; still the existing enrollment
 *                                 flow decides the final batch).
 *   - admission_assigned_user_id: the institute staff member responsible for
 *                                 the admission (defaults from the CRM lead's
 *                                 assigned_user_id when converting a lead).
 * Both are optional references, tenant/branch isolation comes from the owning
 * students row. No parallel CRM/student/enrollment/finance logic is added.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->unsignedBigInteger('preferred_batch_id')->nullable()->after('applied_academic_year_id');
            $table->unsignedBigInteger('admission_assigned_user_id')->nullable()->after('preferred_batch_id');

            $table->index('preferred_batch_id', 'students_preferred_batch_idx');
            $table->index('admission_assigned_user_id', 'students_admission_assigned_idx');

            $table->foreign('preferred_batch_id', 'students_preferred_batch_fk')
                ->references('id')
                ->on('batches')
                ->nullOnDelete();

            $table->foreign('admission_assigned_user_id', 'students_admission_assigned_fk')
                ->references('id')
                ->on('institute_users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['students_preferred_batch_fk']);
            $table->dropForeign(['students_admission_assigned_fk']);
            $table->dropIndex(['preferred_batch_id']);
            $table->dropIndex(['admission_assigned_user_id']);
            $table->dropColumn(['preferred_batch_id', 'admission_assigned_user_id']);
        });
    }
};
