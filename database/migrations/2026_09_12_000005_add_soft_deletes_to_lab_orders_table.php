<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 03 — Deletion, cascade & clinical history safety (Step 5).
 *
 * Deleting an ordered lab order issued a real DELETE, which cascaded at the
 * database level into lab_results (physical loss, even of soft-deleted
 * rows). Archival instead: no DELETE is issued, no cascade fires, the order
 * and its results survive hidden from operational queries — exactly like a
 * deleted order behaved for readers, but recoverable and auditable.
 *
 * Purely additive (nullable deleted_at); no existing data is modified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_orders', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('lab_orders', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
