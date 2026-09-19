<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fixed_assets')) {
            return;
        }

        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->unsignedBigInteger('vendor_party_id')->nullable();
            $table->string('asset_code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->date('purchase_date')->nullable();
            $table->date('capitalization_date')->nullable();
            $table->string('purchase_document_no')->nullable();
            $table->string('invoice_reference')->nullable();
            $table->decimal('acquisition_cost', 19, 4)->default(0);
            $table->decimal('additional_capitalized_cost', 19, 4)->default(0);
            $table->decimal('residual_value', 19, 4)->default(0);
            $table->unsignedSmallInteger('useful_life_months')->nullable();
            $table->string('depreciation_method')->default('straight_line');
            $table->string('depreciation_frequency')->default('monthly');
            $table->string('depreciation_convention')->default('full_month');
            $table->decimal('depreciation_rate', 10, 4)->nullable();
            $table->date('depreciation_start_date')->nullable();
            $table->decimal('accumulated_depreciation', 19, 4)->default(0);
            $table->decimal('impairment_amount', 19, 4)->default(0);
            $table->boolean('is_depreciable')->default(true);
            $table->string('unit_of_measure')->nullable();
            $table->decimal('total_units', 19, 4)->nullable();
            $table->string('status')->default('draft');
            $table->string('department')->nullable();
            $table->string('responsible_person')->nullable();
            $table->string('warranty_provider')->nullable();
            $table->date('warranty_start')->nullable();
            $table->date('warranty_end')->nullable();
            $table->string('warranty_reference')->nullable();
            $table->text('warranty_notes')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('asset_categories')->onDelete('restrict');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('restrict');
            $table->foreign('vendor_party_id')->references('id')->on('parties')->onDelete('restrict');
            $table->foreign('location_id')->references('id')->on('asset_locations')->onDelete('restrict');
            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
