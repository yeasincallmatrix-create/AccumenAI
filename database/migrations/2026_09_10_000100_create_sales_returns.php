<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('return_number', 40);
            $table->string('credit_note_number', 40)->nullable();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('parties')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('inventory_warehouses')->nullOnDelete();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->date('return_date');
            $table->enum('status', ['draft','approved','posted','cancelled','reversed'])->default('draft');
            $table->enum('refund_status', ['none','pending','partial','refunded','credited'])->default('none');
            $table->enum('refund_method', ['credit','cash','bank','other'])->nullable();
            $table->string('reason', 500)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 19, 4)->default(0);
            $table->decimal('discount_amount', 19, 4)->default(0);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('grand_total', 19, 4)->default(0);
            $table->decimal('refundable_amount', 19, 4)->default(0);
            $table->decimal('refunded_amount', 19, 4)->default(0);
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->foreignId('inventory_journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->foreignId('reversal_of')->nullable()->constrained('sales_returns')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('institute_users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('institute_users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('institute_users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('institute_users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['institute_id','return_number']);
            $table->unique(['institute_id','credit_note_number']);
            $table->index(['institute_id','branch_id','status']);
            $table->index(['invoice_id']);
        });

        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('return_id')->constrained('sales_returns')->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained('invoice_items')->nullOnDelete();
            $table->foreignId('sales_order_line_id')->nullable()->constrained('sales_order_lines')->nullOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
            $table->string('description', 500);
            $table->decimal('quantity', 19, 4);
            $table->decimal('unit_price', 19, 4)->default(0);
            $table->decimal('discount_amount', 19, 4)->default(0);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('line_total', 19, 4)->default(0);
            $table->timestamps();
            $table->index(['return_id']);
        });

        Schema::create('sales_return_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('return_id')->constrained('sales_returns')->cascadeOnDelete();
            $table->enum('method', ['cash','bank','other','credit'])->default('credit');
            $table->decimal('amount', 19, 4);
            $table->string('reference', 200)->nullable();
            $table->date('refund_date');
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('institute_users')->nullOnDelete();
            $table->timestamps();
            $table->index(['return_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_refunds');
        Schema::dropIfExists('sales_return_items');
        Schema::dropIfExists('sales_returns');
    }
};
