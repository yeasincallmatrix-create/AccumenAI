<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'sales_order_id')) {
                $table->foreignId('sales_order_id')->nullable()->after('journal_id')->constrained('sales_orders')->nullOnDelete();
                $table->index('sales_order_id', 'idx_invoices_sales_order');
            }
            if (! Schema::hasColumn('invoices', 'sales_delivery_id')) {
                $table->foreignId('sales_delivery_id')->nullable()->after('sales_order_id')->constrained('sales_deliveries')->nullOnDelete();
                $table->index('sales_delivery_id', 'idx_invoices_sales_delivery');
            }
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_items', 'sales_order_line_id')) {
                $table->foreignId('sales_order_line_id')->nullable()->after('inventory_item_id')->constrained('sales_order_lines')->nullOnDelete();
                $table->index('sales_order_line_id', 'idx_invoice_items_so_line');
            }
            if (! Schema::hasColumn('invoice_items', 'quantity')) {
                $table->decimal('quantity', 19, 4)->nullable()->after('amount');
            }
            if (! Schema::hasColumn('invoice_items', 'unit_price')) {
                $table->decimal('unit_price', 19, 4)->nullable()->after('quantity');
            }
            if (! Schema::hasColumn('invoice_items', 'discount_amount')) {
                $table->decimal('discount_amount', 19, 4)->default(0)->after('unit_price');
            }
            if (! Schema::hasColumn('invoice_items', 'tax_rate')) {
                $table->decimal('tax_rate', 10, 4)->default(0)->after('discount_amount');
            }
            if (! Schema::hasColumn('invoice_items', 'tax_amount')) {
                $table->decimal('tax_amount', 19, 4)->default(0)->after('tax_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_items', 'sales_order_line_id')) {
                $table->dropForeign(['sales_order_line_id']);
                $table->dropColumn('sales_order_line_id');
            }
            foreach (['quantity', 'unit_price', 'discount_amount', 'tax_rate', 'tax_amount'] as $col) {
                if (Schema::hasColumn('invoice_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'sales_delivery_id')) {
                $table->dropForeign(['sales_delivery_id']);
                $table->dropColumn('sales_delivery_id');
            }
            if (Schema::hasColumn('invoices', 'sales_order_id')) {
                $table->dropForeign(['sales_order_id']);
                $table->dropColumn('sales_order_id');
            }
        });
    }
};
