<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('expenses')) {
            if (!Schema::hasColumn('expenses', 'is_billable')) {
                Schema::table('expenses', function (Blueprint $table) {
                    $table->boolean('is_billable')->default(false)->after('id');
                    $table->unsignedBigInteger('customer_id')->nullable()->after('is_billable');
                    $table->decimal('markup_percentage', 5, 2)->default(0)->after('customer_id');
                    $table->decimal('billable_amount', 15, 2)->nullable()->after('markup_percentage');
                    $table->string('billing_status', 20)->default('unbillable')->after('billable_amount');
                    $table->unsignedBigInteger('billed_invoice_id')->nullable()->after('billing_status');
                    $table->timestamp('billed_at')->nullable()->after('billed_invoice_id');
                    $table->string('expense_category', 50)->nullable()->after('billed_at');
                    $table->text('description')->nullable()->after('expense_category');
                    $table->string('vendor_name', 200)->nullable()->after('description');
                    $table->string('reference_number', 50)->nullable()->after('vendor_name');
                    $table->string('receipt_path', 255)->nullable()->after('reference_number');
                });
            }
            return;
        }

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('expense_number', 50);

            $table->unsignedBigInteger('paid_by_user_id')->nullable();
            $table->unsignedBigInteger('payment_account_id');

            $table->date('expense_date');
            $table->string('vendor_name', 200)->nullable();
            $table->string('reference_number', 50)->nullable();
            $table->string('expense_category', 50)->nullable();

            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3)->default('BDT');
            $table->decimal('exchange_rate', 15, 6)->default(1);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->unsignedBigInteger('tax_group_id')->nullable();

            $table->unsignedBigInteger('expense_account_id');
            $table->unsignedBigInteger('journal_entry_id')->nullable();

            $table->boolean('is_billable')->default(false);
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->decimal('markup_percentage', 5, 2)->default(0);
            $table->decimal('billable_amount', 15, 2)->nullable();
            $table->string('billing_status', 20)->default('unbillable');
            $table->unsignedBigInteger('billed_invoice_id')->nullable();
            $table->timestamp('billed_at')->nullable();

            $table->string('receipt_path', 255)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('paid_by_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('payment_account_id')->references('id')->on('chart_of_accounts')->onDelete('restrict');
            $table->foreign('expense_account_id')->references('id')->on('chart_of_accounts')->onDelete('restrict');
            $table->foreign('customer_id')->references('id')->on('parties')->onDelete('set null');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('set null');
            $table->foreign('tax_group_id')->references('id')->on('tax_groups')->onDelete('set null');
            $table->foreign('billed_invoice_id')->references('id')->on('invoices')->onDelete('set null');

            $table->unique(['institute_id', 'expense_number'], 'uniq_expense_number');
            $table->index(['institute_id', 'expense_date']);
            $table->index(['institute_id', 'is_billable', 'billing_status'], 'idx_billable_status');
            $table->index(['customer_id', 'billing_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
