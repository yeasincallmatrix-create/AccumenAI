<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('patient_id');

            $table->string('document_number', 50)->nullable();
            $table->string('document_type', 50);
            $table->string('title', 255);
            $table->text('description')->nullable();

            $table->string('file_path');
            $table->string('thumbnail_path')->nullable();
            $table->string('original_filename', 255);
            $table->string('mime_type', 100);
            $table->integer('file_size');
            $table->string('file_hash', 64)->nullable();
            $table->integer('page_count')->nullable();

            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->boolean('is_confidential')->default(false);
            $table->boolean('is_patient_visible')->default(false);
            $table->string('access_level', 20)->default('clinical');

            $table->json('tags')->nullable();
            $table->date('document_date')->nullable();
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['institute_id', 'patient_id', 'document_type']);
            $table->index(['institute_id', 'document_date']);
            $table->index(['source_type', 'source_id']);
            $table->unique(['institute_id', 'document_number'], 'uniq_document_number');
        });

        Schema::create('discharge_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('summary_number', 50);
            $table->unsignedBigInteger('admission_id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('prepared_by');

            $table->date('admission_date');
            $table->date('discharge_date');
            $table->integer('length_of_stay_days');

            $table->text('admission_diagnosis');
            $table->text('final_diagnosis')->nullable();
            $table->text('hospital_course');
            $table->text('procedures_done')->nullable();
            $table->text('investigations_summary')->nullable();
            $table->text('treatment_given')->nullable();

            $table->text('discharge_medications');
            $table->text('discharge_instructions');
            $table->text('diet_instructions')->nullable();
            $table->text('activity_restrictions')->nullable();
            $table->string('condition_on_discharge', 50);

            $table->date('follow_up_date')->nullable();
            $table->text('follow_up_instructions')->nullable();
            $table->string('follow_up_department', 100)->nullable();

            $table->unsignedBigInteger('document_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('admission_id')->references('id')->on('admissions')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('prepared_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('document_id')->references('id')->on('medical_documents')->onDelete('set null');

            $table->index(['institute_id', 'patient_id']);
            $table->index(['admission_id']);
            $table->unique(['institute_id', 'summary_number'], 'uniq_discharge_summary_number');
        });

        Schema::create('patient_timeline_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('patient_id');

            $table->string('event_type', 50);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->timestamp('event_at');
            $table->date('event_date');

            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('location', 100)->nullable();

            $table->json('metadata')->nullable();
            $table->string('severity', 20)->nullable();
            $table->string('icon', 50)->nullable();

            $table->timestamps();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('doctor_id')->references('id')->on('users')->onDelete('set null');

            $table->index(['institute_id', 'patient_id', 'event_at']);
            $table->index(['patient_id', 'event_type', 'event_at']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('clinical_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('note_number', 50);
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('author_id');

            $table->string('note_type', 50);
            $table->string('encounter_type', 50)->nullable();
            $table->unsignedBigInteger('encounter_id')->nullable();

            $table->text('subjective')->nullable();
            $table->text('objective')->nullable();
            $table->text('assessment')->nullable();
            $table->text('plan')->nullable();

            $table->text('content')->nullable();
            $table->text('addendum')->nullable();

            $table->timestamp('noted_at');
            $table->boolean('is_signed')->default(false);
            $table->timestamp('signed_at')->nullable();
            $table->string('signature_hash', 64)->nullable();
            $table->boolean('is_amended')->default(false);
            $table->text('amendment_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('author_id')->references('id')->on('users')->onDelete('restrict');

            $table->index(['institute_id', 'patient_id', 'note_type']);
            $table->index(['encounter_type', 'encounter_id']);
            $table->unique(['institute_id', 'note_number'], 'uniq_clinical_note_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_notes');
        Schema::dropIfExists('patient_timeline_events');
        Schema::dropIfExists('discharge_summaries');
        Schema::dropIfExists('medical_documents');
    }
};
