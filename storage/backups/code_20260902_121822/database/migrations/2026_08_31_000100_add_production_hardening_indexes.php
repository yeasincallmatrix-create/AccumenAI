<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function hasIndex(string $table, string $index): bool
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);
            return count($indexes) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function up(): void
    {
        // fiscal_years: fast lookup for current open year (used in every posting)
        if (Schema::hasTable('fiscal_years') && ! $this->hasIndex('fiscal_years', 'idx_fy_current')) {
            Schema::table('fiscal_years', function (Blueprint $table) {
                $table->index(['institute_id', 'is_current', 'status'], 'idx_fy_current');
            });
        }

        // accounting_periods: period open check per fiscal year
        if (Schema::hasTable('accounting_periods') && ! $this->hasIndex('accounting_periods', 'idx_periods_fiscal_status')) {
            Schema::table('accounting_periods', function (Blueprint $table) {
                $table->index(['fiscal_year_id', 'status'], 'idx_periods_fiscal_status');
            });
        }

        // parties: filtered lists for AR/AP
        if (Schema::hasTable('parties') && ! $this->hasIndex('parties', 'idx_parties_scope')) {
            Schema::table('parties', function (Blueprint $table) {
                $table->index(['institute_id', 'branch_id', 'type'], 'idx_parties_scope');
            });
        }

        // chart_of_accounts: type-filtered COA tree
        if (Schema::hasTable('chart_of_accounts') && ! $this->hasIndex('chart_of_accounts', 'idx_coa_scope_type')) {
            Schema::table('chart_of_accounts', function (Blueprint $table) {
                $table->index(['institute_id', 'branch_id', 'type'], 'idx_coa_scope_type');
            });
        }

        // journal_entries: ledger and aging queries (coa + date, party)
        if (Schema::hasTable('journal_entries') && ! $this->hasIndex('journal_entries', 'idx_je_coa_date')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->index(['coa_id', 'journal_date'], 'idx_je_coa_date');
            });
        }

        if (Schema::hasTable('journal_entries') && ! $this->hasIndex('journal_entries', 'idx_je_party')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->index(['party_id'], 'idx_je_party');
            });
        }

        // budgets: approval queue
        if (Schema::hasTable('budgets') && ! $this->hasIndex('budgets', 'idx_budgets_status')) {
            Schema::table('budgets', function (Blueprint $table) {
                $table->index(['institute_id', 'status'], 'idx_budgets_status');
            });
        }

        // fx_revaluations: already has unique, add institute filter index
        if (Schema::hasTable('fx_revaluations') && ! $this->hasIndex('fx_revaluations', 'idx_fxr_institute')) {
            Schema::table('fx_revaluations', function (Blueprint $table) {
                $table->index(['institute_id', 'currency_id'], 'idx_fxr_institute');
            });
        }
    }

    public function down(): void
    {
        $drops = [
            'fiscal_years' => ['idx_fy_current'],
            'accounting_periods' => ['idx_periods_fiscal_status'],
            'parties' => ['idx_parties_scope'],
            'chart_of_accounts' => ['idx_coa_scope_type'],
            'journal_entries' => ['idx_je_coa_date', 'idx_je_party'],
            'budgets' => ['idx_budgets_status'],
            'fx_revaluations' => ['idx_fxr_institute'],
        ];

        foreach ($drops as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($indexes as $index) {
                if ($this->hasIndex($table, $index)) {
                    try {
                        Schema::table($table, function (Blueprint $table) use ($index) {
                            $table->dropIndex($index);
                        });
                    } catch (\Throwable $e) {
                        // best-effort rollback
                    }
                }
            }
        }
    }
};
