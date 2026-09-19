<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_invoice_items')) {
            return;
        }

        Schema::create('purchase_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('purchase_invoice_id');
            $table->unsignedBigInteger('purchase_order_line_id')->nullable();
            $table->unsignedBigInteger('goods_receipt_item_id')->nullable();
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

        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
            $table->foreign('tax_group_id')->references('id')->on('tax_groups')->onDelete('restrict');
            $table->foreign('goods_receipt_item_id')->references('id')->on('goods_receipt_items')->onDelete('restrict');
            $table->foreign('purchase_order_line_id')->references('id')->on('purchase_order_lines')->onDelete('restrict');
            $table->foreign('purchase_invoice_id')->references('id')->on('purchase_invoices')->onDelete('restrict');
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_items');
    }
};
