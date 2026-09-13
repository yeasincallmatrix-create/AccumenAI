<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global Accounting Engine — Step 1: bridge the legacy finance tables.
 *
 * Adds accounting-engine columns to the pre-existing legacy tables so they can
 * be driven by the new posting engine:
 *
 *   invoices        + party_id / currency_id / tax_group_id / journal_id / invoice_meta
 *   invoice_items   + coa_id / tax_group_id
 *   payments        + party_id / payment_method_id / journal_id
 *   cash_memos      + party_id / journal_id
 *
 * Every addition is guarded with hasColumn so this migration is a no-op on
 * schemas where a column already exists. The legacy `status` enums are left
 * untouched (no enum redefinition).
 */
return new class extends Migration
{
    private function alterInvoices(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'party_id')) {
                $table->foreignId('party_id')->nullable()->after('student_id')->constrained('parties')->nullOnDelete();
            }
            if (! Schema::hasColumn('invoices', 'currency_id')) {
                $table->foreignId('currency_id')->nullable()->after('due_date')->constrained('currencies')->nullOnDelete();
            }
            if (! Schema::hasColumn('invoices', 'tax_group_id')) {
                $table->foreignId('tax_group_id')->nullable()->after('currency_id')->constrained('tax_groups')->nullOnDelete();
            }
            if (! Schema::hasColumn('invoices', 'journal_id')) {
                $table->foreignId('journal_id')->nullable()->after('tax_group_id')->constrained('journals')->nullOnDelete();
            }
            if (! Schema::hasColumn('invoices', 'invoice_meta')) {
                $table->json('invoice_meta')->nullable()->after('journal_id');
            }
            if (! Schema::hasIndex('invoices', 'idx_invoices_party')) {
                $table->index('party_id', 'idx_invoices_party');
            }
        });
    }

    private function alterInvoiceItems(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_items', 'coa_id')) {
                $table->foreignId('coa_id')->nullable()->after('amount')->constrained('chart_of_accounts')->nullOnDelete();
            }
            if (! Schema::hasColumn('invoice_items', 'tax_group_id')) {
                $table->foreignId('tax_group_id')->nullable()->after('coa_id')->constrained('tax_groups')->nullOnDelete();
            }
            if (! Schema::hasIndex('invoice_items', 'idx_invoice_items_coa')) {
                $table->index('coa_id', 'idx_invoice_items_coa');
            }
        });
    }

    private function alterPayments(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'party_id')) {
                $table->foreignId('party_id')->nullable()->after('invoice_id')->constrained('parties')->nullOnDelete();
            }
            if (! Schema::hasColumn('payments', 'payment_method_id')) {
                $table->foreignId('payment_method_id')->nullable()->after('payment_method')->constrained('payment_methods')->nullOnDelete();
            }
            if (! Schema::hasColumn('payments', 'journal_id')) {
                $table->foreignId('journal_id')->nullable()->after('payment_method_id')->constrained('journals')->nullOnDelete();
            }
            if (! Schema::hasIndex('payments', 'idx_payments_party')) {
                $table->index('party_id', 'idx_payments_party');
            }
        });
    }

    private function alterCashMemos(): void
    {
        Schema::table('cash_memos', function (Blueprint $table) {
            if (! Schema::hasColumn('cash_memos', 'party_id')) {
                $table->foreignId('party_id')->nullable()->after('student_id')->constrained('parties')->nullOnDelete();
            }
            if (! Schema::hasColumn('cash_memos', 'journal_id')) {
                $table->foreignId('journal_id')->nullable()->after('party_id')->constrained('journals')->nullOnDelete();
            }
            if (! Schema::hasIndex('cash_memos', 'idx_cash_memos_party')) {
                $table->index('party_id', 'idx_cash_memos_party');
            }
        });
    }

    public function up(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        $this->alterInvoices();
        $this->alterInvoiceItems();
        $this->alterPayments();
        $this->alterCashMemos();
    }

    public function down(): void
    {
        $dropColumnIfExists = static function (string $table, array $columns): void {
            Schema::table($table, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    if (Schema::hasColumn($table->getTable(), $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        };

        if (Schema::hasTable('cash_memos')) {
            $dropColumnIfExists('cash_memos', ['journal_id', 'party_id']);
        }
        if (Schema::hasTable('payments')) {
            $dropColumnIfExists('payments', ['journal_id', 'payment_method_id', 'party_id']);
        }
        if (Schema::hasTable('invoice_items')) {
            $dropColumnIfExists('invoice_items', ['tax_group_id', 'coa_id']);
        }
        if (Schema::hasTable('invoices')) {
            $dropColumnIfExists('invoices', ['invoice_meta', 'journal_id', 'tax_group_id', 'currency_id', 'party_id']);
        }
    }
};
