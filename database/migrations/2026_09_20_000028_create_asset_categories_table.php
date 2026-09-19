<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asset_categories')) {
            return;
        }

        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('name');
            $table->string('code')->nullable();
            $table->unsignedBigInteger('asset_account_id')->nullable();
            $table->unsignedBigInteger('accumulated_depreciation_account_id')->nullable();
            $table->unsignedBigInteger('depreciation_expense_account_id')->nullable();
            $table->unsignedBigInteger('disposal_gain_account_id')->nullable();
            $table->unsignedBigInteger('disposal_loss_account_id')->nullable();
            $table->unsignedBigInteger('impairment_account_id')->nullable();
            $table->unsignedSmallInteger('default_useful_life_months')->nullable();
            $table->string('default_depreciation_method')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('asset_categories', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('restrict');
            $table->foreign('impairment_account_id')->references('id')->on('chart_of_accounts')->onDelete('restrict');
            $table->foreign('asset_account_id')->references('id')->on('chart_of_accounts')->onDelete('restrict');
            $table->foreign('disposal_loss_account_id')->references('id')->on('chart_of_accounts')->onDelete('restrict');
            $table->foreign('accumulated_depreciation_account_id')->references('id')->on('chart_of_accounts')->onDelete('restrict');
            $table->foreign('disposal_gain_account_id')->references('id')->on('chart_of_accounts')->onDelete('restrict');
            $table->foreign('depreciation_expense_account_id')->references('id')->on('chart_of_accounts')->onDelete('restrict');
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_categories');
    }
};
