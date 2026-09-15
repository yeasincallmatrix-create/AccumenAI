<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescription_items', function (Blueprint $table) {
            $table->string('item_status', 20)->default('active')->after('status');
            $table->string('discontinued_reason', 255)->nullable()->after('item_status');
            $table->timestamp('discontinued_at')->nullable()->after('discontinued_reason');
            $table->unsignedBigInteger('continued_from_item_id')->nullable()->after('discontinued_at');
            $table->foreign('continued_from_item_id')->references('id')->on('prescription_items')->onDelete('set null');
            $table->index(['prescription_id', 'item_status'], 'idx_rx_items_status');
            $table->index('continued_from_item_id');
        });

        DB::table('prescription_items')->whereNull('item_status')->update(['item_status' => 'active']);
    }

    public function down(): void
    {
        Schema::table('prescription_items', function (Blueprint $table) {
            $table->dropForeign(['continued_from_item_id']);
            $table->dropIndex('idx_rx_items_status');
            $table->dropIndex('prescription_items_continued_from_item_id_index');
            $table->dropColumn(['item_status', 'discontinued_reason', 'discontinued_at', 'continued_from_item_id']);
        });
    }
};
