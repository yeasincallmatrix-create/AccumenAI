<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * STEP 71 — Bank Reconciliation tables.
 *
 * bank_statements: uploaded bank statement headers
 * bank_statement_lines: individual statement lines (deposits, withdrawals)
 * bank_reconciliations: matching between statement lines and journal entries
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bank_account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
            $table->date('statement_date');
            $table->string('file_name')->nullable();
            $table->enum('status', ['imported', 'reconciled', 'cancelled'])->default('imported');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['institute_id', 'bank_account_id', 'statement_date'], 'idx_bs_inst_bank_date');
        });

        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('statement_id')->constrained('bank_statements')->cascadeOnDelete();
            $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
            $table->date('transaction_date');
            $table->string('description');
            $table->string('reference')->nullable();
            $table->decimal('amount', 19, 4);
            $table->enum('type', ['deposit', 'withdrawal']);
            $table->timestamps();

            $table->index(['statement_id', 'transaction_date'], 'idx_bsl_stmt_date');
            $table->index(['institute_id', 'reference'], 'idx_bsl_inst_ref');
        });

        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('statement_line_id')->constrained('bank_statement_lines')->cascadeOnDelete();
            $table->foreignId('journal_id')->constrained('journals')->cascadeOnDelete();
            $table->enum('status', ['matched', 'unmatched', 'ignored'])->default('matched');
            $table->foreignId('matched_by')->nullable()->constrained('institute_users')->nullOnDelete();
            $table->timestamp('matched_at')->nullable();
            $table->timestamps();

            $table->index(['statement_line_id', 'status'], 'idx_br_line_status');
            $table->index(['journal_id', 'status'], 'idx_br_journal_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliations');
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_statements');
    }
};
