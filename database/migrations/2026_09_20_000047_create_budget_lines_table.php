<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('budget_lines')) {
            return;
        }

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_version_id');
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('coa_id');
            $table->unsignedBigInteger('accounting_period_id')->nullable();
            $table->unsignedInteger('month');
            $table->decimal('amount', 19, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('budget_lines', function (Blueprint $table) {
            $table->foreign('coa_id')->references('id')->on('chart_of_accounts')->onDelete('restrict');
            $table->foreign('budget_version_id')->references('id')->on('budget_versions')->onDelete('restrict');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('restrict');
            $table->foreign('accounting_period_id')->references('id')->on('accounting_periods')->onDelete('restrict');
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_lines');
    }
};
