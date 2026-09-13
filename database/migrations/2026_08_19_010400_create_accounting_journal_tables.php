<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global Accounting Engine — Step 1: journals & journal entries.
 *
 * Double-entry posting core.
 *
 * - journals: the document header. A journal is created as `draft`, then
 *   posted (status=posted) once its entries balance. `source` records where the
 *   write came from (app / ai / sync / migration / import) for auditability.
 * - journal_entries: the balanced lines. Every posted journal must satisfy
 *   sum(debit) = sum(credit) and no line may carry both a debit and a credit.
 *   The invariant is enforced by JournalPostingService (no DB CHECK on MariaDB
 *   for portability). journal_date is denormalized from the header so ledger
 *   queries never join back to journals.
 * - Reversals create a new journal with `reversal_of` set; originals are never
 *   hard-deleted, only voided/reversed (audit trail preserved).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('journals')) {
            Schema::create('journals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->string('journal_no', 30);
                $table->date('journal_date');
                $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->restrictOnDelete();
                $table->foreignId('period_id')->nullable()->constrained('accounting_periods')->restrictOnDelete();
                $table->enum('type', ['sale', 'purchase', 'receipt', 'payment', 'journal', 'contra', 'opening', 'adjustment']);
                $table->string('ref_type', 40)->nullable();
                $table->unsignedBigInteger('ref_id')->nullable();
                $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
                $table->decimal('exchange_rate', 19, 8)->default(1);
                $table->enum('status', ['draft', 'posted', 'reversed', 'void'])->default('draft');
                $table->string('description', 500)->nullable();
                $table->unsignedBigInteger('posted_by')->nullable();
                $table->timestamp('posted_at')->nullable();
                $table->unsignedBigInteger('reversed_by')->nullable();
                $table->timestamp('reversed_at')->nullable();
                $table->foreignId('reversal_of')->nullable()->constrained('journals')->nullOnDelete();
                $table->enum('source', ['app', 'ai', 'sync', 'migration', 'import'])->default('app');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['institute_id', 'branch_id', 'journal_no'], 'uq_journals_no');
                $table->index(['institute_id', 'journal_date'], 'idx_journals_date');
                $table->index('fiscal_year_id', 'idx_journals_fy');
                $table->index(['ref_type', 'ref_id'], 'idx_journals_ref');
                $table->index('currency_id', 'idx_journals_currency');
            });
        }

        if (! Schema::hasTable('journal_entries')) {
            Schema::create('journal_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('journal_id')->constrained('journals')->cascadeOnDelete();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->foreignId('coa_id')->constrained('chart_of_accounts')->restrictOnDelete();
                $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
                $table->date('journal_date');
                $table->decimal('debit', 19, 4)->default(0);
                $table->decimal('credit', 19, 4)->default(0);
                $table->string('memo', 255)->nullable();
                $table->json('line_meta')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('journal_id', 'idx_journal_entries_journal');
                $table->index(['coa_id', 'journal_date'], 'idx_journal_entries_coa');
                $table->index('party_id', 'idx_journal_entries_party');
                $table->index(['institute_id', 'branch_id', 'journal_date'], 'idx_journal_entries_branch');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('journals');
    }
};
