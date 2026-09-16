<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radiology_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('order_number', 50);
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->unsignedBigInteger('appointment_id')->nullable();

            // Study details
            $table->string('modality', 30);
            $table->string('body_part', 100);
            $table->string('laterality', 20)->nullable();
            $table->text('clinical_indication')->nullable();
            $table->boolean('is_contrast')->default(false);
            $table->string('contrast_type', 100)->nullable();
            $table->boolean('is_urgent')->default(false);
            $table->boolean('is_fasting_required')->default(false);

            // Scheduling
            $table->string('status', 20)->default('ordered');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('performed_at')->nullable();
            $table->unsignedBigInteger('performed_by')->nullable();

            // Report
            $table->text('technique')->nullable();
            $table->text('findings')->nullable();
            $table->text('impression')->nullable();
            $table->text('recommendations')->nullable();
            $table->string('radiologist_name', 200)->nullable();
            $table->unsignedBigInteger('radiologist_id')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();

            // Billing
            $table->decimal('fee', 10, 2)->default(0);
            $table->string('payment_status', 20)->default('pending');
            $table->unsignedBigInteger('invoice_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('doctor_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['institute_id', 'status']);
            $table->index(['institute_id', 'modality', 'status']);
            $table->index(['institute_id', 'scheduled_at']);
            $table->index(['patient_id', 'performed_at']);
            $table->unique(['institute_id', 'order_number'], 'uniq_radiology_order_number');
        });

        Schema::create('radiology_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('radiology_order_id');
            $table->string('file_path');
            $table->string('thumbnail_path')->nullable();
            $table->string('original_filename', 255);
            $table->string('mime_type', 100);
            $table->integer('file_size');
            $table->string('caption', 255)->nullable();
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamps();

            $table->foreign('radiology_order_id')->references('id')->on('radiology_orders')->onDelete('cascade');
            $table->index('radiology_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radiology_images');
        Schema::dropIfExists('radiology_orders');
    }
};
