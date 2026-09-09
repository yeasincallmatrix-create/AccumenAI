<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 01 — Clinical record integrity.
 *
 * Deleting a discharged admission issued a real DELETE, which cascaded at
 * the database level into vital_signs + nursing_notes (physical loss of
 * clinical history, invisible to any audit). Soft deletes convert removal
 * into archival: no DELETE statement is issued, so no cascade fires and
 * vitals/notes survive alongside a recoverable admission row. List, show
 * and history queries behave exactly as before (trashed rows hidden).
 *
 * Purely additive (nullable deleted_at); no existing data is modified.
 * FK changes themselves are deferred to Phase 03 (delete/cascade safety).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
