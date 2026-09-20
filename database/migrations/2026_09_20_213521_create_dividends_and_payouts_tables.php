<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dividends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
            $table->string('reference_no', 30)->nullable();
            $table->date('declared_date');
            $table->date('record_date')->nullable();
            $table->date('payment_date')->nullable();
            $table->string('financial_year', 20);
            $table->decimal('total_dividend', 15, 2);
            $table->decimal('per_share_amount', 15, 4);
            $table->integer('total_shares');
            $table->decimal('total_tax', 15, 2)->default(0);
            $table->decimal('total_net', 15, 2)->default(0);
            $table->enum('status', ['draft', 'declared', 'paid', 'cancelled'])->default('draft');
            $table->text('board_resolution')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('journal_id')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['institute_id', 'reference_no']);
            $table->index(['institute_id', 'financial_year']);
            $table->index(['institute_id', 'status']);
        });

        Schema::create('dividend_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dividend_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shareholder_id')->constrained()->cascadeOnDelete();
            $table->integer('shares');
            $table->decimal('gross_amount', 15, 2);
            $table->decimal('tax_rate', 5, 2)->default(10);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2);
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->date('paid_date')->nullable();
            $table->string('payment_method', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['dividend_id', 'shareholder_id']);
            $table->index(['institute_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dividend_payouts');
        Schema::dropIfExists('dividends');
    }
};
