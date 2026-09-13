<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_deliveries')) {
            Schema::create('sales_deliveries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('delivery_number', 40);
                $table->foreignId('order_id')->constrained('sales_orders')->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('parties')->cascadeOnDelete();
                $table->foreignId('warehouse_id')->nullable()->constrained('inventory_warehouses')->nullOnDelete();
                $table->date('delivery_date');
                $table->text('shipping_address')->nullable();
                $table->text('notes')->nullable();
                $table->string('status', 20)->default('draft'); // draft, confirmed, delivered, cancelled
                $table->unsignedBigInteger('delivered_by')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['institute_id', 'delivery_number'], 'uq_sales_deliveries_number');
                $table->index(['institute_id', 'branch_id', 'status'], 'idx_sales_deliveries_scope_status');
                $table->index(['institute_id', 'order_id'], 'idx_sales_deliveries_order');
                $table->index(['institute_id', 'customer_id'], 'idx_sales_deliveries_customer');
            });
        }

        if (! Schema::hasTable('sales_delivery_lines')) {
            Schema::create('sales_delivery_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('delivery_id')->constrained('sales_deliveries')->cascadeOnDelete();
                $table->foreignId('order_line_id')->constrained('sales_order_lines')->cascadeOnDelete();
                $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
                $table->string('description', 500);
                $table->decimal('ordered_quantity', 19, 4);
                $table->decimal('previously_delivered_quantity', 19, 4)->default(0);
                $table->decimal('delivery_quantity', 19, 4);
                $table->string('unit', 30)->nullable();
                $table->timestamps();

                $table->index(['delivery_id'], 'idx_sdl_delivery');
                $table->index(['order_line_id'], 'idx_sdl_order_line');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_delivery_lines');
        Schema::dropIfExists('sales_deliveries');
    }
};
