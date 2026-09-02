<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_performance_periods', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('name', 150);
            $table->string('code', 40)->nullable();
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['draft', 'active', 'closed'])->default('active');
            $table->unsignedInteger('display_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['institute_id', 'status'], 'idx_hr_perf_periods_inst_status');
            $table->index('branch_id', 'idx_hr_perf_periods_branch');
            $table->foreign('institute_id', 'fk_hr_perf_periods_institute')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id', 'fk_hr_perf_periods_branch')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('created_by', 'fk_hr_perf_periods_created')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('updated_by', 'fk_hr_perf_periods_updated')->references('id')->on('institute_users')->nullOnDelete();
        });

        Schema::create('hr_kpis', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('target', 150)->nullable();
            $table->string('measurement', 100)->nullable();
            $table->decimal('weight', 5, 2)->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['institute_id', 'is_active'], 'idx_hr_kpis_inst_active');
            $table->foreign('institute_id', 'fk_hr_kpis_institute')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id', 'fk_hr_kpis_branch')->references('id')->on('branches')->nullOnDelete();
        });

        Schema::create('hr_performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('reviewer_id')->nullable();
            $table->unsignedBigInteger('period_id');
            $table->date('review_date');
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->decimal('self_score', 5, 2)->nullable();
            $table->decimal('manager_score', 5, 2)->nullable();
            $table->decimal('hr_score', 5, 2)->nullable();
            $table->enum('status', ['draft', 'pending', 'submitted', 'manager_review', 'hr_review', 'approved', 'rejected'])->default('draft');
            $table->text('comments')->nullable();
            $table->text('self_comments')->nullable();
            $table->text('manager_comments')->nullable();
            $table->text('hr_comments')->nullable();
            $table->string('promotion_recommendation', 50)->nullable();
            $table->string('training_recommendation', 500)->nullable();
            $table->text('improvement_plan')->nullable();
            $table->string('recognition', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'period_id'], 'uq_hr_reviews_employee_period');
            $table->index(['institute_id', 'status'], 'idx_hr_reviews_inst_status');
            $table->index('employee_id', 'idx_hr_reviews_employee');
            $table->index('reviewer_id', 'idx_hr_reviews_reviewer');
            $table->foreign('institute_id', 'fk_hr_reviews_institute')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id', 'fk_hr_reviews_branch')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('employee_id', 'fk_hr_reviews_employee')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->foreign('reviewer_id', 'fk_hr_reviews_reviewer')->references('id')->on('hr_employees')->nullOnDelete();
            $table->foreign('period_id', 'fk_hr_reviews_period')->references('id')->on('hr_performance_periods')->cascadeOnDelete();
            $table->foreign('created_by', 'fk_hr_reviews_created')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('updated_by', 'fk_hr_reviews_updated')->references('id')->on('institute_users')->nullOnDelete();
        });

        Schema::create('hr_performance_review_kpis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('review_id');
            $table->unsignedBigInteger('kpi_id')->nullable();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('target', 150)->nullable();
            $table->string('measurement', 100)->nullable();
            $table->decimal('weight', 5, 2)->default(1);
            $table->decimal('score', 5, 2)->nullable();
            $table->decimal('max_score', 5, 2)->default(100);
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->index('review_id', 'idx_hr_review_kpis_review');
            $table->foreign('review_id', 'fk_hr_review_kpis_review')->references('id')->on('hr_performance_reviews')->cascadeOnDelete();
            $table->foreign('kpi_id', 'fk_hr_review_kpis_kpi')->references('id')->on('hr_kpis')->nullOnDelete();
        });

        Schema::create('hr_trainings', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('provider', 150)->nullable();
            $table->string('trainer', 150)->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('location', 200)->nullable();
            $table->boolean('is_online')->default(false);
            $table->unsignedInteger('capacity')->nullable();
            $table->decimal('cost', 12, 2)->default(0);
            $table->enum('status', ['draft', 'planned', 'ongoing', 'completed', 'cancelled'])->default('planned');
            $table->unsignedInteger('enrolled_count')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['institute_id', 'status'], 'idx_hr_trainings_inst_status');
            $table->index('branch_id', 'idx_hr_trainings_branch');
            $table->foreign('institute_id', 'fk_hr_trainings_institute')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id', 'fk_hr_trainings_branch')->references('id')->on('branches')->nullOnDelete();
        });

        Schema::create('hr_training_enrollments', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('training_id');
            $table->unsignedBigInteger('employee_id');
            $table->enum('status', ['enrolled', 'attending', 'completed', 'dropped', 'cancelled'])->default('enrolled');
            $table->enum('attendance_status', ['present', 'absent', 'partial'])->nullable();
            $table->date('enrollment_date')->nullable();
            $table->date('completion_date')->nullable();
            $table->enum('result', ['pass', 'fail', 'pending'])->default('pending');
            $table->decimal('score', 5, 2)->nullable();
            $table->string('certificate_path', 255)->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['training_id', 'employee_id'], 'uq_hr_enrollments_training_employee');
            $table->index(['institute_id', 'status'], 'idx_hr_enrollments_inst_status');
            $table->index('employee_id', 'idx_hr_enrollments_employee');
            $table->foreign('institute_id', 'fk_hr_enroll_institute')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('training_id', 'fk_hr_enroll_training')->references('id')->on('hr_trainings')->cascadeOnDelete();
            $table->foreign('employee_id', 'fk_hr_enroll_employee')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->foreign('document_id', 'fk_hr_enroll_document')->references('id')->on('documents')->nullOnDelete();
        });

        Schema::create('hr_employee_skills', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('skill_name', 150);
            $table->text('description')->nullable();
            $table->enum('proficiency_level', ['beginner', 'intermediate', 'advanced', 'expert'])->default('beginner');
            $table->date('acquired_date')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['institute_id', 'employee_id'], 'idx_hr_skills_inst_emp');
            $table->index('skill_name', 'idx_hr_skills_name');
            $table->foreign('institute_id', 'fk_hr_skills_institute')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('employee_id', 'fk_hr_skills_employee')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->foreign('verified_by', 'fk_hr_skills_verified')->references('id')->on('institute_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employee_skills');
        Schema::dropIfExists('hr_training_enrollments');
        Schema::dropIfExists('hr_trainings');
        Schema::dropIfExists('hr_performance_review_kpis');
        Schema::dropIfExists('hr_performance_reviews');
        Schema::dropIfExists('hr_kpis');
        Schema::dropIfExists('hr_performance_periods');
    }
};
