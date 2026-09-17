<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaccine_masters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id')->nullable();
            $table->string('code', 30)->nullable();
            $table->string('name', 200);
            $table->string('short_name', 100)->nullable();
            $table->string('category', 50)->nullable();
            $table->text('description')->nullable();
            $table->text('protects_against')->nullable();
            $table->string('route', 30)->nullable();
            $table->string('site', 50)->nullable();
            $table->string('dose_volume', 30)->nullable();
            $table->integer('doses_in_series')->default(1);
            $table->integer('min_age_days')->nullable();
            $table->integer('max_age_days')->nullable();
            $table->integer('interval_days_min')->nullable();
            $table->decimal('default_fee', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->index(['institute_id', 'is_active']);
            $table->index(['category']);
        });

        Schema::create('vaccination_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('vaccine_master_id');
            $table->integer('dose_number');
            $table->date('due_date');
            $table->date('given_date')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->integer('age_in_days_at_due')->nullable();
            $table->text('notes')->nullable();
            $table->text('contraindication_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('vaccine_master_id')->references('id')->on('vaccine_masters')->onDelete('restrict');
            $table->index(['institute_id', 'status', 'due_date'], 'vsch_inst_status_due');
            $table->index(['patient_id', 'vaccine_master_id', 'dose_number'], 'vsch_patient_vax_dose');
            $table->unique(['institute_id', 'patient_id', 'vaccine_master_id', 'dose_number'], 'vsch_unique_schedule');
        });

        Schema::create('vaccination_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('record_number', 50);
            $table->unsignedBigInteger('vaccination_schedule_id')->nullable();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('vaccine_master_id');
            $table->integer('dose_number');

            $table->date('administered_date');
            $table->timestamp('administered_at');
            $table->unsignedBigInteger('administered_by');
            $table->string('site', 50)->nullable();
            $table->string('route', 30)->nullable();
            $table->string('dose_volume', 30)->nullable();

            $table->string('batch_number', 50)->nullable();
            $table->date('batch_expiry')->nullable();
            $table->string('manufacturer', 200)->nullable();
            $table->string('vaccine_vial_id', 50)->nullable();

            $table->boolean('consent_obtained')->default(true);
            $table->text('pre_vaccination_notes')->nullable();
            $table->text('post_vaccination_notes')->nullable();
            $table->string('adverse_event', 100)->nullable();
            $table->text('adverse_event_details')->nullable();
            $table->timestamp('observation_end_at')->nullable();

            $table->date('next_dose_due')->nullable();

            $table->decimal('fee', 10, 2)->default(0);
            $table->string('payment_status', 20)->default('pending');

            $table->string('certificate_number', 50)->nullable();
            $table->timestamp('certificate_issued_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('vaccine_master_id')->references('id')->on('vaccine_masters')->onDelete('restrict');
            $table->foreign('vaccination_schedule_id')->references('id')->on('vaccination_schedules')->onDelete('set null');
            $table->foreign('administered_by')->references('id')->on('users')->onDelete('restrict');
            $table->index(['institute_id', 'administered_date']);
            $table->index(['patient_id', 'administered_date']);
            $table->unique(['institute_id', 'record_number'], 'vrec_inst_recnum');
            $table->unique(['institute_id', 'certificate_number'], 'vrec_inst_certnum');
        });

        Schema::create('vaccine_stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('vaccine_master_id');
            $table->string('batch_number', 50);
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date');
            $table->integer('quantity_received');
            $table->integer('quantity_used')->default(0);
            $table->integer('quantity_available');
            $table->string('storage_location', 100)->nullable();
            $table->decimal('temperature_min', 5, 2)->nullable();
            $table->decimal('temperature_max', 5, 2)->nullable();
            $table->string('status', 20)->default('available');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('vaccine_master_id')->references('id')->on('vaccine_masters')->onDelete('restrict');
            $table->index(['institute_id', 'vaccine_master_id', 'status']);
            $table->index(['expiry_date']);
            $table->unique(['institute_id', 'vaccine_master_id', 'batch_number'], 'vstk_inst_vax_batch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vaccine_stocks');
        Schema::dropIfExists('vaccination_records');
        Schema::dropIfExists('vaccination_schedules');
        Schema::dropIfExists('vaccine_masters');
    }
};
