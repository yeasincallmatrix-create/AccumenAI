<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blood_donors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('donor_number', 50);

            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other']);
            $table->enum('blood_group', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']);
            $table->decimal('weight_kg', 5, 1)->nullable();
            $table->decimal('hemoglobin', 4, 1)->nullable();
            $table->text('medical_history')->nullable();
            $table->boolean('is_eligible')->default(true);
            $table->timestamp('last_donation_date')->nullable();

            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->index(['institute_id', 'status']);
            $table->index(['institute_id', 'blood_group']);
            $table->unique(['institute_id', 'donor_number'], 'uniq_blood_donor_number');
        });

        Schema::create('blood_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('unit_number', 50);
            $table->unsignedBigInteger('donor_id')->nullable();
            $table->enum('blood_group', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']);
            $table->string('component', 30);
            $table->integer('volume_ml')->default(0);
            $table->timestamp('collection_date')->nullable();
            $table->timestamp('expiry_date')->nullable();

            $table->string('status', 20)->default('available');
            $table->boolean('crossmatch_required')->default(true);
            $table->string('screening_hiv', 10)->default('pending');
            $table->string('screening_hbsag', 10)->default('pending');
            $table->string('screening_hcv', 10)->default('pending');
            $table->string('screening_syphilis', 10)->default('pending');
            $table->string('screening_malaria', 10)->default('pending');

            $table->unsignedBigInteger('current_patient_id')->nullable();
            $table->timestamp('reserved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('donor_id')->references('id')->on('blood_donors')->onDelete('set null');
            $table->foreign('current_patient_id')->references('id')->on('patients')->onDelete('set null');
            $table->index(['institute_id', 'status']);
            $table->index(['institute_id', 'blood_group', 'status']);
            $table->index(['institute_id', 'component', 'status']);
            $table->index('expiry_date');
            $table->unique(['institute_id', 'unit_number'], 'uniq_blood_unit_number');
        });

        Schema::create('blood_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('request_number', 50);
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('doctor_id')->nullable();

            $table->enum('blood_group', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']);
            $table->string('component', 30);
            $table->integer('units_requested')->default(1);
            $table->integer('units_issued')->default(0);
            $table->string('urgency', 20)->default('routine');
            $table->string('status', 20)->default('pending');
            $table->text('clinical_indication')->nullable();
            $table->text('diagnosis')->nullable();

            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('doctor_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['institute_id', 'status']);
            $table->index(['institute_id', 'blood_group', 'status']);
            $table->index('patient_id');
            $table->unique(['institute_id', 'request_number'], 'uniq_blood_request_number');
        });

        Schema::create('blood_issue_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('blood_request_id');
            $table->unsignedBigInteger('blood_unit_id');
            $table->unsignedBigInteger('issued_by')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->text('return_reason')->nullable();
            $table->string('status', 20)->default('issued');
            $table->timestamps();

            $table->foreign('blood_request_id')->references('id')->on('blood_requests')->onDelete('cascade');
            $table->foreign('blood_unit_id')->references('id')->on('blood_units')->onDelete('cascade');
            $table->foreign('issued_by')->references('id')->on('users')->onDelete('set null');
            $table->index('blood_request_id');
            $table->index('blood_unit_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blood_issue_items');
        Schema::dropIfExists('blood_requests');
        Schema::dropIfExists('blood_units');
        Schema::dropIfExists('blood_donors');
    }
};
