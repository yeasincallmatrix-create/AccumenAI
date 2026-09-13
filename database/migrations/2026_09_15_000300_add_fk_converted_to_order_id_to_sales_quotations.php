<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_quotations') || ! Schema::hasTable('sales_orders')) {
            return;
        }

        // Clean orphaned references before adding FK (production-safe)
        DB::table('sales_quotations')
            ->whereNotNull('converted_to_order_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('sales_orders')->whereColumn('sales_orders.id', 'sales_quotations.converted_to_order_id');
            })
            ->update(['converted_to_order_id' => null, 'converted_at' => null]);

        // Check if FK already exists
        $exists = DB::selectOne(
            "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales_quotations' AND COLUMN_NAME = 'converted_to_order_id' AND REFERENCED_TABLE_NAME = 'sales_orders' LIMIT 1"
        );

        if ($exists) {
            return;
        }

        Schema::table('sales_quotations', function (Blueprint $table) {
            $table->foreign('converted_to_order_id')->references('id')->on('sales_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sales_quotations')) {
            return;
        }

        try {
            Schema::table('sales_quotations', function (Blueprint $table) {
                $table->dropForeign(['converted_to_order_id']);
            });
        } catch (\Throwable $e) {
            // FK may not exist or already dropped
        }
    }
};
