<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_deliveries')) {
            return;
        }

        Schema::create('sales_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('delivery_number');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->date('delivery_date');
            $table->text('shipping_address')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('delivered_by')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('sales_deliveries', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('sales_orders')->onDelete('restrict');
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
            $table->foreign('customer_id')->references('id')->on('parties')->onDelete('restrict');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('restrict');
            $table->foreign('warehouse_id')->references('id')->on('inventory_warehouses')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_deliveries');
    }
};
