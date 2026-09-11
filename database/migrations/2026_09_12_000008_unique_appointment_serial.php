<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 05 — Appointment serial integrity (Step 13).
 *
 * QueueManager::getNextSerial() allocates max(serial)+1 per
 * (institute, doctor, date) with no locking, so two concurrent bookings for
 * the same doctor/day can silently receive the same serial and corrupt queue
 * order. This constraint converts that silent corruption into a visible
 * uniqueness failure (duplicate check on both monetix_test and accumen_ai:
 * 0 duplicates, so no existing row blocks it).
 *
 * Legitimate flows are unaffected: cancelled rows keep their serials while
 * new bookings take max+1 (never reused); transfers regenerate; NULL
 * serials never conflict in MySQL. A booking retry on 23000 is a possible
 * follow-up — deliberately not added here to avoid touching booking logic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->unique(
                ['institute_id', 'doctor_id', 'appointment_date', 'serial_number'],
                'uq_appointments_doctor_day_serial'
            );
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique('uq_appointments_doctor_day_serial');
        });
    }
};
