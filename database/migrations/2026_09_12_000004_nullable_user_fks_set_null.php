<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 03 — Deletion, cascade & clinical history safety.
 *
 * Admin user purges (AccountDeletionService::forceDelete) previously fired
 * database CASCADEs into clinical rows authored by that user (appointments,
 * prescriptions, admissions, lab orders, doctor profiles, dispenses),
 * vaporizing history. These columns become nullable with NULL ON DELETE so
 * a purged author's rows survive unattributed instead of disappearing.
 *
 * - No data is rewritten (existing values kept; orphan analysis on both
 *   monetix_test and accumen_ai found zero orphaned references).
 * - Application validation still requires a doctor on every write path, so
 *   only the purge path can ever produce NULLs; all record-display views
 *   already render unattributed doctors as 'N/A' (null-safe ?? chains).
 * - Rollback note: down() restores NOT NULL + CASCADE and will fail if NULL
 *   rows exist — delete or reattribute those rows first (dev-only concern).
 */
return new class extends Migration
{
    /**
     * [table, column, fk name].
     */
    private array $targets = [
        ['appointments', 'doctor_id', 'appointments_doctor_id_foreign'],
        ['prescriptions', 'doctor_id', 'prescriptions_doctor_id_foreign'],
        ['admissions', 'admitting_doctor_id', 'admissions_admitting_doctor_id_foreign'],
        ['lab_orders', 'doctor_id', 'lab_orders_doctor_id_foreign'],
        ['medical_doctors', 'user_id', 'medical_doctors_user_id_foreign'],
        ['pharmacy_dispenses', 'dispensed_by', 'pharmacy_dispenses_dispensed_by_foreign'],
    ];

    public function up(): void
    {
        foreach ($this->targets as [$table, $column, $fk]) {
            Schema::table($table, function (Blueprint $t) use ($fk) {
                $t->dropForeign($fk);
            });
            // All six columns are BIGINT UNSIGNED (Laravel foreignId).
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` BIGINT UNSIGNED NULL");
            Schema::table($table, function (Blueprint $t) use ($column, $fk) {
                $t->foreign($column, $fk)->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->targets as [$table, $column, $fk]) {
            Schema::table($table, function (Blueprint $t) use ($fk) {
                $t->dropForeign($fk);
            });
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` BIGINT UNSIGNED NOT NULL");
            Schema::table($table, function (Blueprint $t) use ($column, $fk) {
                $t->foreign($column, $fk)->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }
};
