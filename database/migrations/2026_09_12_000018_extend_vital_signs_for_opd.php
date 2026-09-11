<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extend vital_signs for OPD/consultation use: admission becomes
     * optional, appointment/patient/doctor linkage added, plus the
     * missing pain_score and heart_rate fields. Existing IPD rows are
     * untouched (0 rows at deploy time; admission_id stays filled).
     */
    public function up(): void
    {
        // MySQL has no native "drop not-null" via the schema builder
        // without doctrine/dbal — use a direct MODIFY instead.
        DB::statement('ALTER TABLE `vital_signs` MODIFY `admission_id` BIGINT UNSIGNED NULL');

        Schema::table('vital_signs', function (Blueprint $table) {
            $table->unsignedBigInteger('appointment_id')->nullable()->after('admission_id');
            $table->unsignedBigInteger('patient_id')->nullable()->after('appointment_id');
            $table->unsignedBigInteger('doctor_id')->nullable()->after('patient_id');
            $table->integer('pain_score')->nullable()->after('spo2');
            $table->integer('heart_rate')->nullable()->after('pulse');

            $table->index(['patient_id', 'recorded_at']);
            $table->index(['appointment_id']);
            $table->index(['doctor_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::table('vital_signs', function (Blueprint $table) {
            $table->dropIndex(['patient_id', 'recorded_at']);
            $table->dropIndex(['appointment_id']);
            $table->dropIndex(['doctor_id', 'recorded_at']);
            $table->dropColumn(['appointment_id', 'patient_id', 'doctor_id', 'pain_score', 'heart_rate']);
        });

        DB::statement('ALTER TABLE `vital_signs` MODIFY `admission_id` BIGINT UNSIGNED NOT NULL');
    }
};
