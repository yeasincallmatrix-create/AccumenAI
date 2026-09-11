<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 17 — Longitudinal problem list + clinical follow-up foundation.
 *
 * Two additive tables, both documentation-only (no inference, no
 * terminology authority, no automation):
 *
 * - medical_patient_problems: clinician-recorded longitudinal problems.
 *   Separate from encounter_diagnoses by design — diagnoses stay immutable
 *   history; problems are an explicit longitudinal abstraction. Source
 *   links (encounter / encounter diagnosis) are evidence/context only and
 *   never mutate the source. No unique constraint on labels: the clinician
 *   manages the list through explicit lifecycle actions, and removal is a
 *   status change is NOT offered — problems persist (active/inactive/
 *   resolved) with full audit history. There is deliberately NO delete path.
 * - medical_follow_ups: planned clinical follow-up records ("follow-up is
 *   planned"), structurally separate from appointments ("a visit is
 *   scheduled"). Nothing auto-books appointments.
 *
 * FKs follow Phase 03 doctrine: tenant/patient cascade with their owners,
 * source links NULL ON DELETE (longitudinal history survives encounter
 * cleanup), authorship NULL ON DELETE. Terminology: code/code_system are
 * clinician-supplied text only; mapping_status stays UNRESOLVED until the
 * shared RECOGNIZED_CODE_SYSTEMS registry gains an authority.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_patient_problems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            // Source links are context only; clearing them never touches history.
            $table->foreignId('encounter_id')->nullable()->constrained('medical_encounters')->nullOnDelete();
            $table->foreignId('encounter_diagnosis_id')->nullable()->constrained('encounter_diagnoses')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            // Clinician-entered label, preserved verbatim.
            $table->string('label', 255);
            // chronic|acute|historical|symptom|condition|other — application
            // documentation categories, not authoritative classifications.
            $table->string('problem_type', 20)->default('other');
            // structured|free_text — how the label was entered.
            $table->string('source', 20)->default('free_text');
            $table->string('mapping_status', 20)->default('unresolved');
            $table->string('code', 60)->nullable();
            $table->string('code_system', 60)->nullable();
            // DATE-ONLY, clinician-known only; NULL when unknown (never inferred).
            $table->date('onset_date')->nullable();
            $table->date('resolved_date')->nullable();
            // active|inactive|resolved — explicit transitions only.
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('institute_id');
            $table->index('patient_id');
            $table->index('status');
            $table->index(['institute_id', 'patient_id', 'status'], 'patient_problems_tenant_patient_status_index');
            $table->index('encounter_id');
        });

        Schema::create('medical_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained('medical_encounters')->nullOnDelete();
            $table->foreignId('problem_id')->nullable()->constrained('medical_patient_problems')->nullOnDelete();
            // Responsible clinician/user; authorship-style, never required.
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // DATE-ONLY planned clinical date (HMS convention, cf. prescriptions.follow_up_date).
            $table->date('planned_date');
            $table->text('reason');
            $table->text('notes')->nullable();
            // planned|completed|cancelled — explicit transitions only.
            $table->string('status', 20)->default('planned');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->index('institute_id');
            $table->index('patient_id');
            $table->index('status');
            $table->index('planned_date');
            $table->index(['institute_id', 'patient_id', 'status'], 'follow_ups_tenant_patient_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_follow_ups');
        Schema::dropIfExists('medical_patient_problems');
    }
};
