<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global Accounting Engine — Step 1: periodic, reporting, audit & settings.
 *
 * - opening_balances: per fiscal-year opening position for a CoA account.
 * - statement_snapshots: immutable frozen official reports (trial balance,
 *   balance sheet, income statement, cash flow, ledger, receivables, payables).
 *   A snapshot is never mutated; regeneration creates a new row keyed by
 *   as_of_date + generated_at.
 * - accounting_audit_trails: append-only log of every financial write. There is
 *   no updated_at and no soft delete by design.
 * - accounting_settings: JSON key/value scoped per institute (branch_id NULL =
 *   institute-wide). Keys: base_currency, coa_template, ar_ap_mode,
 *   statement_lock, money_precision, invoice_auto_post, fiscal_year_start.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('opening_balances')) {
            Schema::create('opening_balances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
                $table->foreignId('coa_id')->constrained('chart_of_accounts')->restrictOnDelete();
                $table->decimal('debit', 19, 4)->default(0);
                $table->decimal('credit', 19, 4)->default(0);
                $table->enum('source', ['manual', 'carry_forward', 'migration'])->default('manual');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(
                    ['institute_id', 'branch_id', 'fiscal_year_id', 'coa_id'],
                    'uq_opening_balances'
                );
            });
        }

        if (! Schema::hasTable('statement_snapshots')) {
            Schema::create('statement_snapshots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
                $table->foreignId('period_id')->nullable()->constrained('accounting_periods')->nullOnDelete();
                $table->enum('statement_type', [
                    'trial_balance',
                    'balance_sheet',
                    'income_statement',
                    'cash_flow',
                    'ledger',
                    'receivables',
                    'payables',
                ]);
                $table->date('as_of_date');
                $table->json('payload');
                $table->char('checksum', 64);
                $table->boolean('locked')->default(true);
                $table->unsignedBigInteger('generated_by')->nullable();
                $table->timestamp('generated_at')->useCurrent();
                $table->timestamps();

                $table->index(
                    ['institute_id', 'branch_id', 'fiscal_year_id', 'statement_type', 'as_of_date'],
                    'idx_snapshots'
                );
            });
        }

        if (! Schema::hasTable('accounting_audit_trails')) {
            Schema::create('accounting_audit_trails', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->enum('actor_type', ['user', 'system', 'ai', 'cron', 'import'])->default('user');
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->enum('action', [
                    'create',
                    'update',
                    'delete',
                    'post',
                    'reverse',
                    'void',
                    'lock',
                    'close',
                    'reopen',
                    'import',
                    'migrate',
                    'export',
                ]);
                $table->string('entity_type', 60);
                $table->unsignedBigInteger('entity_id');
                $table->json('before_payload')->nullable();
                $table->json('after_payload')->nullable();
                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['entity_type', 'entity_id'], 'idx_audit_entity');
                $table->index(['actor_type', 'actor_id'], 'idx_audit_actor');
                $table->index(['institute_id', 'created_at'], 'idx_audit_date');
            });
        }

        if (! Schema::hasTable('accounting_settings')) {
            Schema::create('accounting_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('settings_key', 80);
                $table->json('settings_value');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->unique(
                    ['institute_id', 'branch_id', 'settings_key'],
                    'uq_accounting_settings'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_settings');
        Schema::dropIfExists('accounting_audit_trails');
        Schema::dropIfExists('statement_snapshots');
        Schema::dropIfExists('opening_balances');
    }
};
