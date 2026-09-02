<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Step 37 — link invoice line items to the education fee head that produced
     * them so the student ledger and the fee-head reports can group revenue by
     * fee head. Additive only: legacy/manual invoices keep fee_head_id NULL.
     */
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->unsignedBigInteger('fee_head_id')->nullable()->after('coa_id');

            $table->index(['invoice_id', 'fee_head_id'], 'idx_invoice_items_invoice_fee_head');
            $table->foreign('fee_head_id', 'fk_invoice_items_fee_head')
                ->references('id')->on('fee_heads')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign('fk_invoice_items_fee_head');
            $table->dropIndex('idx_invoice_items_invoice_fee_head');
            $table->dropColumn('fee_head_id');
        });
    }
};
