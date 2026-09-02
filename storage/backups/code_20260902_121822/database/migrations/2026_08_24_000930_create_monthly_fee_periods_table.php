<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_fee_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('fee_structure_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('enrollment_id');
            $table->date('period_month');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->enum('status', ['pending', 'generated', 'paid', 'overdue'])->default('pending');
            $table->timestamps();

            $table->unique(
                ['institute_id', 'fee_structure_id', 'student_id', 'enrollment_id', 'period_month'],
                'uq_monthly_fee_period'
            );

            $table->index(['institute_id', 'status'], 'idx_mfp_inst_status');
            $table->index(['institute_id', 'period_month'], 'idx_mfp_inst_period');
            $table->index(['student_id'], 'idx_mfp_student');
            $table->index(['invoice_id'], 'idx_mfp_invoice');

            $table->foreign('institute_id', 'fk_mfp_institute')
                ->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id', 'fk_mfp_branch')
                ->references('id')->on('branches')->nullOnDelete();
            $table->foreign('fee_structure_id', 'fk_mfp_fee_structure')
                ->references('id')->on('fee_structures')->cascadeOnDelete();
            $table->foreign('student_id', 'fk_mfp_student')
                ->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('enrollment_id', 'fk_mfp_enrollment')
                ->references('id')->on('student_enrollments')->cascadeOnDelete();
            $table->foreign('invoice_id', 'fk_mfp_invoice')
                ->references('id')->on('invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_fee_periods');
    }
};
