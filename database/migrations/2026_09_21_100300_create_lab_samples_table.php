<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lab_samples')) {
            return;
        }

        Schema::create('lab_samples', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();

            $table->string('accession_number', 50); // LAB-ACC-YYYY-NNNNN
            $table->string('barcode', 100)->nullable();

            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('lab_order_id')->nullable();

            $table->string('sample_type', 50)->nullable(); // blood, serum, plasma, urine, stool, swab, csf
            $table->string('container_type', 50)->nullable(); // EDTA tube, plain tube, etc.

            // Collection
            $table->timestamp('collected_at')->nullable();
            $table->unsignedBigInteger('collected_by')->nullable();
            $table->string('collection_site', 100)->nullable();

            // Status: collected, received, processing, completed, rejected, cancelled
            $table->string('status', 20)->default('collected');

            // Rejection
            $table->text('rejection_reason')->nullable();
            $table->timestamp('rejected_at')->nullable();

            // Metadata
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('lab_order_id')->references('id')->on('lab_orders')->onDelete('set null');
            $table->foreign('collected_by')->references('id')->on('users')->onDelete('set null');

            $table->unique(['institute_id', 'accession_number'], 'uniq_sample_accession');
            $table->index(['institute_id', 'status']);
            $table->index(['institute_id', 'barcode']);
            $table->index(['patient_id', 'collected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_samples');
    }
};
