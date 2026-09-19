<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_return_refunds')) {
            return;
        }

        Schema::create('sales_return_refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('return_id');
            $table->enum('method', ['cash', 'bank', 'other', 'credit'])->default('credit');
            $table->decimal('amount', 19, 4);
            $table->string('reference')->nullable();
            $table->date('refund_date');
            $table->unsignedBigInteger('journal_id')->nullable();
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::table('sales_return_refunds', function (Blueprint $table) {
            $table->foreign('journal_id')->references('id')->on('journals')->onDelete('restrict');
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('institute_users')->onDelete('restrict');
            $table->foreign('return_id')->references('id')->on('sales_returns')->onDelete('restrict');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('restrict');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_refunds');
    }
};
