<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_invoices')) {
            Schema::create('purchase_invoices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('invoice_number', 40);
                $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
                $table->foreignId('goods_receipt_id')->nullable()->constrained('goods_receipts')->nullOnDelete();
                $table->foreignId('supplier_id')->constrained('parties')->cascadeOnDelete();
                $table->date('invoice_date');
                $table->date('due_date')->nullable();
                $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
                $table->string('payment_terms', 40)->nullable();
                $table->text('notes')->nullable();
                $table->text('terms_conditions')->nullable();
                $table->decimal('subtotal', 19, 4)->default(0);
                $table->decimal('discount_amount', 19, 4)->default(0);
                $table->string('discount_type', 20)->default('fixed');
                $table->decimal('tax_amount', 19, 4)->default(0);
                $table->decimal('grand_total', 19, 4)->default(0);
                $table->decimal('paid_amount', 19, 4)->default(0);
                $table->decimal('due_amount', 19, 4)->default(0);
                $table->string('status', 20)->default('draft'); // draft, posted, cancelled, reversed
                $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['institute_id', 'invoice_number'], 'uq_purchase_invoices_number');
                $table->index(['institute_id', 'branch_id', 'status'], 'idx_pi_scope_status');
                $table->index(['institute_id', 'supplier_id'], 'idx_pi_supplier');
                $table->index(['institute_id', 'purchase_order_id'], 'idx_pi_po');
            });
        }

        if (! Schema::hasTable('purchase_invoice_items')) {
            Schema::create('purchase_invoice_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices')->cascadeOnDelete();
                $table->foreignId('purchase_order_line_id')->nullable()->constrained('purchase_order_lines')->nullOnDelete();
                $table->foreignId('goods_receipt_item_id')->nullable()->constrained('goods_receipt_items')->nullOnDelete();
                $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
                $table->string('description', 500);
                $table->decimal('quantity', 19, 4);
                $table->string('unit', 30)->nullable();
                $table->decimal('unit_price', 19, 4);
                $table->decimal('discount_amount', 19, 4)->default(0);
                $table->string('discount_type', 20)->default('fixed');
                $table->foreignId('tax_group_id')->nullable()->constrained('tax_groups')->nullOnDelete();
                $table->decimal('tax_rate', 10, 4)->default(0);
                $table->decimal('tax_amount', 19, 4)->default(0);
                $table->decimal('line_total', 19, 4);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['purchase_invoice_id', 'sort_order'], 'idx_pii_order');
            });
        }

        if (! Schema::hasTable('purchase_supplier_payments')) {
            Schema::create('purchase_supplier_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices')->cascadeOnDelete();
                $table->foreignId('supplier_id')->constrained('parties')->cascadeOnDelete();
                $table->decimal('amount', 19, 4);
                $table->string('payment_method', 20)->default('cash');
                $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
                $table->string('transaction_id', 100)->nullable();
                $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
                $table->timestamp('paid_at')->useCurrent();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['institute_id', 'purchase_invoice_id'], 'idx_psp_invoice');
                $table->index(['institute_id', 'supplier_id'], 'idx_psp_supplier');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_supplier_payments');
        Schema::dropIfExists('purchase_invoice_items');
        Schema::dropIfExists('purchase_invoices');
    }
};
