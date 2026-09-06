<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phase 3 — allow institute-guard staff (no row in `users`) to dispense.
     *
     * Raw ALTER is used because doctrine/dbal is not installed (->change()
     * would fail). MariaDB/MySQL only.
     *
     * NOTE: the Phase 3 draft's medicines-table migration
     * (2026_09_08 add side_effects/contraindications/storage_conditions)
     * is intentionally NOT created — those columns already exist from the
     * Phase 0 migration and re-adding them would crash.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE `pharmacy_dispenses` MODIFY `dispensed_by` BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        // Symmetric revert. Fails loudly if Phase 3 NULL rows exist —
        // truthful: the column cannot go NOT NULL while they do.
        DB::statement('ALTER TABLE `pharmacy_dispenses` MODIFY `dispensed_by` BIGINT UNSIGNED NOT NULL');
    }
};
