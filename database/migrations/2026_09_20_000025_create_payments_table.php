<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments')) {
            return;
        }

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('party_id')->nullable();
            $table->unsignedBigInteger('installment_id')->nullable();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('exchange_rate', 19, 8)->nullable();
            $table->decimal('base_amount', 19, 4)->nullable();
            $table->decimal('applied_amount', 19, 4)->nullable();
            $table->enum('payment_method', ['cash', 'bkash', 'nagad', 'rocket', 'bank', 'card', 'online', 'other'])->default('cash');
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->unsignedBigInteger('journal_id')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('receipt_number')->nullable();
            $table->timestamp('receipt_printed_at')->nullable();
            $table->dateTime('paid_at');
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('party_id')->references('id')->on('parties')->onDelete('restrict');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('restrict');
            $table->foreign('journal_id')->references('id')->on('journals')->onDelete('restrict');
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('restrict');
            $table->foreign('installment_id')->references('id')->on('installments')->onDelete('restrict');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('restrict');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->onDelete('restrict');
            $table->foreign('received_by')->references('id')->on('institute_users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
