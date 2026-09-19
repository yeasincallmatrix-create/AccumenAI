<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_returns')) {
            return;
        }

        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('return_number');
            $table->string('credit_note_number')->nullable();
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->date('return_date');
            $table->enum('status', ['draft', 'approved', 'posted', 'cancelled', 'reversed'])->default('draft');
            $table->enum('refund_status', ['none', 'pending', 'partial', 'refunded', 'credited'])->default('none');
            $table->enum('refund_method', ['credit', 'cash', 'bank', 'other'])->nullable();
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 19, 4)->default(0);
            $table->decimal('discount_amount', 19, 4)->default(0);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('grand_total', 19, 4)->default(0);
            $table->decimal('refundable_amount', 19, 4)->default(0);
            $table->decimal('refunded_amount', 19, 4)->default(0);
            $table->unsignedBigInteger('journal_id')->nullable();
            $table->unsignedBigInteger('inventory_journal_id')->nullable();
            $table->unsignedBigInteger('reversal_of')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->longText('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('sales_returns', function (Blueprint $table) {
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('restrict');
            $table->foreign('order_id')->references('id')->on('sales_orders')->onDelete('restrict');
            $table->foreign('customer_id')->references('id')->on('parties')->onDelete('restrict');
            $table->foreign('approved_by')->references('id')->on('institute_users')->onDelete('restrict');
            $table->foreign('journal_id')->references('id')->on('journals')->onDelete('restrict');
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('restrict');
            $table->foreign('warehouse_id')->references('id')->on('inventory_warehouses')->onDelete('restrict');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('institute_users')->onDelete('restrict');
            $table->foreign('reversal_of')->references('id')->on('sales_returns')->onDelete('restrict');
            $table->foreign('inventory_journal_id')->references('id')->on('journals')->onDelete('restrict');
            $table->foreign('cancelled_by')->references('id')->on('institute_users')->onDelete('restrict');
            $table->foreign('posted_by')->references('id')->on('institute_users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_returns');
    }
};
