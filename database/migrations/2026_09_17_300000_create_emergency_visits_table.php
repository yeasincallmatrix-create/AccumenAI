<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('visit_number', 50);

            // Patient (can be unknown in emergency)
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->string('patient_name_temp', 200)->nullable();
            $table->integer('patient_age')->nullable();
            $table->string('patient_gender', 10)->nullable();
            $table->string('patient_phone', 20)->nullable();

            // Triage
            $table->string('triage_level', 20)->default('green');
            $table->timestamp('triaged_at')->nullable();
            $table->unsignedBigInteger('triaged_by')->nullable();

            // Arrival
            $table->string('arrival_mode', 30)->nullable();
            $table->string('arrival_reference', 200)->nullable();
            $table->timestamp('arrived_at')->nullable();

            // Clinical
            $table->text('chief_complaint')->nullable();
            $table->text('history_notes')->nullable();
            $table->json('vitals_snapshot')->nullable();
            $table->text('examination_findings')->nullable();
            $table->text('provisional_diagnosis')->nullable();
            $table->text('treatment_given')->nullable();

            // Attending
            $table->unsignedBigInteger('attending_doctor_id')->nullable();
            $table->timestamp('attended_at')->nullable();

            // Disposition
            $table->string('status', 20)->default('waiting');
            $table->string('disposition', 50)->nullable();
            $table->unsignedBigInteger('admission_id')->nullable();
            $table->timestamp('disposition_at')->nullable();
            $table->unsignedBigInteger('disposition_by')->nullable();
            $table->text('disposition_notes')->nullable();

            // Billing
            $table->decimal('triage_fee', 10, 2)->default(0);
            $table->decimal('total_fee', 10, 2)->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('set null');
            $table->foreign('attending_doctor_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('triaged_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('disposition_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('admission_id')->references('id')->on('admissions')->onDelete('set null');

            $table->index(['institute_id', 'status']);
            $table->index(['institute_id', 'triage_level', 'status']);
            $table->index(['institute_id', 'arrived_at']);
            $table->unique(['institute_id', 'visit_number'], 'uniq_emergency_visit_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_visits');
    }
};
