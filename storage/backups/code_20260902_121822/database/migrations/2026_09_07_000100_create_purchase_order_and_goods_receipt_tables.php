<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add received_quantity / rejected_quantity to existing PO lines
        if (Schema::hasTable('purchase_order_lines') && ! Schema::hasColumn('purchase_order_lines', 'received_quantity')) {
            Schema::table('purchase_order_lines', function (Blueprint $table) {
                $table->decimal('received_quantity', 19, 4)->default(0)->after('quantity');
                $table->decimal('rejected_quantity', 19, 4)->default(0)->after('received_quantity');
            });
        }

        // Goods receipt header
        if (! Schema::hasTable('goods_receipts')) {
            Schema::create('goods_receipts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('receipt_number', 50);
                if (Schema::hasTable('purchase_orders')) {
                    $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
                } else {
                    $table->unsignedBigInteger('purchase_order_id')->nullable();
                }
                $table->foreignId('supplier_id')->constrained('parties')->cascadeOnDelete();
                $table->foreignId('warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete();
                $table->date('receipt_date');
                $table->enum('status', ['draft', 'confirmed', 'cancelled'])->default('draft');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('confirmed_by')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->unsignedBigInteger('cancelled_by')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancellation_reason')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['institute_id', 'receipt_number'], 'uq_gr_inst_number');
                $table->index(['institute_id', 'branch_id', 'status'], 'idx_gr_scope_status');
                $table->index(['institute_id', 'purchase_order_id'], 'idx_gr_po');
                $table->index(['institute_id', 'supplier_id'], 'idx_gr_supplier');
            });
        }

        // Goods receipt line items
        if (! Schema::hasTable('goods_receipt_items')) {
            Schema::create('goods_receipt_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
                if (Schema::hasTable('purchase_order_lines')) {
                    $table->foreignId('purchase_order_line_id')->constrained('purchase_order_lines')->cascadeOnDelete();
                } else {
                    $table->unsignedBigInteger('purchase_order_line_id')->nullable();
                }
                $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
                $table->decimal('ordered_quantity', 19, 4);
                $table->decimal('previously_received_quantity', 19, 4)->default(0);
                $table->decimal('received_quantity', 19, 4);
                $table->decimal('rejected_quantity', 19, 4)->default(0);
                $table->decimal('unit_cost', 19, 4);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');

        if (Schema::hasTable('purchase_order_lines') && Schema::hasColumn('purchase_order_lines', 'received_quantity')) {
            Schema::table('purchase_order_lines', function (Blueprint $table) {
                $table->dropColumn(['received_quantity', 'rejected_quantity']);
            });
        }
    }
};
