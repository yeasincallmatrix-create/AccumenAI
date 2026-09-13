<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            if (! Schema::hasColumn('goods_receipt_items', 'batch_number')) {
                $table->string('batch_number', 80)->nullable()->after('unit_cost');
            }
            if (! Schema::hasColumn('goods_receipt_items', 'lot_number')) {
                $table->string('lot_number', 80)->nullable()->after('batch_number');
            }
            if (! Schema::hasColumn('goods_receipt_items', 'expiry_date')) {
                $table->date('expiry_date')->nullable()->after('lot_number');
            }
            if (! Schema::hasColumn('goods_receipt_items', 'manufacture_date')) {
                $table->date('manufacture_date')->nullable()->after('expiry_date');
            }
            if (! Schema::hasColumn('goods_receipt_items', 'serial_numbers')) {
                $table->json('serial_numbers')->nullable()->after('manufacture_date');
            }
            if (! Schema::hasColumn('goods_receipt_items', 'received_condition')) {
                $table->string('received_condition', 30)->nullable()->after('serial_numbers')->comment('good/damaged/expired etc');
            }
            if (! Schema::hasColumn('goods_receipt_items', 'batch_id')) {
                $table->foreignId('batch_id')->nullable()->after('inventory_item_id')->constrained('inventory_batches')->nullOnDelete();
            }
        });

        // Add reversed status to goods_receipts if not exists via raw check
        // Status is enum in model but DB is string, so no migration needed for status values
        Schema::table('goods_receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('goods_receipts', 'reversed_at')) {
                $table->timestamp('reversed_at')->nullable()->after('cancelled_at');
            }
            if (! Schema::hasColumn('goods_receipts', 'reversed_by')) {
                $table->unsignedBigInteger('reversed_by')->nullable()->after('reversed_at');
            }
            if (! Schema::hasColumn('goods_receipts', 'reversal_reason')) {
                $table->text('reversal_reason')->nullable()->after('reversed_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            foreach (['batch_number','lot_number','expiry_date','manufacture_date','serial_numbers','received_condition','batch_id'] as $col) {
                if (Schema::hasColumn('goods_receipt_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::table('goods_receipts', function (Blueprint $table) {
            foreach (['reversed_at','reversed_by','reversal_reason'] as $col) {
                if (Schema::hasColumn('goods_receipts', $col)) $table->dropColumn($col);
            }
        });
    }
};
