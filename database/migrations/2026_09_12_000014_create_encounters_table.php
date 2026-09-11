<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 14 — Clinical encounter foundation.
 *
 * medical_encounters links the visit (appointment or walk-in) to its
 * clinical documentation, orders and outcome. Additive only:
 * - prescriptions / lab_orders gain NULLABLE encounter_id (legacy rows and
 *   existing flows keep working with NULL; no backfill fabricates links).
 * - All FKs follow Phase 03 doctrine: tenant/patient cascade with their
 *   owners, everything else NULL ON DELETE, no destructive cascade into
 *   clinical or financial history. There is deliberately NO delete route;
 *   cancellation is a status with audit, and completed rows are immutable
 *   except through the reason-gated amend path.
 * - UNIQUE(appointment_id) permits many walk-ins (NULL) while making a
 *   second encounter for one appointment impossible at the DB level.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_encounters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->unique()->constrained('appointments')->nullOnDelete();
            // Users purge nulls authorship instead of vaporizing encounters
            // (Phase 03 users→clinical doctrine, like appointments).
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('medical_departments')->nullOnDelete();
            $table->foreignId('specialty_id')->nullable()->constrained('medical_specialties')->nullOnDelete();
            $table->foreignId('admission_id')->nullable()->constrained('admissions')->nullOnDelete();
            // ENC-YYYY-III-NNNNN (Phase 04 doctrine: global unique w/ tenant segment).
            $table->string('encounter_number', 50)->unique();
            // OPD|EMERGENCY|IPD|FOLLOW_UP|WALK_IN
            $table->string('encounter_type', 20)->default('OPD');
            // open|in_progress|completed|cancelled
            $table->string('status', 20)->default('open');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('chief_complaint')->nullable();
            $table->text('history_of_present_illness')->nullable();
            $table->text('examination_notes')->nullable();
            $table->text('assessment_notes')->nullable();
            $table->text('plan_notes')->nullable();
            $table->text('follow_up_notes')->nullable();
            $table->text('diagnosis_text')->nullable();
            // Structured code only when authoritatively known; never inferred.
            $table->string('diagnosis_code', 60)->nullable();
            // Actor snapshot columns (mixed guards) — attribution lives in
            // the audit log; intentionally no FK (Phase 01 audit doctrine).
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('institute_id');
            $table->index('patient_id');
            $table->index('doctor_id');
            $table->index('status');
            $table->index('started_at');
            $table->index(['institute_id', 'patient_id', 'status'], 'encounters_tenant_patient_status_index');
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->foreignId('encounter_id')->nullable()->after('patient_id')
                ->constrained('medical_encounters')->nullOnDelete();
            $table->index('encounter_id');
        });

        Schema::table('lab_orders', function (Blueprint $table) {
            $table->foreignId('encounter_id')->nullable()->after('patient_id')
                ->constrained('medical_encounters')->nullOnDelete();
            $table->index('encounter_id');
        });
    }

    public function down(): void
    {
        Schema::table('lab_orders', function (Blueprint $table) {
            $table->dropForeign(['encounter_id']);
            $table->dropIndex(['encounter_id']);
            $table->dropColumn('encounter_id');
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropForeign(['encounter_id']);
            $table->dropIndex(['encounter_id']);
            $table->dropColumn('encounter_id');
        });

        Schema::dropIfExists('medical_encounters');
    }
};
