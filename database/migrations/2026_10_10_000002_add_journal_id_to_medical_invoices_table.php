<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase A (medical ledger foundation) — link a medical invoice to the sale
 * journal it posted, mirroring appointments.journal_id. BillingService's
 * processPayment writes only medical_invoices today; the journal_id column
 * is the hook Phase B's posting code will populate.
 *
 * Nullable + idempotent (hasColumn guard) per this repo's delta-migration
 * convention on top of the data-only schema dump.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('medical_invoices', 'journal_id')) {
            return;
        }

        Schema::table('medical_invoices', function (Blueprint $table): void {
            $table->unsignedBigInteger('journal_id')
                ->nullable()
                ->after('payment_reference')
                ->comment('Sale journal posted for this invoice (Phase B wiring)');

            $table->foreign('journal_id')
                ->references('id')
                ->on('journals')
                ->nullOnDelete();

            $table->index('journal_id', 'medical_invoices_journal_id_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('medical_invoices', 'journal_id')) {
            return;
        }

        Schema::table('medical_invoices', function (Blueprint $table): void {
            $table->dropIndex('medical_invoices_journal_id_idx');
            $table->dropForeign(['journal_id']);
            $table->dropColumn('journal_id');
        });
    }
};
