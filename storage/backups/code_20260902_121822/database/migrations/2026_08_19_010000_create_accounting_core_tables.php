<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global Accounting Engine — Step 1: core shared tables.
 *
 * - currencies: global shared catalog (no institute_id). One row is flagged
 *   is_base per installation; each institute later pins its base currency in
 *   accounting_settings.
 * - exchange_rates: institute-scoped (branch_id NULL = institute-wide rate).
 * - tax_groups: institute-scoped VAT / sales-tax / withholding rates.
 * - customer_groups: institute-scoped grouping with discount rate.
 *
 * Every tenant-owned record carries created_by/updated_by (institute_users)
 * and soft deletes. Money stays DECIMAL — never float.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('currencies')) {
            Schema::create('currencies', function (Blueprint $table) {
                $table->id();
                $table->char('code', 3)->unique('uq_currencies_code');
                $table->string('name', 100);
                $table->string('symbol', 10)->nullable();
                $table->unsignedTinyInteger('decimal_places')->default(2);
                $table->boolean('is_base')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('exchange_rates')) {
            Schema::create('exchange_rates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->foreignId('from_currency_id')->constrained('currencies');
                $table->foreignId('to_currency_id')->constrained('currencies');
                $table->decimal('rate', 19, 8);
                $table->date('rate_date');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(
                    ['institute_id', 'branch_id', 'from_currency_id', 'to_currency_id', 'rate_date'],
                    'uq_exchange_rates'
                );
                $table->index(['institute_id', 'rate_date'], 'idx_exchange_rates_dates');
            });
        }

        if (! Schema::hasTable('tax_groups')) {
            Schema::create('tax_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('name', 100);
                $table->enum('type', ['vat', 'sales_tax', 'withholding', 'custom'])->default('vat');
                $table->decimal('rate', 10, 4)->default(0);
                $table->boolean('is_compound')->default(false);
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['institute_id', 'is_active'], 'idx_tax_groups_institute');
            });
        }

        if (! Schema::hasTable('customer_groups')) {
            Schema::create('customer_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('name', 100);
                $table->decimal('discount_rate', 10, 4)->default(0);
                $table->boolean('is_system')->default(false);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['institute_id', 'branch_id', 'name'], 'uq_customer_groups_name');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_groups');
        Schema::dropIfExists('tax_groups');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('currencies');
    }
};
