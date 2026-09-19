<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_order_lines')) {
            return;
        }

        Schema::create('sales_order_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('inventory_item_id')->nullable();
            $table->string('description');
            $table->decimal('quantity', 19, 4);
            $table->string('unit')->nullable();
            $table->decimal('unit_price', 19, 4);
            $table->decimal('discount_amount', 19, 4)->default(0);
            $table->string('discount_type')->default('fixed');
            $table->unsignedBigInteger('tax_group_id')->nullable();
            $table->decimal('tax_rate', 10, 4)->default(0);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('line_total', 19, 4);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
            $table->foreign('tax_group_id')->references('id')->on('tax_groups')->onDelete('restrict');
            $table->foreign('order_id')->references('id')->on('sales_orders')->onDelete('restrict');
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_lines');
    }
};
