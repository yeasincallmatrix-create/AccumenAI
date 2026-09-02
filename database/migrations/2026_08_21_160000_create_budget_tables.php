<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('fiscal_year_id');
            $table->unsignedBigInteger('currency_id');
            $table->string('name', 100);
            $table->enum('type', ['revenue', 'expense', 'cost', 'asset'])->default('expense');
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'locked'])->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->decimal('total_amount', 19, 4)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_years')->restrictOnDelete();
            $table->foreign('currency_id')->references('id')->on('currencies')->restrictOnDelete();

            $table->unique(['institute_id', 'branch_id', 'fiscal_year_id', 'type'], 'uq_budgets_type');
            $table->index('institute_id');
            $table->index('fiscal_year_id');
            $table->index('status');
        });

        Schema::create('budget_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_id');
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedInteger('version');
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'locked'])->default('draft');
            $table->decimal('total_amount', 19, 4)->default(0);
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('budget_id')->references('id')->on('budgets')->cascadeOnDelete();
            $table->foreign('institute_id')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();

            $table->unique(['budget_id', 'version'], 'uq_budget_versions_version');
            $table->index('budget_id');
            $table->index('institute_id');
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_version_id');
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('coa_id');
            $table->unsignedBigInteger('accounting_period_id')->nullable();
            $table->unsignedInteger('month')->comment('1-12 for monthly, 0 for annual total');
            $table->decimal('amount', 19, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('budget_version_id')->references('id')->on('budget_versions')->cascadeOnDelete();
            $table->foreign('institute_id')->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('coa_id')->references('id')->on('chart_of_accounts')->restrictOnDelete();
            $table->foreign('accounting_period_id')->references('id')->on('accounting_periods')->nullOnDelete();

            $table->unique(['budget_version_id', 'coa_id', 'month'], 'uq_budget_lines_account_month');
            $table->index('budget_version_id');
            $table->index('institute_id');
            $table->index('coa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('budget_versions');
        Schema::dropIfExists('budgets');
    }
};
