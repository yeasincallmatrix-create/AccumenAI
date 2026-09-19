<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('online_payment_attempts')) {
            return;
        }

        Schema::create('online_payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('gateway_id');
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('installment_id')->nullable();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->decimal('amount', 19, 4);
            $table->decimal('base_amount', 19, 4)->nullable();
            $table->decimal('exchange_rate', 19, 8)->nullable();
            $table->string('currency_code')->nullable();
            $table->string('status')->default('pending');
            $table->string('gateway_reference')->nullable()->unique();
            $table->string('idempotency_key')->nullable()->unique();
            $table->text('failure_reason')->nullable();
            $table->longText('gateway_response')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->unsignedBigInteger('journal_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('online_payment_attempts', function (Blueprint $table) {
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('restrict');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('restrict');
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('restrict');
            $table->foreign('installment_id')->references('id')->on('installments')->onDelete('restrict');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('restrict');
            $table->foreign('gateway_id')->references('id')->on('payment_gateways')->onDelete('restrict');
            $table->foreign('journal_id')->references('id')->on('journals')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('institute_users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_payment_attempts');
    }
};
