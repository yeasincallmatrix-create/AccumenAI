<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 01 — Clinical record integrity.
 *
 * Vitals, nursing notes and lab results previously vanished on delete
 * (vitals/notes have no update path — only hard delete; lab order update
 * deletes + recreates result rows). Soft deletes turn removal into
 * archival: default queries behave exactly as before (trashed rows are
 * hidden, same as hard-deleted rows), but history is recoverable and the
 * generalized clinical audit keeps pointing at a surviving row.
 *
 * Purely additive (nullable deleted_at); no existing data is modified.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['vital_signs', 'nursing_notes', 'lab_results'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (['lab_results', 'nursing_notes', 'vital_signs'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
