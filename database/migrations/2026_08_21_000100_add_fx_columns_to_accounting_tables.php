<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * STEP 19 — Multi-Currency & FX Accounting: additive schema extensions.
 *
 * Every change is guarded (hasTable/hasColumn/hasIndex), additive and
 * backward compatible: all new columns are nullable or carry safe defaults so
 * existing rows remain valid. Existing debit/credit on journal_entries stay
 * the authoritative BASE-currency amounts; foreign amounts are additive
 * metadata. No existing column is altered or dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('journal_entries', 'currency_id')) {
                $table->foreignId('currency_id')->nullable()->after('party_id')->constrained('currencies')->nullOnDelete();
            }
            if (! Schema::hasColumn('journal_entries', 'foreign_debit')) {
                $table->decimal('foreign_debit', 19, 4)->default(0)->after('journal_date');
            }
            if (! Schema::hasColumn('journal_entries', 'foreign_credit')) {
                $table->decimal('foreign_credit', 19, 4)->default(0)->after('foreign_debit');
            }
            if (! Schema::hasColumn('journal_entries', 'exchange_rate')) {
                $table->decimal('exchange_rate', 19, 8)->default(1)->after('foreign_credit');
            }
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            if (! Schema::hasIndex('journal_entries', 'idx_journal_entries_currency')) {
                $table->index(['institute_id', 'currency_id'], 'idx_journal_entries_currency');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'exchange_rate')) {
                $table->decimal('exchange_rate', 19, 8)->nullable()->after('currency_id');
            }
            if (! Schema::hasColumn('invoices', 'base_payable_amount')) {
                $table->decimal('base_payable_amount', 19, 4)->nullable()->after('exchange_rate');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'currency_id')) {
                $table->foreignId('currency_id')->nullable()->after('student_id')->constrained('currencies')->nullOnDelete();
            }
            if (! Schema::hasColumn('payments', 'exchange_rate')) {
                $table->decimal('exchange_rate', 19, 8)->nullable()->after('amount');
            }
            if (! Schema::hasColumn('payments', 'base_amount')) {
                $table->decimal('base_amount', 19, 4)->nullable()->after('exchange_rate');
            }
            if (! Schema::hasColumn('payments', 'applied_amount')) {
                $table->decimal('applied_amount', 19, 4)->nullable()->after('base_amount');
            }
        });

        Schema::table('chart_of_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('chart_of_accounts', 'currency_id')) {
                $table->foreignId('currency_id')->nullable()->after('is_system')->constrained('currencies')->nullOnDelete();
            }
        });

        Schema::table('exchange_rates', function (Blueprint $table) {
            if (! Schema::hasColumn('exchange_rates', 'source')) {
                $table->string('source', 40)->nullable()->after('rate_date');
            }
            if (! Schema::hasColumn('exchange_rates', 'buy_rate')) {
                $table->decimal('buy_rate', 19, 8)->nullable()->after('source');
            }
            if (! Schema::hasColumn('exchange_rates', 'sell_rate')) {
                $table->decimal('sell_rate', 19, 8)->nullable()->after('buy_rate');
            }
        });

        if (! Schema::hasTable('fx_revaluations')) {
            Schema::create('fx_revaluations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->restrictOnDelete();
                $table->foreignId('period_id')->nullable()->constrained('accounting_periods')->restrictOnDelete();
                $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
                $table->date('as_of_date');
                $table->decimal('closing_rate', 19, 8);
                $table->decimal('carrying_value', 19, 4)->default(0);
                $table->decimal('revalued_value', 19, 4)->default(0);
                $table->decimal('difference', 19, 4)->default(0);
                $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
                $table->enum('status', ['posted', 'reversed'])->default('posted');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->unique(
                    ['institute_id', 'branch_id', 'fiscal_year_id', 'period_id', 'currency_id', 'as_of_date'],
                    'uq_fx_revaluations_key'
                );
                $table->index(['institute_id', 'as_of_date'], 'idx_fx_revaluations_date');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_revaluations');

        Schema::table('exchange_rates', function (Blueprint $table) {
            foreach (['sell_rate', 'buy_rate', 'source'] as $column) {
                if (Schema::hasColumn('exchange_rates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('chart_of_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('chart_of_accounts', 'currency_id')) {
                $table->dropConstrainedForeignId('currency_id');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            foreach (['applied_amount', 'base_amount', 'exchange_rate'] as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    $table->dropColumn($column);
                }
            }
            if (Schema::hasColumn('payments', 'currency_id')) {
                $table->dropConstrainedForeignId('currency_id');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            foreach (['base_payable_amount', 'exchange_rate'] as $column) {
                if (Schema::hasColumn('invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            if (Schema::hasIndex('journal_entries', 'idx_journal_entries_currency')) {
                $table->dropIndex('idx_journal_entries_currency');
            }

            foreach (['exchange_rate', 'foreign_credit', 'foreign_debit'] as $column) {
                if (Schema::hasColumn('journal_entries', $column)) {
                    $table->dropColumn($column);
                }
            }
            if (Schema::hasColumn('journal_entries', 'currency_id')) {
                $table->dropConstrainedForeignId('currency_id');
            }
        });
    }
};
