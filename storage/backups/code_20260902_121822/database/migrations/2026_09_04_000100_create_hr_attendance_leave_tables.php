<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HR-4 — Attendance & Leave Management (industry-neutral).
 *
 * Separate from Education `attendance` (student academic). Employee attendance uses `hr_attendances`.
 * Supports present/absent/late/early_departure/leave/holiday/weekend/half_day, check-in/out, working/late/overtime,
 * source (manual/system/api/import), shift, corrections, leave types/policies/balances/applications, holidays.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_work_shifts', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('name', 120);
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('grace_minutes')->default(0);
            $table->json('working_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('institute_id', 'idx_hr_shifts_institute');
            $table->index('branch_id', 'idx_hr_shifts_branch');
            $table->index('employee_id', 'idx_hr_shifts_employee');
            $table->foreign('institute_id', 'fk_hr_shifts_institute')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id', 'fk_hr_shifts_branch')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('employee_id', 'fk_hr_shifts_employee')->references('id')->on('hr_employees')->nullOnDelete();
            $table->foreign('created_by', 'fk_hr_shifts_created')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('updated_by', 'fk_hr_shifts_updated')->references('id')->on('institute_users')->nullOnDelete();
        });

        Schema::create('hr_holidays', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('name', 150);
            $table->date('holiday_date');
            $table->boolean('is_recurring')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('institute_id', 'idx_hr_holidays_institute');
            $table->index('holiday_date', 'idx_hr_holidays_date');
            $table->unique(['institute_id', 'branch_id', 'holiday_date'], 'uq_hr_holidays_inst_branch_date');
            $table->foreign('institute_id', 'fk_hr_holidays_institute')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id', 'fk_hr_holidays_branch')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('created_by', 'fk_hr_holidays_created')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('updated_by', 'fk_hr_holidays_updated')->references('id')->on('institute_users')->nullOnDelete();
        });

        Schema::create('hr_leave_types', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->string('name', 100);
            $table->string('code', 40);
            $table->unsignedSmallInteger('yearly_allowance')->default(0);
            $table->boolean('carry_forward')->default(false);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('institute_id', 'idx_hr_leave_types_institute');
            $table->unique(['institute_id', 'code'], 'uq_hr_leave_types_inst_code');
            $table->foreign('institute_id', 'fk_hr_leave_types_institute')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('created_by', 'fk_hr_leave_types_created')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('updated_by', 'fk_hr_leave_types_updated')->references('id')->on('institute_users')->nullOnDelete();
        });

        Schema::create('hr_leave_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->unsignedSmallInteger('year');
            $table->decimal('allocated', 5, 1)->default(0);
            $table->decimal('carry_forward', 5, 1)->default(0);
            $table->decimal('used', 5, 1)->default(0);
            $table->decimal('pending', 5, 1)->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'year'], 'uq_hr_balances_emp_type_year');
            $table->index('institute_id', 'idx_hr_balances_institute');
            $table->foreign('institute_id', 'fk_hr_balances_institute')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('employee_id', 'fk_hr_balances_employee')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->foreign('leave_type_id', 'fk_hr_balances_type')->references('id')->on('hr_leave_types')->cascadeOnDelete();
        });

        Schema::create('hr_leave_applications', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days_count', 5, 1);
            $table->text('reason')->nullable();
            $table->string('attachment', 255)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->unsignedBigInteger('applied_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index('institute_id', 'idx_hr_leave_apps_institute');
            $table->index('employee_id', 'idx_hr_leave_apps_employee');
            $table->index('leave_type_id', 'idx_hr_leave_apps_type');
            $table->index('status', 'idx_hr_leave_apps_status');
            $table->index(['employee_id', 'start_date', 'end_date'], 'idx_hr_leave_apps_dates');
            $table->foreign('institute_id', 'fk_hr_leave_apps_institute')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id', 'fk_hr_leave_apps_branch')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('employee_id', 'fk_hr_leave_apps_employee')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->foreign('leave_type_id', 'fk_hr_leave_apps_type')->references('id')->on('hr_leave_types')->nullOnDelete();
            $table->foreign('applied_by', 'fk_hr_leave_apps_applied')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('approved_by', 'fk_hr_leave_apps_approved')->references('id')->on('institute_users')->nullOnDelete();
        });

        Schema::create('hr_attendances', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->date('attendance_date');
            $table->enum('status', ['present', 'absent', 'late', 'early_departure', 'leave', 'holiday', 'weekend', 'half_day'])->default('present');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->unsignedSmallInteger('working_minutes')->nullable();
            $table->unsignedSmallInteger('late_minutes')->nullable();
            $table->unsignedSmallInteger('overtime_minutes')->nullable();
            $table->enum('source', ['manual', 'system', 'api', 'import'])->default('manual');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['institute_id', 'employee_id', 'attendance_date'], 'uq_hr_attendances_inst_emp_date');
            $table->index('institute_id', 'idx_hr_attendances_institute');
            $table->index('employee_id', 'idx_hr_attendances_employee');
            $table->index('attendance_date', 'idx_hr_attendances_date');
            $table->index('status', 'idx_hr_attendances_status');
            $table->foreign('institute_id', 'fk_hr_att_institute')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id', 'fk_hr_att_branch')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('employee_id', 'fk_hr_att_employee')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->foreign('shift_id', 'fk_hr_att_shift')->references('id')->on('hr_work_shifts')->nullOnDelete();
            $table->foreign('created_by', 'fk_hr_att_created')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('updated_by', 'fk_hr_att_updated')->references('id')->on('institute_users')->nullOnDelete();
        });

        Schema::create('hr_attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('attendance_id')->nullable();
            $table->unsignedBigInteger('employee_id');
            $table->date('correction_date');
            $table->enum('requested_status', ['present', 'absent', 'late', 'early_departure', 'leave', 'holiday', 'weekend', 'half_day']);
            $table->time('requested_check_in')->nullable();
            $table->time('requested_check_out')->nullable();
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index('institute_id', 'idx_hr_corr_institute');
            $table->index('employee_id', 'idx_hr_corr_employee');
            $table->index('status', 'idx_hr_corr_status');
            $table->foreign('institute_id', 'fk_hr_corr_institute')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('attendance_id', 'fk_hr_corr_attendance')->references('id')->on('hr_attendances')->nullOnDelete();
            $table->foreign('employee_id', 'fk_hr_corr_employee')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->foreign('requested_by', 'fk_hr_corr_requested')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('reviewed_by', 'fk_hr_corr_reviewed')->references('id')->on('institute_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_attendance_corrections');
        Schema::dropIfExists('hr_attendances');
        Schema::dropIfExists('hr_leave_applications');
        Schema::dropIfExists('hr_leave_balances');
        Schema::dropIfExists('hr_leave_types');
        Schema::dropIfExists('hr_holidays');
        Schema::dropIfExists('hr_work_shifts');
    }
};
