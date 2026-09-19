<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('statement_snapshots')) {
            return;
        }

        Schema::create('statement_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('fiscal_year_id');
            $table->unsignedBigInteger('period_id')->nullable();
            $table->enum('statement_type', ['trial_balance', 'balance_sheet', 'income_statement', 'cash_flow', 'ledger', 'receivables', 'payables']);
            $table->date('as_of_date');
            $table->longText('payload');
            $table->string('checksum');
            $table->boolean('locked')->default(true);
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();
        });

        Schema::table('statement_snapshots', function (Blueprint $table) {
            $table->foreign('period_id')->references('id')->on('accounting_periods')->onDelete('restrict');
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_years')->onDelete('restrict');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statement_snapshots');
    }
};
