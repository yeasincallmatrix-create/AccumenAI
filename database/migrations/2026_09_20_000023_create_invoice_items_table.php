<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoice_items')) {
            return;
        }

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->string('description');
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('quantity', 19, 4)->nullable();
            $table->decimal('unit_price', 19, 4)->nullable();
            $table->decimal('discount_amount', 19, 4)->default(0);
            $table->decimal('tax_rate', 10, 4)->default(0);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->unsignedBigInteger('coa_id')->nullable();
            $table->unsignedBigInteger('inventory_item_id')->nullable();
            $table->unsignedBigInteger('sales_order_line_id')->nullable();
            $table->unsignedBigInteger('fee_head_id')->nullable();
            $table->unsignedBigInteger('tax_group_id')->nullable();
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('restrict');
            $table->foreign('coa_id')->references('id')->on('chart_of_accounts')->onDelete('restrict');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('restrict');
            $table->foreign('tax_group_id')->references('id')->on('tax_groups')->onDelete('restrict');
            $table->foreign('fee_head_id')->references('id')->on('fee_heads')->onDelete('restrict');
            $table->foreign('sales_order_line_id')->references('id')->on('sales_order_lines')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
