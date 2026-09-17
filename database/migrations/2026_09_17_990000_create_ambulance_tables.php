<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambulances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('vehicle_number', 50);
            $table->string('registration_number', 50)->nullable();
            $table->string('make', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->integer('year')->nullable();
            $table->string('type', 30);
            $table->string('fuel_type', 30)->nullable();
            $table->integer('capacity_patients')->default(1);
            $table->integer('capacity_attendants')->default(2);
            $table->json('equipment')->nullable();
            $table->string('status', 30)->default('available');
            $table->date('last_service_date')->nullable();
            $table->date('next_service_date')->nullable();
            $table->date('insurance_expiry')->nullable();
            $table->date('fitness_expiry')->nullable();
            $table->decimal('odometer_km', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->index(['institute_id', 'status']);
            $table->index(['institute_id', 'is_active']);
            $table->unique(['institute_id', 'vehicle_number'], 'uniq_ambulance_vehicle_number');
        });

        Schema::create('ambulance_drivers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('driver_number', 50);
            $table->string('name', 200);
            $table->string('phone', 20);
            $table->string('email', 150)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 10)->nullable();
            $table->text('address')->nullable();
            $table->string('license_number', 50)->nullable();
            $table->string('license_type', 30)->nullable();
            $table->date('license_expiry')->nullable();
            $table->string('employee_type', 30)->default('full_time');
            $table->date('joined_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->date('medical_fitness_expiry')->nullable();
            $table->text('emergency_contact')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['institute_id', 'status']);
            $table->unique(['institute_id', 'driver_number'], 'uniq_ambulance_driver_number');
        });

        Schema::create('ambulance_trips', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('trip_number', 50);
            $table->unsignedBigInteger('ambulance_id')->nullable();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->unsignedBigInteger('attendant_id')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('emergency_visit_id')->nullable();
            $table->unsignedBigInteger('admission_id')->nullable();
            $table->string('trip_type', 30);
            $table->string('pickup_location');
            $table->text('pickup_address')->nullable();
            $table->decimal('pickup_lat', 10, 7)->nullable();
            $table->decimal('pickup_lng', 10, 7)->nullable();
            $table->string('dropoff_location');
            $table->text('dropoff_address')->nullable();
            $table->decimal('dropoff_lat', 10, 7)->nullable();
            $table->decimal('dropoff_lng', 10, 7)->nullable();
            $table->text('patient_condition_at_pickup')->nullable();
            $table->json('vitals_at_pickup')->nullable();
            $table->text('treatment_en_route')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('arrived_at_pickup')->nullable();
            $table->timestamp('departed_pickup')->nullable();
            $table->timestamp('arrived_at_dropoff')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('odometer_start_km', 10, 2)->nullable();
            $table->decimal('odometer_end_km', 10, 2)->nullable();
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->string('status', 30)->default('requested');
            $table->string('priority', 20)->default('routine');
            $table->decimal('base_fee', 10, 2)->default(0);
            $table->decimal('distance_fee', 10, 2)->default(0);
            $table->decimal('waiting_fee', 10, 2)->default(0);
            $table->decimal('total_fee', 10, 2)->default(0);
            $table->string('payment_status', 20)->default('pending');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('driver_notes')->nullable();
            $table->text('dispatch_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('ambulance_id')->references('id')->on('ambulances')->onDelete('restrict');
            $table->foreign('driver_id')->references('id')->on('ambulance_drivers')->onDelete('set null');
            $table->foreign('attendant_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('set null');
            $table->index(['institute_id', 'status']);
            $table->index(['ambulance_id', 'status']);
            $table->index(['driver_id', 'status']);
            $table->index(['patient_id', 'requested_at']);
            $table->unique(['institute_id', 'trip_number'], 'uniq_ambulance_trip_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambulance_trips');
        Schema::dropIfExists('ambulance_drivers');
        Schema::dropIfExists('ambulances');
    }
};
