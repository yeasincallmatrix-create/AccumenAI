<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tax_rules')) {
            return;
        }

        Schema::create('tax_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('jurisdiction_id')->nullable();
            $table->unsignedBigInteger('tax_rate_id');
            $table->string('item_type')->default('*');
            $table->string('product_category')->default('*');
            $table->unsignedBigInteger('tax_group_id')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('tax_rules', function (Blueprint $table) {
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('restrict');
            $table->foreign('tax_rate_id')->references('id')->on('tax_rates')->onDelete('restrict');
            $table->foreign('tax_group_id')->references('id')->on('tax_groups')->onDelete('restrict');
            $table->foreign('jurisdiction_id')->references('id')->on('tax_jurisdictions')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rules');
    }
};
