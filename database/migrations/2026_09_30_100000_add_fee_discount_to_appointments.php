<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Visit-fee discount on appointments (HMS delta 18).
     *
     * The Collect Visit Fee popup accepts an optional discount, either a
     * percent or a flat currency amount. fee_collected_amount keeps storing
     * the NET collected figure; fee_discount_amount records the currency
     * value taken off so the money trail stays intact
     * (gross = fee_applied, net = fee_collected_amount).
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('appointments', 'fee_discount_type')) {
                $table->string('fee_discount_type', 10)->nullable()->after('fee_collected_amount');
            }
            if (! Schema::hasColumn('appointments', 'fee_discount_value')) {
                $table->decimal('fee_discount_value', 10, 2)->nullable()->after('fee_discount_type');
            }
            if (! Schema::hasColumn('appointments', 'fee_discount_amount')) {
                $table->decimal('fee_discount_amount', 10, 2)->nullable()->after('fee_discount_value');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            foreach (['fee_discount_amount', 'fee_discount_value', 'fee_discount_type'] as $column) {
                if (Schema::hasColumn('appointments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
