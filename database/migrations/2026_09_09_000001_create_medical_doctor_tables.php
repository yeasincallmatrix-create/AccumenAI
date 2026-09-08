<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_departments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->index('institute_id');
        });

        Schema::create('medical_specialties', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('department_id');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('department_id')->references('id')->on('medical_departments')->onDelete('cascade');
            $table->index('institute_id');
            $table->index('department_id');
        });

        Schema::create('medical_doctors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('specialty_id');
            $table->string('registration_number', 50)->unique();
            $table->text('qualification')->nullable();
            $table->integer('experience_years')->default(0);
            $table->decimal('consultation_fee', 15, 2)->default(0);
            $table->text('chamber_address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->text('bio')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('specialty_id')->references('id')->on('medical_specialties')->onDelete('cascade');
            $table->index('institute_id');
            $table->index('user_id');
            $table->index('specialty_id');
            $table->index('registration_number');
        });

        Schema::create('doctor_availabilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctor_id');
            $table->enum('day_of_week', ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']);
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('slot_duration')->default(10);
            $table->boolean('is_available')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('doctor_id')->references('id')->on('medical_doctors')->onDelete('cascade');
            $table->unique(['doctor_id', 'day_of_week', 'start_time'], 'unique_doctor_day_time');
            $table->index('doctor_id');
            $table->index('day_of_week');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_availabilities');
        Schema::dropIfExists('medical_doctors');
        Schema::dropIfExists('medical_specialties');
        Schema::dropIfExists('medical_departments');
    }
};
