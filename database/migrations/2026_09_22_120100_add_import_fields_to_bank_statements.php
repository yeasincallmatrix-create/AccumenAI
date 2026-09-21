<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_statements', function (Blueprint $table) {
            if (!Schema::hasColumn('bank_statements', 'import_hash')) {
                $table->string('import_hash', 64)->nullable()->after('id');
                $table->index(['institute_id', 'import_hash'], 'idx_statement_import_hash');
            }
            if (!Schema::hasColumn('bank_statements', 'import_source')) {
                $table->string('import_source', 20)->nullable()->after('import_hash');
            }
            if (!Schema::hasColumn('bank_statements', 'original_filename')) {
                $table->string('original_filename', 255)->nullable()->after('import_source');
            }
            if (!Schema::hasColumn('bank_statements', 'imported_at')) {
                $table->timestamp('imported_at')->nullable()->after('original_filename');
            }
            if (!Schema::hasColumn('bank_statements', 'opening_balance')) {
                $table->decimal('opening_balance', 19, 4)->nullable()->after('statement_date');
            }
            if (!Schema::hasColumn('bank_statements', 'closing_balance')) {
                $table->decimal('closing_balance', 19, 4)->nullable()->after('opening_balance');
            }
        });

        Schema::table('bank_statement_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('bank_statement_lines', 'rule_id')) {
                $table->unsignedBigInteger('rule_id')->nullable()->after('id');
                $table->foreign('rule_id')->references('id')->on('bank_rules')->onDelete('set null');
            }
            if (!Schema::hasColumn('bank_statement_lines', 'match_confidence')) {
                $table->integer('match_confidence')->nullable()->after('rule_id');
            }
            if (!Schema::hasColumn('bank_statement_lines', 'matched_je_id')) {
                $table->unsignedBigInteger('matched_je_id')->nullable()->after('match_confidence');
                $table->foreign('matched_je_id')->references('id')->on('journals')->onDelete('set null');
            }
            if (!Schema::hasColumn('bank_statement_lines', 'categorized_account_id')) {
                $table->unsignedBigInteger('categorized_account_id')->nullable()->after('matched_je_id');
                $table->foreign('categorized_account_id')->references('id')->on('chart_of_accounts')->onDelete('set null');
            }
            if (!Schema::hasColumn('bank_statement_lines', 'category_status')) {
                $table->string('category_status', 20)->default('unmatched')->after('categorized_account_id');
            }
            if (!Schema::hasColumn('bank_statement_lines', 'matched_at')) {
                $table->timestamp('matched_at')->nullable()->after('category_status');
            }
            if (!Schema::hasColumn('bank_statement_lines', 'counterparty')) {
                $table->string('counterparty', 255)->nullable()->after('reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bank_statements', function (Blueprint $table) {
            foreach (['import_hash', 'import_source', 'original_filename', 'imported_at', 'opening_balance', 'closing_balance'] as $col) {
                if (Schema::hasColumn('bank_statements', $col)) {
                    if ($col === 'import_hash') {
                        try { $table->dropIndex('idx_statement_import_hash'); } catch (\Throwable $e) {}
                    }
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('bank_statement_lines', function (Blueprint $table) {
            foreach (['rule_id', 'matched_je_id', 'categorized_account_id'] as $col) {
                if (Schema::hasColumn('bank_statement_lines', $col)) {
                    try { $table->dropForeign([$col]); } catch (\Throwable $e) {}
                }
            }
            foreach (['rule_id', 'match_confidence', 'matched_je_id', 'categorized_account_id', 'category_status', 'matched_at', 'counterparty'] as $col) {
                if (Schema::hasColumn('bank_statement_lines', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
