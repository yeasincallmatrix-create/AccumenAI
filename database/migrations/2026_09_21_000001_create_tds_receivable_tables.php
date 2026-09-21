<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tds_receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
            $table->char('country_code', 2)->default('BD');
            $table->foreignId('party_id')->constrained('parties')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable();
            $table->string('reference_no', 50)->nullable();
            $table->decimal('gross_amount', 15, 2);
            $table->decimal('rate_percent', 5, 2);
            $table->decimal('tds_amount', 15, 2);
            $table->decimal('net_amount', 15, 2);
            $table->char('currency_code', 3)->default('BDT');
            $table->date('deduction_date');
            $table->string('tax_period', 20);
            $table->string('financial_year', 20);
            $table->enum('status', ['pending_certificate', 'certified', 'reconciled'])->default('pending_certificate');
            $table->foreignId('certificate_id')->nullable();
            $table->foreignId('journal_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();

            $table->index(['institute_id', 'financial_year', 'status'], 'tds_recv_fy_status_idx');
            $table->index(['institute_id', 'party_id'], 'tds_recv_party_idx');
        });

        Schema::create('tds_certificates_received', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
            $table->char('country_code', 2)->default('BD');
            $table->foreignId('party_id')->constrained('parties')->cascadeOnDelete();
            $table->string('certificate_no', 50);
            $table->date('certificate_date');
            $table->string('tax_period', 20);
            $table->string('financial_year', 20);
            $table->decimal('total_base', 15, 2);
            $table->decimal('total_tds', 15, 2);
            $table->char('currency_code', 3)->default('BDT');
            $table->string('attachment_path', 500)->nullable();
            $table->enum('status', ['received', 'verified', 'disputed'])->default('received');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();

            $table->unique(['institute_id', 'party_id', 'certificate_no', 'financial_year'], 'tds_cert_recv_unique');
            $table->index(['institute_id', 'financial_year', 'status'], 'tds_cert_fy_status_idx');
        });

        Schema::create('tax_return_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
            $table->char('country_code', 2)->default('BD');
            $table->string('financial_year', 20);
            $table->decimal('tds_payable_total', 15, 2)->default(0);
            $table->decimal('tds_receivable_total', 15, 2)->default(0);
            $table->decimal('advance_tax_paid', 15, 2)->default(0);
            $table->decimal('corporate_tax_payable', 15, 2)->default(0);
            $table->decimal('total_tax_liability', 15, 2)->default(0);
            $table->decimal('total_credits', 15, 2)->default(0);
            $table->decimal('net_payable', 15, 2)->default(0);
            $table->char('currency_code', 3)->default('BDT');
            $table->enum('status', ['draft', 'computed', 'filed', 'settled'])->default('draft');
            $table->date('filing_date')->nullable();
            $table->string('acknowledgment_no', 50)->nullable();
            $table->json('breakdown')->nullable();
            $table->foreignId('journal_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();

            $table->unique(['institute_id', 'financial_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_return_reconciliations');
        Schema::dropIfExists('tds_certificates_received');
        Schema::dropIfExists('tds_receivables');
    }
};
