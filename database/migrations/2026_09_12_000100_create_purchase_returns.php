<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_returns')) {
            Schema::create('purchase_returns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('return_number', 40);
                $table->string('credit_note_number', 40)->nullable();
                $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
                $table->foreignId('goods_receipt_id')->nullable()->constrained('goods_receipts')->nullOnDelete();
                $table->foreignId('purchase_invoice_id')->nullable()->constrained('purchase_invoices')->nullOnDelete();
                $table->foreignId('supplier_id')->constrained('parties')->cascadeOnDelete();
                $table->foreignId('warehouse_id')->nullable()->constrained('inventory_warehouses')->nullOnDelete();
                $table->date('return_date');
                $table->text('reason')->nullable();
                $table->text('notes')->nullable();
                $table->decimal('subtotal', 19, 4)->default(0);
                $table->decimal('discount_amount', 19, 4)->default(0);
                $table->decimal('tax_amount', 19, 4)->default(0);
                $table->decimal('grand_total', 19, 4)->default(0);
                $table->string('status', 20)->default('draft'); // draft, submitted, approved, posted, cancelled, reversed
                $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('posted_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['institute_id', 'return_number'], 'uq_purchase_returns_number');
                $table->unique(['institute_id', 'credit_note_number'], 'uq_purchase_returns_credit');
                $table->index(['institute_id', 'branch_id', 'status'], 'idx_pr_scope_status');
                $table->index(['institute_id', 'supplier_id'], 'idx_pr_supplier');
            });
        }

        if (! Schema::hasTable('purchase_return_items')) {
            Schema::create('purchase_return_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('purchase_return_id')->constrained('purchase_returns')->cascadeOnDelete();
                $table->foreignId('purchase_order_line_id')->nullable()->constrained('purchase_order_lines')->nullOnDelete();
                $table->foreignId('goods_receipt_item_id')->nullable()->constrained('goods_receipt_items')->nullOnDelete();
                $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
                $table->string('description', 500);
                $table->decimal('quantity', 19, 4); // returned quantity
                $table->string('unit', 30)->nullable();
                $table->decimal('unit_price', 19, 4);
                $table->decimal('discount_amount', 19, 4)->default(0);
                $table->string('discount_type', 20)->default('fixed');
                $table->decimal('tax_rate', 10, 4)->default(0);
                $table->decimal('tax_amount', 19, 4)->default(0);
                $table->decimal('line_total', 19, 4);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['purchase_return_id', 'sort_order'], 'idx_pri_order');
            });
        }

        if (! Schema::hasTable('supplier_credit_balances')) {
            Schema::create('supplier_credit_balances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->foreignId('supplier_id')->constrained('parties')->cascadeOnDelete();
                $table->foreignId('purchase_return_id')->constrained('purchase_returns')->cascadeOnDelete();
                $table->decimal('credit_amount', 19, 4);
                $table->decimal('used_amount', 19, 4)->default(0);
                $table->decimal('remaining_amount', 19, 4);
                $table->string('status', 20)->default('available'); // available, partially_used, fully_used, refunded
                $table->timestamps();

                $table->index(['institute_id', 'supplier_id', 'status'], 'idx_scb_supplier');
            });
        }

        if (! Schema::hasTable('supplier_refunds')) {
            Schema::create('supplier_refunds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->foreignId('supplier_id')->constrained('parties')->cascadeOnDelete();
                $table->foreignId('purchase_return_id')->nullable()->constrained('purchase_returns')->nullOnDelete();
                $table->decimal('amount', 19, 4);
                $table->string('refund_method', 20)->default('cash');
                $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['institute_id', 'supplier_id'], 'idx_sr_supplier');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_refunds');
        Schema::dropIfExists('supplier_credit_balances');
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
    }
};
