<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Step 36 — admission workflow on the existing students entity (single
     * source of truth, no parallel "admission" table).
     *
     * The applicant IS a student row: admission_status drives the application
     * funnel (draft → submitted → under_review → approved → enrolled /
     * rejected / cancelled / withdrawn) while students.status keeps governing
     * the operational lifecycle. No historical rows are rewritten.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('application_number', 30)->nullable()->after('student_id_number');
            $table->date('application_date')->nullable()->after('application_number');
            $table->enum('admission_status', [
                'draft',
                'submitted',
                'under_review',
                'approved',
                'rejected',
                'cancelled',
                'enrolled',
                'withdrawn',
            ])->default('enrolled')->after('application_date');
            $table->string('admission_source', 60)->nullable()->after('admission_status');
            $table->string('admission_reject_reason', 255)->nullable()->after('admission_source');
            $table->unsignedBigInteger('applied_course_id')->nullable()->after('admission_reject_reason');
            $table->unsignedBigInteger('applied_academic_year_id')->nullable()->after('applied_course_id');

            $table->unique(['institute_id', 'application_number'], 'uq_students_inst_app_number');
            $table->index(['institute_id', 'admission_status'], 'idx_students_inst_admission_status');

            $table->foreign('applied_course_id', 'fk_students_applied_course')
                ->references('id')->on('courses')->nullOnDelete();
            $table->foreign('applied_academic_year_id', 'fk_students_applied_academic_year')
                ->references('id')->on('academic_years')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign('fk_students_applied_course');
            $table->dropForeign('fk_students_applied_academic_year');
            $table->dropUnique('uq_students_inst_app_number');
            $table->dropIndex('idx_students_inst_admission_status');
            $table->dropColumn([
                'application_number',
                'application_date',
                'admission_status',
                'admission_source',
                'admission_reject_reason',
                'applied_course_id',
                'applied_academic_year_id',
            ]);
        });
    }
};
