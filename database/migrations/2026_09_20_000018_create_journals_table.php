<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('journals')) {
            return;
        }

        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('journal_no')->unique();
            $table->date('journal_date');
            $table->unsignedBigInteger('fiscal_year_id');
            $table->unsignedBigInteger('period_id')->nullable();
            $table->enum('type', ['sale', 'purchase', 'receipt', 'payment', 'journal', 'contra', 'opening', 'adjustment']);
            $table->string('ref_type')->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->unsignedBigInteger('currency_id');
            $table->decimal('exchange_rate', 19, 8)->default(1);
            $table->enum('status', ['draft', 'posted', 'reversed', 'void'])->default('draft');
            $table->string('description')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->unsignedBigInteger('reversed_by')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->unsignedBigInteger('reversal_of')->nullable();
            $table->enum('source', ['app', 'ai', 'sync', 'migration', 'import'])->default('app');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('journals', function (Blueprint $table) {
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('restrict');
            $table->foreign('reversal_of')->references('id')->on('journals')->onDelete('restrict');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('restrict');
            $table->foreign('period_id')->references('id')->on('accounting_periods')->onDelete('restrict');
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_years')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journals');
    }
};
