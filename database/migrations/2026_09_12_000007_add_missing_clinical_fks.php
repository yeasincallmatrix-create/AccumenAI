<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 05 — Missing FK coverage for clinical/audit tables.
 *
 * Adds only constraints proven orphan-free on both monetix_test and
 * accumen_ai (0 orphans on every rule below):
 *
 * - patient_allergies.institute_id → institutes (CASCADE, like every other
 *   HMS institute FK). Column is NOT NULL; parent type matches
 *   (bigint unsigned).
 * - patient_allergies.medicine_id → medicines (NULL ON DELETE, nullable
 *   column — allergy history survives catalog cleanup).
 * - prescription_audit_logs.institute_id → institutes (CASCADE).
 *
 * Deliberately NOT added (documented, mixed-guard actor columns whose ids
 * may come from either `users` or `institute_users` — no single parent):
 * prescription_audit_logs.user_id, queue_audit_logs.user_id,
 * prescriptions.signed_by. Attribution there survives via the denormalized
 * actor_name snapshot instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_allergies', function (Blueprint $table) {
            $table->foreign('institute_id', 'patient_allergies_institute_id_foreign')
                ->references('id')->on('institutes')->cascadeOnDelete();
            $table->foreign('medicine_id', 'patient_allergies_medicine_id_foreign')
                ->references('id')->on('medicines')->nullOnDelete();
        });

        Schema::table('prescription_audit_logs', function (Blueprint $table) {
            $table->foreign('institute_id', 'prescription_audit_logs_institute_id_foreign')
                ->references('id')->on('institutes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prescription_audit_logs', function (Blueprint $table) {
            $table->dropForeign('prescription_audit_logs_institute_id_foreign');
        });

        Schema::table('patient_allergies', function (Blueprint $table) {
            $table->dropForeign(['medicine_id']);
            $table->dropForeign(['institute_id']);
        });
    }
};
