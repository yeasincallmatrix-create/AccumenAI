<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global Accounting Engine — Step 1: Chart of Accounts.
 *
 * - account_groups: category buckets (asset/liability/equity/income/expense)
 *   that form the report tree. Self-referencing parent.
 * - chart_of_accounts: the working account ledger replacing account_heads.
 *   `legacy_head_id` maps to the legacy income/expense account_heads row so the
 *   old single-entry data can be backfilled later. The FK is intentionally NOT
 *   enforced at DB level (legacy tables are kept weak per design); the mapping
 *   is validated in the service layer.
 * - payment_methods: cash/bank/mobile money etc. coa_id links the default
 *   posting account (Cash in Hand / Bank) for that method.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('account_groups')) {
            Schema::create('account_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('account_groups')->nullOnDelete();
                $table->string('code', 20);
                $table->string('name', 150);
                $table->enum('category', ['asset', 'liability', 'equity', 'income', 'expense']);
                $table->boolean('is_system')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['institute_id', 'branch_id', 'code'], 'uq_account_groups_code');
            });
        }

        if (! Schema::hasTable('chart_of_accounts')) {
            Schema::create('chart_of_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->foreignId('account_group_id')->constrained('account_groups');
                $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                $table->string('code', 30);
                $table->string('name', 150);
                $table->enum('type', ['asset', 'liability', 'equity', 'income', 'expense']);
                $table->boolean('is_cash')->default(false);
                $table->boolean('is_bank')->default(false);
                $table->boolean('is_receivable')->default(false);
                $table->boolean('is_payable')->default(false);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_system')->default(false);
                $table->unsignedBigInteger('legacy_head_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['institute_id', 'branch_id', 'code'], 'uq_coa_code');
                $table->index('account_group_id', 'idx_coa_group');
                $table->index(['institute_id', 'is_active'], 'idx_coa_active');
                $table->index('legacy_head_id', 'idx_coa_legacy');
            });
        }

        if (! Schema::hasTable('payment_methods')) {
            Schema::create('payment_methods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('name', 100);
                $table->foreignId('coa_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['institute_id', 'branch_id', 'name'], 'uq_payment_methods_name');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('account_groups');
    }
};
