<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HR-7 — Recruitment & Hiring (industry-neutral, reuses CRM Lead/Contact).
 *
 * - hr_requisitions: headcount request (dept/design/branch, openings, skills, salary range)
 * - hr_vacancies: publishable vacancy derived from requisition (draft→published→closed)
 * - hr_applications: candidate (crm_leads) → vacancy linkage with pipeline stage
 * - hr_application_histories: stage transition audit (immutable)
 * - hr_interviews: schedule/interviewer/score/feedback
 * - hr_offers: offer letter + hiring data (no payroll)
 *
 * Candidate reuse: crm_leads (candidate) + crm_contacts (hired) via converted_contact_id pattern.
 * Documents reused via generic documents table (crm-lead entity already in config/documents.php).
 * Hiring creates hr_employees and links back to crm.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Requisitions
        Schema::create('hr_requisitions', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('designation_id')->nullable();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('openings')->default(1);
            $table->enum('employment_type', ['full_time','part_time','contractual','permanent','temporary','intern','probation'])->nullable();
            $table->text('required_skills')->nullable();
            $table->text('experience')->nullable();
            $table->string('education', 255)->nullable();
            $table->decimal('salary_min', 12, 2)->nullable();
            $table->decimal('salary_max', 12, 2)->nullable();
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->enum('status', ['draft','pending_approval','approved','rejected','published','closed','cancelled'])->default('draft');
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('institute_id', 'idx_hr_req_inst');
            $table->index('branch_id', 'idx_hr_req_branch');
            $table->index(['institute_id','status'], 'idx_hr_req_status');
            $table->index('department_id', 'idx_hr_req_dept');

            $table->foreign('institute_id', 'fk_hr_req_inst')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id', 'fk_hr_req_branch')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('department_id', 'fk_hr_req_dept')->references('id')->on('hr_departments')->nullOnDelete();
            $table->foreign('designation_id', 'fk_hr_req_desig')->references('id')->on('hr_designations')->nullOnDelete();
            $table->foreign('currency_id', 'fk_hr_req_currency')->references('id')->on('currencies')->nullOnDelete();
            $table->foreign('requested_by', 'fk_hr_req_requested')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('approved_by', 'fk_hr_req_approved')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('created_by', 'fk_hr_req_created')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('updated_by', 'fk_hr_req_updated')->references('id')->on('institute_users')->nullOnDelete();
        });

        // Vacancies (approved requisition → publishable)
        Schema::create('hr_vacancies', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('requisition_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('designation_id')->nullable();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('openings')->default(1);
            $table->enum('employment_type', ['full_time','part_time','contractual','permanent','temporary','intern','probation'])->nullable();
            $table->decimal('salary_min', 12, 2)->nullable();
            $table->decimal('salary_max', 12, 2)->nullable();
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->enum('status', ['draft','pending_approval','approved','published','closed','cancelled'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('institute_id', 'idx_hr_vac_inst');
            $table->index('branch_id', 'idx_hr_vac_branch');
            $table->index('requisition_id', 'idx_hr_vac_req');
            $table->index(['institute_id','status'], 'idx_hr_vac_status');

            $table->foreign('institute_id', 'fk_hr_vac_inst')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id', 'fk_hr_vac_branch')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('requisition_id', 'fk_hr_vac_req')->references('id')->on('hr_requisitions')->nullOnDelete();
            $table->foreign('department_id', 'fk_hr_vac_dept')->references('id')->on('hr_departments')->nullOnDelete();
            $table->foreign('designation_id', 'fk_hr_vac_desig')->references('id')->on('hr_designations')->nullOnDelete();
            $table->foreign('currency_id', 'fk_hr_vac_currency')->references('id')->on('currencies')->nullOnDelete();
            $table->foreign('created_by', 'fk_hr_vac_created')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('updated_by', 'fk_hr_vac_updated')->references('id')->on('institute_users')->nullOnDelete();
        });

        // Applications (candidate = crm_leads, hired → crm_contacts + hr_employees)
        Schema::create('hr_applications', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('vacancy_id')->nullable();
            $table->unsignedBigInteger('candidate_lead_id');
            $table->unsignedBigInteger('candidate_contact_id')->nullable();
            $table->unsignedBigInteger('hired_employee_id')->nullable();
            $table->enum('current_stage', ['new','screening','shortlisted','interview','assessment','selected','rejected','hired','withdrawn'])->default('new');
            $table->unsignedBigInteger('assigned_recruiter_id')->nullable();
            $table->date('application_date');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['institute_id','vacancy_id','candidate_lead_id'], 'uq_hr_app_vac_lead');
            $table->index('institute_id', 'idx_hr_app_inst');
            $table->index('vacancy_id', 'idx_hr_app_vac');
            $table->index('candidate_lead_id', 'idx_hr_app_lead');
            $table->index('current_stage', 'idx_hr_app_stage');
            $table->index('assigned_recruiter_id', 'idx_hr_app_recruiter');

            $table->foreign('institute_id', 'fk_hr_app_inst')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id', 'fk_hr_app_branch')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('vacancy_id', 'fk_hr_app_vac')->references('id')->on('hr_vacancies')->nullOnDelete();
            $table->foreign('candidate_lead_id', 'fk_hr_app_lead')->references('id')->on('crm_leads')->cascadeOnDelete();
            $table->foreign('candidate_contact_id', 'fk_hr_app_contact')->references('id')->on('crm_contacts')->nullOnDelete();
            $table->foreign('hired_employee_id', 'fk_hr_app_employee')->references('id')->on('hr_employees')->nullOnDelete();
            $table->foreign('assigned_recruiter_id', 'fk_hr_app_recruiter')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('source_id', 'fk_hr_app_source')->references('id')->on('crm_lead_sources')->nullOnDelete();
            $table->foreign('created_by', 'fk_hr_app_created')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('updated_by', 'fk_hr_app_updated')->references('id')->on('institute_users')->nullOnDelete();
        });

        // Stage history (immutable audit)
        Schema::create('hr_application_histories', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('application_id');
            $table->enum('from_stage', ['new','screening','shortlisted','interview','assessment','selected','rejected','hired','withdrawn'])->nullable();
            $table->enum('to_stage', ['new','screening','shortlisted','interview','assessment','selected','rejected','hired','withdrawn']);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamps();

            $table->index('institute_id', 'idx_hr_ah_inst');
            $table->index('application_id', 'idx_hr_ah_app');

            $table->foreign('institute_id', 'fk_hr_ah_inst')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('application_id', 'fk_hr_ah_app')->references('id')->on('hr_applications')->cascadeOnDelete();
            $table->foreign('changed_by', 'fk_hr_ah_changed')->references('id')->on('institute_users')->nullOnDelete();
        });

        // Interviews
        Schema::create('hr_interviews', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('application_id');
            $table->unsignedBigInteger('vacancy_id')->nullable();
            $table->unsignedBigInteger('candidate_lead_id');
            $table->unsignedBigInteger('interviewer_id')->nullable();
            $table->enum('interview_type', ['onsite','online','phone','panel'])->default('onsite');
            $table->dateTime('scheduled_at');
            $table->string('location', 255)->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->enum('recommendation', ['hire','reject','hold','pending'])->default('pending');
            $table->enum('status', ['scheduled','completed','cancelled','no_show'])->default('scheduled');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('institute_id', 'idx_hr_int_inst');
            $table->index('application_id', 'idx_hr_int_app');
            $table->index('interviewer_id', 'idx_hr_int_interviewer');
            $table->index('status', 'idx_hr_int_status');

            $table->foreign('institute_id', 'fk_hr_int_inst')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id', 'fk_hr_int_branch')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('application_id', 'fk_hr_int_app')->references('id')->on('hr_applications')->cascadeOnDelete();
            $table->foreign('vacancy_id', 'fk_hr_int_vac')->references('id')->on('hr_vacancies')->nullOnDelete();
            $table->foreign('candidate_lead_id', 'fk_hr_int_lead')->references('id')->on('crm_leads')->cascadeOnDelete();
            $table->foreign('interviewer_id', 'fk_hr_int_interviewer')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('created_by', 'fk_hr_int_created')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('updated_by', 'fk_hr_int_updated')->references('id')->on('institute_users')->nullOnDelete();
        });

        // Offers
        Schema::create('hr_offers', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('application_id');
            $table->unsignedBigInteger('candidate_lead_id');
            $table->unsignedBigInteger('proposed_designation_id')->nullable();
            $table->unsignedBigInteger('proposed_department_id')->nullable();
            $table->unsignedBigInteger('proposed_branch_id')->nullable();
            $table->string('salary_reference', 100)->nullable();
            $table->decimal('offered_salary', 12, 2)->nullable();
            $table->date('joining_date')->nullable();
            $table->date('offer_date');
            $table->date('expiry_date')->nullable();
            $table->enum('status', ['draft','sent','accepted','rejected','withdrawn','expired'])->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique('application_id', 'uq_hr_offers_app');
            $table->index('institute_id', 'idx_hr_offers_inst');
            $table->index('candidate_lead_id', 'idx_hr_offers_lead');
            $table->index('status', 'idx_hr_offers_status');

            $table->foreign('institute_id', 'fk_hr_offers_inst')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id', 'fk_hr_offers_branch')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('application_id', 'fk_hr_offers_app')->references('id')->on('hr_applications')->cascadeOnDelete();
            $table->foreign('candidate_lead_id', 'fk_hr_offers_lead')->references('id')->on('crm_leads')->cascadeOnDelete();
            $table->foreign('proposed_designation_id', 'fk_hr_offers_desig')->references('id')->on('hr_designations')->nullOnDelete();
            $table->foreign('proposed_department_id', 'fk_hr_offers_dept')->references('id')->on('hr_departments')->nullOnDelete();
            $table->foreign('proposed_branch_id', 'fk_hr_offers_proposed_branch')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('created_by', 'fk_hr_offers_created')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('updated_by', 'fk_hr_offers_updated')->references('id')->on('institute_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_offers');
        Schema::dropIfExists('hr_interviews');
        Schema::dropIfExists('hr_application_histories');
        Schema::dropIfExists('hr_applications');
        Schema::dropIfExists('hr_vacancies');
        Schema::dropIfExists('hr_requisitions');
    }
};
