<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('monthly_fee_periods')) {
            return;
        }

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
        });

        Schema::table('monthly_fee_periods', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('students')->onDelete('restrict');
            $table->foreign('fee_structure_id')->references('id')->on('fee_structures')->onDelete('restrict');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('restrict');
            $table->foreign('enrollment_id')->references('id')->on('student_enrollments')->onDelete('restrict');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('restrict');
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_fee_periods');
    }
};
