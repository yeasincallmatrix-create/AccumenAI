<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clinical numbers go tenant-scoped.
 *
 * The tenant segment is removed from generated numbers (MR-2026-189-00002
 * becomes MR-2026-00002 stored / MR-26-00002 displayed), so the old GLOBAL
 * unique keys would collide across tenants (every tenant's first MR of the
 * year is now identical). Each global unique becomes a composite
 * (institute_id + number) unique: unique inside the tenant, repeatable
 * across tenants.
 *
 * No data is rewritten: historical numbers (including legacy
 * PREFIX-YYYY-III-NNNNN rows) keep working and stay reserved by the
 * sequence backfill.
 */
return new class extends Migration
{
    /**
     * @var array<int, array{table: string, column: string, composite: string}>
     */
    private array $targets = [
        ['table' => 'patients', 'column' => 'mr_number', 'composite' => 'uq_patients_institute_mr'],
        ['table' => 'prescriptions', 'column' => 'prescription_number', 'composite' => 'uq_prescriptions_institute_number'],
        ['table' => 'lab_orders', 'column' => 'order_number', 'composite' => 'uq_lab_orders_institute_number'],
        ['table' => 'medical_invoices', 'column' => 'invoice_number', 'composite' => 'uq_medical_invoices_institute_number'],
        ['table' => 'tpa_claims', 'column' => 'claim_number', 'composite' => 'uq_tpa_claims_institute_number'],
        ['table' => 'medical_encounters', 'column' => 'encounter_number', 'composite' => 'uq_medical_encounters_institute_number'],
    ];

    public function up(): void
    {
        foreach ($this->targets as $target) {
            Schema::table($target['table'], function (Blueprint $table) use ($target) {
                $table->dropUnique([$target['column']]);
            });
        }

        foreach ($this->targets as $target) {
            Schema::table($target['table'], function (Blueprint $table) use ($target) {
                $table->unique(['institute_id', $target['column']], $target['composite']);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->targets as $target) {
            Schema::table($target['table'], function (Blueprint $table) use ($target) {
                $table->dropUnique($target['composite']);
            });
        }

        // Restores the old global uniques. Fails honestly if two tenants
        // now share a number (the reason this migration exists).
        foreach ($this->targets as $target) {
            Schema::table($target['table'], function (Blueprint $table) use ($target) {
                $table->unique($target['column']);
            });
        }
    }
};
