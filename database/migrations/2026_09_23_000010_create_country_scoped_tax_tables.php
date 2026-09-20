<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. tax_deduction_rules — master, unique (country_code, code)
        Schema::create('tax_deduction_rules', function (Blueprint $table) {
            $table->id();
            $table->char('country_code', 2);
            $table->char('currency_code', 3);
            $table->string('code', 50);
            $table->string('name', 150);
            $table->string('description')->nullable();
            $table->string('category', 50)->default('tds');
            $table->decimal('rate', 6, 2)->default(0);
            $table->decimal('threshold', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['country_code', 'code']);
            $table->index('is_active');
        });

        // 2. tds_deductions — per-transaction with country+currency snapshot
        Schema::create('tds_deductions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('rule_id')->nullable();
            $table->char('country_code', 2);
            $table->char('currency_code', 3);
            $table->string('reference_no', 50)->unique();
            $table->string('type', 50);
            $table->string('payee_name', 200);
            $table->string('payee_tin', 50)->nullable();
            $table->decimal('gross_amount', 15, 2)->default(0);
            $table->decimal('tax_rate', 6, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->date('deduction_date');
            $table->date('deposit_date')->nullable();
            $table->string('deposit_challan_no', 100)->nullable();
            $table->enum('status', ['pending', 'deposited', 'certificate_issued'])->default('pending');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('rule_id')->references('id')->on('tax_deduction_rules')->nullOnDelete();
            $table->index(['institute_id', 'status']);
            $table->index(['institute_id', 'type']);
            $table->index(['institute_id', 'deduction_date']);
        });

        // 3. tds_certificates
        Schema::create('tds_certificates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('deduction_id');
            $table->char('country_code', 2);
            $table->string('certificate_no', 50)->unique();
            $table->string('financial_year', 20);
            $table->date('issue_date');
            $table->enum('status', ['draft', 'issued', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('deduction_id')->references('id')->on('tds_deductions')->onDelete('cascade');
            $table->index(['institute_id', 'financial_year']);
        });

        // 4. advance_tax_payments
        Schema::create('advance_tax_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->char('country_code', 2);
            $table->char('currency_code', 3);
            $table->string('reference_no', 50)->unique();
            $table->string('financial_year', 20);
            $table->enum('quarter', ['Q1', 'Q2', 'Q3', 'Q4']);
            $table->decimal('estimated_income', 15, 2)->default(0);
            $table->decimal('tax_rate', 6, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->date('due_date');
            $table->date('payment_date')->nullable();
            $table->string('challan_no', 100)->nullable();
            $table->enum('status', ['due', 'paid', 'overdue'])->default('due');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->index(['institute_id', 'financial_year']);
            $table->index(['institute_id', 'status']);
        });

        // 5. corporate_tax_computations
        Schema::create('corporate_tax_computations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->char('country_code', 2);
            $table->char('currency_code', 3);
            $table->string('reference_no', 50)->unique();
            $table->string('financial_year', 20);
            $table->string('entity_type', 50)->default('private_limited');
            $table->decimal('total_income', 15, 2)->default(0);
            $table->decimal('deductions', 15, 2)->default(0);
            $table->decimal('taxable_income', 15, 2)->default(0);
            $table->decimal('tax_rate', 6, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('minimum_tax', 15, 2)->default(0);
            $table->decimal('final_tax', 15, 2)->default(0);
            $table->decimal('advance_tax_paid', 15, 2)->default(0);
            $table->decimal('tax_payable', 15, 2)->default(0);
            $table->enum('status', ['draft', 'filed', 'paid'])->default('draft');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->index(['institute_id', 'financial_year']);
            $table->index(['institute_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corporate_tax_computations');
        Schema::dropIfExists('advance_tax_payments');
        Schema::dropIfExists('tds_certificates');
        Schema::dropIfExists('tds_deductions');
        Schema::dropIfExists('tax_deduction_rules');
    }
};
