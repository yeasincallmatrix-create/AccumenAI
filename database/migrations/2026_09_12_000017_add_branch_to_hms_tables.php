<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 18 — HMS branch foundation (Stage 1: nullable relationships).
 *
 * Reuses the EXISTING branches table / Branch model / BranchContext — no
 * duplicate branch system is created. This migration only adds NULLABLE
 * branch links (Stage 1 of §16):
 *
 * - Branch-owned clinical transactions gain nullable branch_id:
 *   appointments, medical_encounters, admissions, wards, beds,
 *   prescriptions, lab_orders, medical_invoices, pharmacy_stock,
 *   pharmacy_dispenses, medical_follow_ups.
 * - clinical_audit_logs gains nullable branch_id (context when available;
 *   historical rows stay NULL and valid — never backfilled with guesses).
 * - New doctor_branch pivot: the minimum safe doctor↔branch association
 *   (active flag; a doctor with NO assignments is institute-wide, i.e.
 *   legacy-compatible, never silently locked out).
 *
 * Deliberately NOT branched: patients (institute identity), terminology
 * masters (concepts/products/ingredients/forms/routes/identifiers),
 * DGDA/RxNorm, CDS rules, departments/specialties, LabTest/medicine
 * catalogs, doctors (identity), number_sequences (institute numbering),
 * and derived child rows (encounter_diagnoses, lab_results,
 * prescription_items, vitals, nursing_notes, problems) which inherit
 * branch through their parent — no convenience duplication (§7).
 *
 * Legacy rule: branch_id NULL is a legitimate state (pre-branch records).
 * Branch-scoped readers see their branch PLUS legacy NULLs; nothing is
 * backfilled without provable mapping. FKs are nullOnDelete (rows survive
 * branch removal; branches themselves soft-delete/inactivate as lifecycle).
 */
return new class extends Migration
{
    private const BRANCH_OWNED = [
        'appointments',
        'medical_encounters',
        'admissions',
        'wards',
        'beds',
        'prescriptions',
        'lab_orders',
        'medical_invoices',
        'pharmacy_stock',
        'pharmacy_dispenses',
        'medical_follow_ups',
        'clinical_audit_logs',
    ];

    public function up(): void
    {
        foreach (self::BRANCH_OWNED as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('institute_id')
                    ->constrained('branches')->nullOnDelete();
                $table->index('branch_id');
            });
        }

        Schema::create('doctor_branch', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('medical_doctors')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'doctor_id'], 'uq_doctor_branch');
            $table->index(['institute_id', 'branch_id'], 'doctor_branch_tenant_branch_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_branch');

        foreach (self::BRANCH_OWNED as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropIndex(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }
    }
};
