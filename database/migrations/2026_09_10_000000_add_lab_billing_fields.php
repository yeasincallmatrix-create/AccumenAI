<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 4 — line-item storage + payment audit trail for invoices.
     *
     * NOTE: targets `medical_invoices`, NOT `invoices` — the plain
     * `invoices` table belongs to the finance module and must not be
     * touched (see Phase 0). `lab_orders.result_notes` already exists
     * from Phase 0, so it is not re-added here.
     */
    public function up(): void
    {
        Schema::table('medical_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('medical_invoices', 'items_data')) {
                $table->text('items_data')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('medical_invoices', 'payment_reference')) {
                $table->string('payment_reference', 100)->nullable()->after('payment_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('medical_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('medical_invoices', 'payment_reference')) {
                $table->dropColumn('payment_reference');
            }
            if (Schema::hasColumn('medical_invoices', 'items_data')) {
                $table->dropColumn('items_data');
            }
        });
    }
};
