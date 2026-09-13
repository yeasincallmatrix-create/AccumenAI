<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HR-5 — Payroll Core (industry-neutral).
 *
 * - hr_salary_structures: reusable pay templates (basic + allowances + deductions + tax)
 * - hr_salary_structure_components: configurable earning/deduction line items
 * - hr_employee_salary_assignments: employee → structure linkage with effective date + revision history
 * - hr_payroll_no_sequences: per-institute atomic counter for payslip numbers (PSL-{inst}-{seq})
 * - hr_payroll_periods: payroll run header (monthly/fortnightly etc, status lifecycle)
 * - hr_payrolls: per-employee payslip (snapshot, calc, journal links, protected from silent mutation)
 * - hr_payroll_items: earnings/deductions breakdown per payslip
 * - hr_payroll_adjustments: manual bonus/deduction/allowance/correction with audit
 *
 * Finance integration reuses Step 32 JournalPostingService (no second ledger).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Salary structures
        Schema::create('hr_salary_structures', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('name', 120);
            $table->string('code', 40);
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->enum('pay_frequency', ['monthly', 'weekly', 'biweekly', 'fortnightly'])->default('monthly');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            // Core earnings
            $table->decimal('basic_salary', 12, 2)->default(0);
            $table->decimal('housing_allowance', 12, 2)->default(0);
            $table->decimal('medical_allowance', 12, 2)->default(0);
            $table->decimal('transport_allowance', 12, 2)->default(0);
            $table->decimal('other_allowance', 12, 2)->default(0);
            $table->decimal('overtime_rate', 12, 2)->default(0); // per hour
            $table->decimal('bonus_amount', 12, 2)->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0);
            // Deductions
            $table->decimal('deduction_amount', 12, 2)->default(0);
            $table->decimal('tax_deduction', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['institute_id', 'code'], 'uq_hr_salary_structures_inst_code');
            $table->index('institute_id', 'idx_hr_salary_structures_inst');
            $table->index('branch_id', 'idx_hr_salary_structures_branch');
            $table->index('department_id', 'idx_hr_salary_structures_dept');
            $table->index('currency_id', 'idx_hr_salary_structures_currency');
            $table->index(['institute_id', 'is_active'], 'idx_hr_salary_structures_active');

            $table->foreign('institute_id', 'fk_hr_salary_structures_inst')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id', 'fk_hr_salary_structures_branch')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('department_id', 'fk_hr_salary_structures_dept')->references('id')->on('hr_departments')->nullOnDelete();
            $table->foreign('currency_id', 'fk_hr_salary_structures_currency')->references('id')->on('currencies')->nullOnDelete();
            $table->foreign('created_by', 'fk_hr_salary_structures_created')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('updated_by', 'fk_hr_salary_structures_updated')->references('id')->on('institute_users')->nullOnDelete();
        });

        // Configurable components per structure (earning/deduction/tax/statutory)
        Schema::create('hr_salary_structure_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('salary_structure_id');
            $table->string('name', 120);
            $table->string('code', 40);
            $table->enum('component_type', ['earning', 'deduction', 'tax', 'statutory'])->default('earning');
            $table->enum('amount_type', ['fixed', 'percent'])->default('fixed');
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('percent_base', 5, 2)->nullable(); // if percent, base %
            $table->boolean('is_taxable')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('institute_id', 'idx_hr_ssc_inst');
            $table->index('salary_structure_id', 'idx_hr_ssc_structure');
            $table->unique(['salary_structure_id', 'code'], 'uq_hr_ssc_structure_code');
            $table->foreign('institute_id', 'fk_hr_ssc_inst')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('salary_structure_id', 'fk_hr_ssc_structure')->references('id')->on('hr_salary_structures')->cascadeOnDelete();
        });

        // Employee → salary assignment (effective date + revision history)
        Schema::create('hr_employee_salary_assignments', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('salary_structure_id')->nullable();
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->enum('pay_frequency', ['monthly', 'weekly', 'biweekly', 'fortnightly'])->default('monthly');
            $table->date('effective_date');
            $table->date('effective_to')->nullable();
            $table->decimal('basic_salary', 12, 2)->default(0);
            $table->decimal('housing_allowance', 12, 2)->default(0);
            $table->decimal('medical_allowance', 12, 2)->default(0);
            $table->decimal('transport_allowance', 12, 2)->default(0);
            $table->decimal('other_allowance', 12, 2)->default(0);
            $table->decimal('overtime_rate', 12, 2)->default(0);
            $table->decimal('bonus_amount', 12, 2)->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('deduction_amount', 12, 2)->default(0);
            $table->decimal('tax_deduction', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('institute_id', 'idx_hr_es_assign_inst');
            $table->index('employee_id', 'idx_hr_es_assign_employee');
            $table->index('salary_structure_id', 'idx_hr_es_assign_structure');
            $table->index('effective_date', 'idx_hr_es_assign_date');
            $table->index(['institute_id', 'employee_id', 'effective_date'], 'idx_hr_es_assign_emp_date');

            $table->foreign('institute_id', 'fk_hr_es_assign_inst')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id', 'fk_hr_es_assign_branch')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('employee_id', 'fk_hr_es_assign_emp')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->foreign('salary_structure_id', 'fk_hr_es_assign_structure')->references('id')->on('hr_salary_structures')->nullOnDelete();
            $table->foreign('currency_id', 'fk_hr_es_assign_currency')->references('id')->on('currencies')->nullOnDelete();
            $table->foreign('created_by', 'fk_hr_es_assign_created')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('updated_by', 'fk_hr_es_assign_updated')->references('id')->on('institute_users')->nullOnDelete();
        });

        // Payslip number sequence per institute (atomic)
        Schema::create('hr_payroll_no_sequences', function (Blueprint $table) {
            $table->unsignedBigInteger('institute_id')->primary();
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();
            $table->foreign('institute_id', 'fk_hr_payroll_seq_inst')->references('id')->on('institutes')->cascadeOnDelete();
        });

        // Payroll periods (run header)
        Schema::create('hr_payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('name', 120);
            $table->enum('pay_frequency', ['monthly', 'weekly', 'biweekly', 'fortnightly'])->default('monthly');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['draft', 'processing', 'approved', 'paid', 'cancelled', 'void'])->default('draft');
            $table->unsignedInteger('total_employees')->default(0);
            $table->decimal('total_gross', 19, 4)->default(0);
            $table->decimal('total_deductions', 19, 4)->default(0);
            $table->decimal('total_net', 19, 4)->default(0);
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['institute_id', 'branch_id', 'start_date', 'end_date'], 'uq_hr_payroll_periods_inst_branch_dates');
            $table->index('institute_id', 'idx_hr_pp_inst');
            $table->index('branch_id', 'idx_hr_pp_branch');
            $table->index('status', 'idx_hr_pp_status');
            $table->index(['institute_id', 'start_date', 'end_date'], 'idx_hr_pp_dates');

            $table->foreign('institute_id', 'fk_hr_pp_inst')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id', 'fk_hr_pp_branch')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('currency_id', 'fk_hr_pp_currency')->references('id')->on('currencies')->nullOnDelete();
            $table->foreign('generated_by', 'fk_hr_pp_generated')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('approved_by', 'fk_hr_pp_approved')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('paid_by', 'fk_hr_pp_paid')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('cancelled_by', 'fk_hr_pp_cancelled')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('created_by', 'fk_hr_pp_created')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('updated_by', 'fk_hr_pp_updated')->references('id')->on('institute_users')->nullOnDelete();
        });

        // Payslips (per-employee, per-period)
        Schema::create('hr_payrolls', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('payroll_period_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('salary_assignment_id')->nullable();
            $table->string('payslip_no', 40);
            $table->enum('status', ['draft', 'approved', 'paid', 'cancelled', 'void'])->default('draft');
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->unsignedSmallInteger('working_days')->default(0);
            $table->decimal('present_days', 5, 1)->default(0);
            $table->decimal('leave_days', 5, 1)->default(0);
            $table->decimal('unpaid_leave_days', 5, 1)->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->decimal('overtime_amount', 19, 4)->default(0);
            $table->decimal('gross_earnings', 19, 4)->default(0);
            $table->decimal('total_deductions', 19, 4)->default(0);
            $table->decimal('net_salary', 19, 4)->default(0);
            $table->json('earnings_snapshot')->nullable(); // detailed breakdown
            $table->json('deductions_snapshot')->nullable();
            $table->json('calculation_snapshot')->nullable(); // full historical snapshot (salary + attendance + leave at generation time)
            $table->unsignedBigInteger('journal_id')->nullable();
            $table->unsignedBigInteger('payment_journal_id')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['institute_id', 'payslip_no'], 'uq_hr_payrolls_inst_no');
            $table->unique(['institute_id', 'payroll_period_id', 'employee_id'], 'uq_hr_payrolls_period_employee');
            $table->index('institute_id', 'idx_hr_payrolls_inst');
            $table->index('payroll_period_id', 'idx_hr_payrolls_period');
            $table->index('employee_id', 'idx_hr_payrolls_employee');
            $table->index('status', 'idx_hr_payrolls_status');
            $table->index(['institute_id', 'employee_id', 'status'], 'idx_hr_payrolls_emp_status');
            $table->index(['institute_id', 'branch_id'], 'idx_hr_payrolls_branch');

            $table->foreign('institute_id', 'fk_hr_payrolls_inst')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id', 'fk_hr_payrolls_branch')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('payroll_period_id', 'fk_hr_payrolls_period')->references('id')->on('hr_payroll_periods')->cascadeOnDelete();
            $table->foreign('employee_id', 'fk_hr_payrolls_employee')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->foreign('salary_assignment_id', 'fk_hr_payrolls_assignment')->references('id')->on('hr_employee_salary_assignments')->nullOnDelete();
            $table->foreign('currency_id', 'fk_hr_payrolls_currency')->references('id')->on('currencies')->nullOnDelete();
            $table->foreign('journal_id', 'fk_hr_payrolls_journal')->references('id')->on('journals')->nullOnDelete();
            $table->foreign('payment_journal_id', 'fk_hr_payrolls_pay_journal')->references('id')->on('journals')->nullOnDelete();
            $table->foreign('created_by', 'fk_hr_payrolls_created')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('updated_by', 'fk_hr_payrolls_updated')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('approved_by', 'fk_hr_payrolls_approved')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('paid_by', 'fk_hr_payrolls_paid')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('cancelled_by', 'fk_hr_payrolls_cancelled')->references('id')->on('institute_users')->nullOnDelete();
        });

        // Detailed breakdown items (earning/deduction per payslip)
        Schema::create('hr_payroll_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('payroll_id');
            $table->enum('item_type', ['earning', 'deduction']);
            $table->string('name', 120);
            $table->string('code', 40)->nullable();
            $table->decimal('amount', 19, 4)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('institute_id', 'idx_hr_pi_inst');
            $table->index('payroll_id', 'idx_hr_pi_payroll');
            $table->index('item_type', 'idx_hr_pi_type');

            $table->foreign('institute_id', 'fk_hr_pi_inst')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('payroll_id', 'fk_hr_pi_payroll')->references('id')->on('hr_payrolls')->cascadeOnDelete();
        });

        // Manual adjustments (bonus/deduction/allowance/correction) — audited
        Schema::create('hr_payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->default(DB::raw('uuid()'));
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('payroll_id')->nullable();
            $table->unsignedBigInteger('payroll_period_id')->nullable();
            $table->unsignedBigInteger('employee_id');
            $table->enum('adjustment_type', ['bonus', 'deduction', 'allowance', 'correction', 'overtime', 'commission', 'tax']);
            $table->decimal('amount', 19, 4);
            $table->string('reason', 500);
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('approved');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('institute_id', 'idx_hr_pa_inst');
            $table->index('payroll_id', 'idx_hr_pa_payroll');
            $table->index('employee_id', 'idx_hr_pa_employee');
            $table->index('adjustment_type', 'idx_hr_pa_type');
            $table->index(['institute_id', 'payroll_period_id'], 'idx_hr_pa_period');

            $table->foreign('institute_id', 'fk_hr_pa_inst')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id', 'fk_hr_pa_branch')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('payroll_id', 'fk_hr_pa_payroll')->references('id')->on('hr_payrolls')->nullOnDelete();
            $table->foreign('payroll_period_id', 'fk_hr_pa_period')->references('id')->on('hr_payroll_periods')->nullOnDelete();
            $table->foreign('employee_id', 'fk_hr_pa_employee')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->foreign('created_by', 'fk_hr_pa_created')->references('id')->on('institute_users')->nullOnDelete();
            $table->foreign('approved_by', 'fk_hr_pa_approved')->references('id')->on('institute_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_payroll_adjustments');
        Schema::dropIfExists('hr_payroll_items');
        Schema::dropIfExists('hr_payrolls');
        Schema::dropIfExists('hr_payroll_periods');
        Schema::dropIfExists('hr_payroll_no_sequences');
        Schema::dropIfExists('hr_employee_salary_assignments');
        Schema::dropIfExists('hr_salary_structure_components');
        Schema::dropIfExists('hr_salary_structures');
    }
};
