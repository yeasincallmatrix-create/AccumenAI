<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // -------- VITAL SIGNS --------
        Schema::create('vital_signs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admission_id');
            $table->decimal('temperature', 4, 1)->nullable();
            $table->integer('blood_pressure_systolic')->nullable();
            $table->integer('blood_pressure_diastolic')->nullable();
            $table->integer('pulse')->nullable();
            $table->integer('respiratory_rate')->nullable();
            $table->integer('spo2')->nullable();
            $table->decimal('blood_sugar', 6, 1)->nullable();
            $table->decimal('weight', 5, 1)->nullable();
            $table->decimal('height', 5, 1)->nullable();
            // Nullable (differs from the Phase 2 draft): institute_user-guard
            // staff have no row in `users`, so recorder is null for them
            // instead of violating the FK. See MedicalScope::recorderId().
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->datetime('recorded_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('admission_id')->references('id')->on('admissions')->onDelete('cascade');
            $table->foreign('recorded_by')->references('id')->on('users')->onDelete('set null');

            $table->index('admission_id');
            $table->index('recorded_at');
        });

        // -------- NURSING NOTES --------
        Schema::create('nursing_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admission_id');
            $table->text('note');
            // Nullable for the same reason as vital_signs.recorded_by.
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->datetime('recorded_at');
            $table->timestamps();

            $table->foreign('admission_id')->references('id')->on('admissions')->onDelete('cascade');
            $table->foreign('recorded_by')->references('id')->on('users')->onDelete('set null');

            $table->index('admission_id');
            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nursing_notes');
        Schema::dropIfExists('vital_signs');
    }
};
