<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HR-2 — Employment Lifecycle.
 *
 * Two new immutable/history tables:
 *  - hr_employment_histories: every employment change (joining, transfers, promotion, salary reference, resignation, termination, reactivation, type/status changes) with effective_date, previous/new values, reason, approval_status, changed_by.
 *  - hr_employment_periods: continuous employment periods (start_date .. end_date) for reporting current/previous periods and total service duration; never deleted, only closed/opened.
 *
 * Historical safety: histories are never updated/deleted; periods are only closed (end_date set) and a new period opened on rejoin. No overwrite of past events.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_employment_histories', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('employee_id');
            $table->enum('event_type', [
                'joining',
                'branch_transfer',
                'department_transfer',
                'designation_change',
                'manager_change',
                'employment_type_change',
                'employment_status_change',
                'salary_reference',
                'promotion',
                'demotion',
                'resignation',
                'resignation_approved',
                'resignation_rejected',
                'termination',
                'reactivation',
                'rejoin',
            ]);
            $table->date('effective_date');
            $table->unsignedBigInteger('previous_branch_id')->nullable();
            $table->unsignedBigInteger('new_branch_id')->nullable();
            $table->unsignedBigInteger('previous_department_id')->nullable();
            $table->unsignedBigInteger('new_department_id')->nullable();
            $table->unsignedBigInteger('previous_designation_id')->nullable();
            $table->unsignedBigInteger('new_designation_id')->nullable();
            $table->unsignedBigInteger('previous_manager_id')->nullable();
            $table->unsignedBigInteger('new_manager_id')->nullable();
            $table->string('previous_employment_type', 30)->nullable();
            $table->string('new_employment_type', 30)->nullable();
            $table->string('previous_employment_status', 30)->nullable();
            $table->string('new_employment_status', 30)->nullable();
            $table->string('previous_salary_reference', 100)->nullable();
            $table->string('new_salary_reference', 100)->nullable();
            $table->string('title', 150)->nullable();
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->enum('approval_status', ['pending', 'approved', 'rejected', 'cancelled'])->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamps();

            $table->index('institute_id', 'idx_hr_histories_institute');
            $table->index('employee_id', 'idx_hr_histories_employee');
            $table->index('event_type', 'idx_hr_histories_event');
            $table->index('effective_date', 'idx_hr_histories_effective');
            $table->index(['institute_id', 'employee_id'], 'idx_hr_histories_inst_emp');

            $table->foreign('institute_id', 'fk_hr_histories_institute')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('employee_id', 'fk_hr_histories_employee')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->foreign('previous_branch_id', 'fk_hr_hist_prev_branch')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('new_branch_id', 'fk_hr_hist_new_branch')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('previous_department_id', 'fk_hr_hist_prev_dept')->references('id')->on('hr_departments')->nullOnDelete();
            $table->foreign('new_department_id', 'fk_hr_hist_new_dept')->references('id')->on('hr_departments')->nullOnDelete();
            $table->foreign('previous_designation_id', 'fk_hr_hist_prev_desig')->references('id')->on('hr_designations')->nullOnDelete();
            $table->foreign('new_designation_id', 'fk_hr_hist_new_desig')->references('id')->on('hr_designations')->nullOnDelete();
            $table->foreign('previous_manager_id', 'fk_hr_hist_prev_mgr')->references('id')->on('hr_employees')->nullOnDelete();
            $table->foreign('new_manager_id', 'fk_hr_hist_new_mgr')->references('id')->on('hr_employees')->nullOnDelete();
            $table->foreign('changed_by', 'fk_hr_hist_changed_by')->references('id')->on('institute_users')->nullOnDelete();
        });

        Schema::create('hr_employment_periods', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('employee_id');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->enum('end_reason', ['resigned', 'terminated', 'inactive', 'other'])->nullable();
            $table->enum('status', ['active', 'closed'])->default('active');
            $table->unsignedBigInteger('started_by')->nullable();
            $table->unsignedBigInteger('ended_by')->nullable();
            $table->timestamps();

            $table->index('institute_id', 'idx_hr_periods_institute');
            $table->index('employee_id', 'idx_hr_periods_employee');
            $table->index('status', 'idx_hr_periods_status');
            $table->index(['institute_id', 'employee_id', 'status'], 'idx_hr_periods_inst_emp_status');

            $table->foreign('institute_id', 'fk_hr_periods_institute')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('employee_id', 'fk_hr_periods_employee')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->foreign('started_by', 'fk_hr_periods_started_by')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('ended_by', 'fk_hr_periods_ended_by')->references('id')->on('institute_users')->nullOnDelete();
        });

        // Backfill existing HR-1 employees: create missing joining history + active period (idempotent)
        $employees = DB::table('hr_employees')->whereNull('deleted_at')->get();
        foreach ($employees as $emp) {
            $hasHistory = DB::table('hr_employment_histories')->where('employee_id', $emp->id)->where('event_type', 'joining')->exists();
            if (! $hasHistory) {
                $effective = $emp->joining_date ?? $emp->created_at;
                $effectiveDate = $effective ? substr((string) $effective, 0, 10) : date('Y-m-d');
                DB::table('hr_employment_histories')->insert([
                    'uuid' => DB::raw('uuid()'),
                    'institute_id' => $emp->institute_id,
                    'employee_id' => $emp->id,
                    'event_type' => 'joining',
                    'effective_date' => $effectiveDate,
                    'new_branch_id' => $emp->branch_id,
                    'new_department_id' => $emp->department_id,
                    'new_designation_id' => $emp->designation_id,
                    'new_manager_id' => $emp->reporting_manager_id,
                    'new_employment_type' => $emp->employment_type,
                    'new_employment_status' => $emp->employment_status,
                    'changed_by' => $emp->created_by,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $hasPeriod = DB::table('hr_employment_periods')->where('employee_id', $emp->id)->exists();
            if (! $hasPeriod) {
                $start = $emp->joining_date ?? substr((string) $emp->created_at, 0, 10);
                DB::table('hr_employment_periods')->insert([
                    'uuid' => DB::raw('uuid()'),
                    'institute_id' => $emp->institute_id,
                    'employee_id' => $emp->id,
                    'start_date' => $start ? substr((string) $start, 0, 10) : date('Y-m-d'),
                    'status' => in_array($emp->employment_status, ['resigned', 'terminated'], true) ? 'closed' : 'active',
                    'end_date' => in_array($emp->employment_status, ['resigned', 'terminated'], true) ? date('Y-m-d') : null,
                    'end_reason' => $emp->employment_status === 'resigned' ? 'resigned' : ($emp->employment_status === 'terminated' ? 'terminated' : null),
                    'started_by' => $emp->created_by,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employment_periods');
        Schema::dropIfExists('hr_employment_histories');
    }
};
