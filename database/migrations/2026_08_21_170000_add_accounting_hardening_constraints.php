<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Unique constraint on journal_no to prevent duplicate journal numbers
        // (concurrent-safe journal numbering)
        Schema::table('journals', function (Blueprint $table) {
            $table->unique('journal_no', 'uq_journals_journal_no');
        });

        // Index on invoices for faster payment lookups (overpayment check, AR aging)
        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['institute_id', 'status', 'due_amount'], 'idx_invoices_status_due');
        });

        // Index on journal_entries for faster AR/AP derivation
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->index(['party_id', 'coa_id'], 'idx_je_party_coa');
        });

        // Index on fx_revaluations for faster revaluation lookups
        Schema::table('fx_revaluations', function (Blueprint $table) {
            $table->index(['institute_id', 'status', 'currency_id'], 'idx_fxr_scope_status');
        });

        // Index on accounting_periods for period validation on posting
        Schema::table('accounting_periods', function (Blueprint $table) {
            $table->index(['institute_id', 'status'], 'idx_periods_scope_status');
        });
    }

    public function down(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->dropIndex('uq_journals_journal_no');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('idx_invoices_status_due');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex('idx_je_party_coa');
        });

        Schema::table('fx_revaluations', function (Blueprint $table) {
            $table->dropIndex('idx_fxr_scope_status');
        });

        Schema::table('accounting_periods', function (Blueprint $table) {
            $table->dropIndex('idx_periods_scope_status');
        });
    }
};
