<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_delivery_lines')) {
            return;
        }

        Schema::create('sales_delivery_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('delivery_id');
            $table->unsignedBigInteger('order_line_id');
            $table->unsignedBigInteger('inventory_item_id')->nullable();
            $table->string('description');
            $table->decimal('ordered_quantity', 19, 4);
            $table->decimal('previously_delivered_quantity', 19, 4)->default(0);
            $table->decimal('delivery_quantity', 19, 4);
            $table->string('unit')->nullable();
            $table->timestamps();
        });

        Schema::table('sales_delivery_lines', function (Blueprint $table) {
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('restrict');
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
            $table->foreign('delivery_id')->references('id')->on('sales_deliveries')->onDelete('restrict');
            $table->foreign('order_line_id')->references('id')->on('sales_order_lines')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_delivery_lines');
    }
};
