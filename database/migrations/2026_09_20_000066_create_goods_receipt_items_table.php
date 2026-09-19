<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('goods_receipt_items')) {
            return;
        }

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('goods_receipt_id');
            $table->unsignedBigInteger('purchase_order_line_id')->nullable();
            $table->unsignedBigInteger('inventory_item_id');
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->decimal('ordered_quantity', 19, 4);
            $table->decimal('previously_received_quantity', 19, 4)->default(0);
            $table->decimal('received_quantity', 19, 4);
            $table->decimal('rejected_quantity', 19, 4)->default(0);
            $table->decimal('unit_cost', 19, 4);
            $table->string('batch_number')->nullable();
            $table->string('lot_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('manufacture_date')->nullable();
            $table->longText('serial_numbers')->nullable();
            $table->string('received_condition')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('restrict');
            $table->foreign('goods_receipt_id')->references('id')->on('goods_receipts')->onDelete('restrict');
            $table->foreign('batch_id')->references('id')->on('inventory_batches')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
    }
};
