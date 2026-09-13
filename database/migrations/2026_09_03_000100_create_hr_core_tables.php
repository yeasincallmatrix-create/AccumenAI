<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HR-1 — Employee Core & Organization.
 *
 * Industry-neutral foundational HR tables:
 *  - hr_departments: per-institute (+ optional branch scope), hierarchical, ordered, active/inactive, soft-deletable.
 *  - hr_designations: per-institute, optional department link, ordered, active/inactive, soft-deletable.
 *  - hr_employees: tenant + branch scoped master profile (code, names, contacts, IDs, joining date, status/type, department/designation/manager), soft-deletable.
 *  - hr_employee_code_sequences: per-institute atomic counter for tenant-safe employee codes (EMP-{inst padded}-{seq padded}).
 *
 * No recruitment/payroll/attendance/leave/performance/training/AI in this step.
 * Existing Institute/InstituteUser/Branch/TeacherProfile remain untouched; employees may optionally link to an institute_users row via institute_user_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Departments
        Schema::create('hr_departments', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('parent_department_id')->nullable();
            $table->string('name', 120);
            $table->string('code', 40)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('institute_id', 'idx_hr_departments_institute');
            $table->index('branch_id', 'idx_hr_departments_branch');
            $table->index('parent_department_id', 'idx_hr_departments_parent');
            $table->index(['institute_id', 'is_active'], 'idx_hr_departments_active');

            $table->foreign('institute_id', 'fk_hr_departments_institute')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id', 'fk_hr_departments_branch')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('parent_department_id', 'fk_hr_departments_parent')->references('id')->on('hr_departments')->nullOnDelete();
            $table->foreign('created_by', 'fk_hr_departments_created_by')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('updated_by', 'fk_hr_departments_updated_by')->references('id')->on('institute_users')->nullOnDelete();
        });

        // Designations
        Schema::create('hr_designations', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('name', 120);
            $table->string('code', 40)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('institute_id', 'idx_hr_designations_institute');
            $table->index('department_id', 'idx_hr_designations_department');
            $table->index(['institute_id', 'is_active'], 'idx_hr_designations_active');

            $table->foreign('institute_id', 'fk_hr_designations_institute')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('department_id', 'fk_hr_designations_department')->references('id')->on('hr_departments')->nullOnDelete();
            $table->foreign('created_by', 'fk_hr_designations_created_by')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('updated_by', 'fk_hr_designations_updated_by')->references('id')->on('institute_users')->nullOnDelete();
        });

        // Employee code sequence (per-institute atomic counter)
        Schema::create('hr_employee_code_sequences', function (Blueprint $table) {
            $table->unsignedBigInteger('institute_id')->primary();
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();

            $table->foreign('institute_id', 'fk_hr_employee_seq_institute')->references('id')->on('institutes')->cascadeOnDelete();
        });

        // Employees
        Schema::create('hr_employees', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('designation_id')->nullable();
            $table->unsignedBigInteger('reporting_manager_id')->nullable();
            $table->unsignedBigInteger('institute_user_id')->nullable();
            $table->string('employee_code', 40);
            $table->string('first_name', 60);
            $table->string('middle_name', 60)->nullable();
            $table->string('last_name', 60);
            $table->string('display_name', 180);
            $table->string('profile_photo', 255)->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact_name', 120)->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('national_id', 60)->nullable();
            $table->string('passport_no', 60)->nullable();
            $table->date('joining_date')->nullable();
            $table->enum('employment_status', ['active', 'inactive', 'suspended', 'resigned', 'terminated'])->default('active');
            $table->enum('employment_type', ['full_time', 'part_time', 'contractual', 'permanent', 'temporary', 'intern', 'probation'])->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['institute_id', 'employee_code'], 'uq_hr_employees_institute_code');
            $table->index('institute_id', 'idx_hr_employees_institute');
            $table->index('branch_id', 'idx_hr_employees_branch');
            $table->index('department_id', 'idx_hr_employees_department');
            $table->index('designation_id', 'idx_hr_employees_designation');
            $table->index('reporting_manager_id', 'idx_hr_employees_manager');
            $table->index('employment_status', 'idx_hr_employees_status');
            $table->index('employment_type', 'idx_hr_employees_type');
            $table->index(['institute_id', 'employment_status'], 'idx_hr_employees_institute_status');

            $table->foreign('institute_id', 'fk_hr_employees_institute')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id', 'fk_hr_employees_branch')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('department_id', 'fk_hr_employees_department')->references('id')->on('hr_departments')->nullOnDelete();
            $table->foreign('designation_id', 'fk_hr_employees_designation')->references('id')->on('hr_designations')->nullOnDelete();
            $table->foreign('reporting_manager_id', 'fk_hr_employees_manager')->references('id')->on('hr_employees')->nullOnDelete();
            $table->foreign('institute_user_id', 'fk_hr_employees_user')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('created_by', 'fk_hr_employees_created_by')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('updated_by', 'fk_hr_employees_updated_by')->references('id')->on('institute_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employees');
        Schema::dropIfExists('hr_employee_code_sequences');
        Schema::dropIfExists('hr_designations');
        Schema::dropIfExists('hr_departments');
    }
};
