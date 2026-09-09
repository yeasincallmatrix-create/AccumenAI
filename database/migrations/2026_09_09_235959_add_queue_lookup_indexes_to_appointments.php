<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Composite indexes for the Live Queue hot paths (all columns verified
     * to exist on appointments — there is no checked_in_at column):
     *
     * - loadQueue / queue status: institute + doctor + date + status.
     * - per-patient fee lookup (Doctor::lastCompletedVisitFor): institute
     *   + patient + doctor + status (+ date for the latest() ordering).
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (! Schema::hasIndex('appointments', 'appointments_queue_lookup_index')) {
                $table->index(
                    ['institute_id', 'doctor_id', 'appointment_date', 'status'],
                    'appointments_queue_lookup_index'
                );
            }
            if (! Schema::hasIndex('appointments', 'appointments_patient_history_index')) {
                $table->index(
                    ['institute_id', 'patient_id', 'doctor_id', 'status', 'appointment_date'],
                    'appointments_patient_history_index'
                );
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndexIfExists('appointments_queue_lookup_index');
            $table->dropIndexIfExists('appointments_patient_history_index');
        });
    }
};
