<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Step 37 — education waivers. An approved waiver reduces an unpaid invoice
     * through the invoice discount (never a parallel adjustment): the sale
     * journal is reversed (or its draft voided), the discount grows, the
     * installments are rebalanced and a fresh sale journal is created. This
     * table records the approval (who / when / why / how much) and feeds the
     * student ledger and the dashboard "discounts/waivers" metric.
     */
    public function up(): void
    {
        Schema::create('student_waivers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('enrollment_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('reason', 500)->nullable();
            $table->unsignedBigInteger('waived_by')->nullable();
            $table->timestamp('waived_at')->nullable();
            $table->timestamps();

            $table->index(['institute_id', 'branch_id'], 'idx_student_waivers_inst_branch');
            $table->index(['institute_id', 'student_id'], 'idx_student_waivers_inst_student');

            $table->foreign('institute_id', 'fk_student_waivers_institute')
                ->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id', 'fk_student_waivers_branch')
                ->references('id')->on('branches')->nullOnDelete();
            $table->foreign('invoice_id', 'fk_student_waivers_invoice')
                ->references('id')->on('invoices')->nullOnDelete();
            $table->foreign('student_id', 'fk_student_waivers_student')
                ->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('enrollment_id', 'fk_student_waivers_enrollment')
                ->references('id')->on('student_enrollments')->nullOnDelete();
            $table->foreign('waived_by', 'fk_student_waivers_waived_by')
                ->references('id')->on('institute_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_waivers');
    }
};
