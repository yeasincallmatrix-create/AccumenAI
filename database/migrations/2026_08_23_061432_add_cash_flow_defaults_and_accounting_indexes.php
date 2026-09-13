<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * STEP 69E — Accounting hardening & cash flow reliability.
 *
 * 1. Default cash_flow_category for system COA template accounts.
 * 2. Performance indexes for journals and journal_entries.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Priority 1: Cash flow classification defaults ────────────────
        // Only update system accounts where cash_flow_category is NULL.
        // User-defined classifications are never touched.

        $this->setCategory('1100', 'operating');  // Accounts Receivable
        $this->setCategory('1200', 'operating');  // Inventory Asset
        $this->setCategory('1201', 'operating');  // Input VAT / Tax Receivable
        $this->setCategory('2001', 'operating');  // Accounts Payable
        $this->setCategory('2002', 'operating');  // Unearned Revenue
        $this->setCategory('2100', 'operating');  // VAT Payable
        $this->setCategory('2101', 'operating');  // Withholding Tax Payable
        $this->setCategory('2102', 'operating');  // Tax Clearing

        $this->setCategory('1300', 'investing');  // Fixed Assets
        $this->setCategory('1301', 'investing');  // Accumulated Depreciation

        $this->setCategory('2003', 'financing');  // Loans Payable
        $this->setCategory('3001', 'financing');  // Owner's Capital
        $this->setCategory('3002', 'financing');  // Retained Earnings
        $this->setCategory('3100', 'financing');  // Revaluation Surplus

        // Revenue (4xxx) → operating
        DB::table('chart_of_accounts')
            ->where('is_system', true)
            ->whereNull('cash_flow_category')
            ->whereBetween('code', ['4000', '4999'])
            ->update(['cash_flow_category' => 'operating']);

        // Expense (5xxx) → operating
        DB::table('chart_of_accounts')
            ->where('is_system', true)
            ->whereNull('cash_flow_category')
            ->whereBetween('code', ['5000', '5999'])
            ->update(['cash_flow_category' => 'operating']);

        // ── Priority 3: Performance indexes ─────────────────────────────

        Schema::table('journals', function (Blueprint $table) {
            $table->index(['status', 'institute_id'], 'idx_journals_status_institute');
            $table->index('reversal_of', 'idx_journals_reversal_of');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->index(['journal_id', 'coa_id'], 'idx_je_journal_coa');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex('idx_je_journal_coa');
        });

        Schema::table('journals', function (Blueprint $table) {
            $table->dropIndex('idx_journals_reversal_of');
            $table->dropIndex('idx_journals_status_institute');
        });

        DB::table('chart_of_accounts')
            ->whereIn('code', [
                '1100', '1200', '1201', '1300', '1301',
                '2001', '2002', '2003', '2100', '2101', '2102',
                '3001', '3002', '3100',
            ])
            ->update(['cash_flow_category' => null]);

        DB::table('chart_of_accounts')
            ->where('is_system', true)
            ->where('cash_flow_category', 'operating')
            ->whereBetween('code', ['4000', '5999'])
            ->update(['cash_flow_category' => null]);
    }

    private function setCategory(string $code, string $category): void
    {
        DB::table('chart_of_accounts')
            ->where('code', $code)
            ->where('is_system', true)
            ->whereNull('cash_flow_category')
            ->update(['cash_flow_category' => $category]);
    }
};
