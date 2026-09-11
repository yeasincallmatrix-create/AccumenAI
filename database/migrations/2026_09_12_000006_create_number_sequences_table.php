<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 04 — Identifier & numbering hardening.
 *
 * Database-backed per-institute, per-year sequences for clinical numbering
 * (MR, prescription, lab order, invoice, TPA claim). Replaces random MR
 * generation and last-row suffix parsing with a single row-locked counter:
 *
 *   institute_id + sequence_type + year  →  last_number   (UNIQUE)
 *
 * - New table only; no existing data is read, rewritten or renumbered.
 * - Continuity with existing rows is handled in code (first allocation for
 *   an institute/type/year backfills from the highest existing suffix, so
 *   new numbers never collide with or reuse historical ones).
 * - Gap policy: numbers are unique and roughly ordered, NOT gapless. Gaps
 *   arise from deleted/voided records (numbers are retired, never reused).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->string('sequence_type', 30);
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['institute_id', 'sequence_type', 'year'], 'uq_number_sequences');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
