<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_orders')) {
            Schema::create('purchase_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('order_number', 40);
                $table->string('reference_number', 80)->nullable();
                $table->foreignId('supplier_id')->constrained('parties')->cascadeOnDelete();
                $table->foreignId('warehouse_id')->nullable()->constrained('inventory_warehouses')->nullOnDelete();
                $table->date('order_date');
                $table->date('expected_delivery_date')->nullable();
                $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
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

                $table->unique(['institute_id', 'order_number'], 'uq_purchase_orders_number');
                $table->index(['institute_id', 'branch_id', 'status'], 'idx_po_scope_status');
                $table->index(['institute_id', 'supplier_id'], 'idx_po_supplier');
                $table->index(['institute_id', 'order_date'], 'idx_po_date');
                $table->index(['institute_id', 'warehouse_id'], 'idx_po_warehouse');
            });
        }

        if (! Schema::hasTable('purchase_order_lines')) {
            Schema::create('purchase_order_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('order_id')->constrained('purchase_orders')->cascadeOnDelete();
                $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
                $table->string('description', 500);
                $table->decimal('quantity', 19, 4);
                $table->string('unit', 30)->nullable();
                $table->decimal('unit_price', 19, 4);
                $table->decimal('discount_amount', 19, 4)->default(0);
                $table->string('discount_type', 20)->default('fixed');
                $table->decimal('discount_rate', 10, 4)->default(0);
                $table->foreignId('tax_group_id')->nullable()->constrained('tax_groups')->nullOnDelete();
                $table->decimal('tax_rate', 10, 4)->default(0);
                $table->decimal('tax_amount', 19, 4)->default(0);
                $table->decimal('line_total', 19, 4);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['order_id', 'sort_order'], 'idx_po_lines_order');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
    }
};
