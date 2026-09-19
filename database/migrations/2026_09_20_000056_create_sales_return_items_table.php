<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_return_items')) {
            return;
        }

        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('return_id');
            $table->unsignedBigInteger('invoice_item_id')->nullable();
            $table->unsignedBigInteger('sales_order_line_id')->nullable();
            $table->unsignedBigInteger('inventory_item_id')->nullable();
            $table->string('description');
            $table->decimal('quantity', 19, 4);
            $table->decimal('unit_price', 19, 4)->default(0);
            $table->decimal('discount_amount', 19, 4)->default(0);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('line_total', 19, 4)->default(0);
            $table->timestamps();
        });

        Schema::table('sales_return_items', function (Blueprint $table) {
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
            $table->foreign('sales_order_line_id')->references('id')->on('sales_order_lines')->onDelete('restrict');
            $table->foreign('return_id')->references('id')->on('sales_returns')->onDelete('restrict');
            $table->foreign('invoice_item_id')->references('id')->on('invoice_items')->onDelete('restrict');
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_items');
    }
};
