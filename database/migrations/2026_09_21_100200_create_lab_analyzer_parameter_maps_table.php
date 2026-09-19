<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lab_analyzer_parameter_maps')) {
            return;
        }

        Schema::create('lab_analyzer_parameter_maps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('analyzer_id');

            // Vendor side
            $table->string('vendor_code', 50); // e.g., WBC, HGB, PLT (as machine sends)
            $table->string('vendor_name', 200)->nullable();

            // Universal side
            $table->string('universal_code', 50); // WBC, HGB, PLT (platform standard)

            // Optional link to catalog
            $table->unsignedBigInteger('lab_test_id')->nullable();
            $table->string('parameter_key', 50)->nullable();

            // Unit conversion
            $table->string('unit_from', 30)->nullable();
            $table->string('unit_to', 30)->nullable();
            $table->decimal('conversion_factor', 15, 8)->default(1);

            // Reference range override (instrument-specific)
            $table->decimal('ref_low', 15, 4)->nullable();
            $table->decimal('ref_high', 15, 4)->nullable();
            $table->string('ref_range_text', 100)->nullable(); // "4.0-11.0"

            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('analyzer_id')->references('id')->on('lab_analyzers')->onDelete('cascade');
            $table->foreign('lab_test_id')->references('id')->on('lab_tests')->onDelete('set null');

            $table->unique(['analyzer_id', 'vendor_code'], 'uniq_analyzer_vendor_code');
            $table->index(['institute_id', 'universal_code']);
            $table->index(['analyzer_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_analyzer_parameter_maps');
    }
};
