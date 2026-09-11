<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 15 — Structured encounter diagnoses (documentation only).
 *
 * encounter_diagnoses attaches clinician-entered diagnoses to a Phase 14
 * encounter. This is a documentation/workflow table, NOT a terminology
 * authority and NOT a diagnostic engine:
 * - There is deliberately NO diagnosis concept/master table: the repository
 *   has no authoritative terminology source (no ICD/SNOMED datasets), so a
 *   master dictionary would be fabricated knowledge. Raw clinician-entered
 *   labels are preserved; code/code_system are stored ONLY when the
 *   clinician supplies an authoritatively known code, and mapping_status
 *   stays UNRESOLVED otherwise.
 * - No patient_id column by design (§28): the patient is derived from the
 *   encounter, so Encounter A can never point at Patient B's diagnosis.
 * - Duplicate control: UNIQUE(encounter_id, label, status) gives one active
 *   row per label per encounter (ci collation => case-insensitive), while a
 *   removed row stays preserved beside a later re-addition.
 * - Removal is a status change (active→removed), never a delete; completed
 *   encounters only accept changes through the reason-gated amend path.
 * - FKs follow Phase 03 doctrine: tenant/encounter cascade with their
 *   owners, authorship NULL ON DELETE, no destructive cascade into history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounter_diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('encounter_id')->constrained('medical_encounters')->cascadeOnDelete();
            // Users purge nulls authorship instead of vaporizing diagnoses.
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            // Clinician-entered label, preserved verbatim. 255 chars so the
            // duplicate-control unique index stays within limits.
            $table->string('label', 255);
            // primary|secondary|differential|symptom — always explicitly
            // chosen by the clinician; the app never assigns primary.
            $table->string('diagnosis_type', 20)->default('secondary');
            // structured|free_text — how the label was entered.
            $table->string('source', 20)->default('free_text');
            // resolved|unresolved — whether code/code_system is backed by a
            // recognized authority (see config allowlist); raw text stays
            // UNRESOLVED rather than fabricating a mapping.
            $table->string('mapping_status', 20)->default('unresolved');
            // Optional authoritative code, clinician-supplied only.
            $table->string('code', 60)->nullable();
            $table->string('code_system', 60)->nullable();
            $table->text('notes')->nullable();
            // active|removed — removal preserves the row for audit.
            $table->string('status', 20)->default('active');
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->unique(['encounter_id', 'label', 'status'], 'encounter_diagnoses_unique_label');
            $table->index('institute_id');
            $table->index('encounter_id');
            $table->index('status');
            $table->index(['institute_id', 'status'], 'encounter_diagnoses_tenant_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_diagnoses');
    }
};
