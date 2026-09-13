<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_orders')) {
            Schema::create('sales_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('order_number', 40);
                $table->foreignId('quotation_id')->nullable()->constrained('sales_quotations')->nullOnDelete();
                $table->foreignId('customer_id')->constrained('parties')->cascadeOnDelete();
                $table->date('order_date');
                $table->date('expected_delivery_date')->nullable();
                $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
                $table->string('payment_terms', 40)->nullable();
                $table->text('billing_address')->nullable();
                $table->text('shipping_address')->nullable();
                $table->text('notes')->nullable();
                $table->text('terms_conditions')->nullable();
                $table->decimal('subtotal', 19, 4)->default(0);
                $table->decimal('discount_amount', 19, 4)->default(0);
                $table->string('discount_type', 20)->default('fixed');
                $table->decimal('tax_amount', 19, 4)->default(0);
                $table->decimal('grand_total', 19, 4)->default(0);
                $table->string('status', 30)->default('draft');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['institute_id', 'order_number'], 'uq_sales_orders_number');
                $table->unique(['institute_id', 'quotation_id'], 'uq_sales_orders_quotation');
                $table->index(['institute_id', 'branch_id', 'status'], 'idx_sales_orders_scope_status');
                $table->index(['institute_id', 'customer_id'], 'idx_sales_orders_customer');
                $table->index(['institute_id', 'order_date'], 'idx_sales_orders_date');
            });
        }

        if (! Schema::hasTable('sales_order_lines')) {
            Schema::create('sales_order_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('order_id')->constrained('sales_orders')->cascadeOnDelete();
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

                $table->index(['order_id', 'sort_order'], 'idx_so_lines_order');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_lines');
        Schema::dropIfExists('sales_orders');
    }
};
