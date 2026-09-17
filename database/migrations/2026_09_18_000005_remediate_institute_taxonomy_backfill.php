<?php

use App\Services\InstituteTaxonomyBackfill;
use Illuminate\Database\Migrations\Migration;

/**
 * Remediation for 2026_09_18_000004_backfill_institute_taxonomy, which ran
 * before the taxonomy seeder populated industries/sub_industries and
 * therefore matched nothing (and never reruns).
 *
 * This migration reuses the same idempotent service as the
 * `taxonomy:backfill-institutes` artisan command:
 * - only NULL FK columns are filled; valid existing FKs are never touched;
 * - legacy string columns are preserved;
 * - unmatched values are left NULL (reported via logs by the command);
 * - no-op when the taxonomy is not seeded yet (fresh install before
 *   seeding): run the artisan command after seeding in that case.
 *
 * Rollback is intentionally a no-op: clearing resolved FKs would destroy
 * data with no way to know which rows this migration filled.
 */
return new class extends Migration
{
    public function up(): void
    {
        InstituteTaxonomyBackfill::run();
    }

    public function down(): void
    {
        // No-op by design (see docblock).
    }
};
