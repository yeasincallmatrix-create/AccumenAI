<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tax_rates')) {
            return;
        }

        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('jurisdiction_id')->nullable();
            $table->unsignedBigInteger('tax_group_id')->nullable();
            $table->string('name');
            $table->enum('type', ['vat', 'sales_tax', 'withholding', 'excise', 'custom'])->default('vat');
            $table->enum('rate_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('rate', 10, 4)->default(0);
            $table->boolean('is_compound')->default(false);
            $table->boolean('is_inclusive')->default(false);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('tax_rates', function (Blueprint $table) {
            $table->foreign('tax_group_id')->references('id')->on('tax_groups')->onDelete('restrict');
            $table->foreign('jurisdiction_id')->references('id')->on('tax_jurisdictions')->onDelete('restrict');
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
