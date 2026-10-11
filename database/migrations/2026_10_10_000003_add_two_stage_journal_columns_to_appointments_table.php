<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase C1 (two-stage OPD fee accounting) — split the single Phase B receipt
 * link into the two journals a staged collection can produce:
 *
 *   fee_receipt_journal_id      money received (cash in) at collection time
 *   fee_recognition_journal_id  revenue recognised when the service is
 *                               delivered — set later, or never for a
 *                               same-visit direct collection
 *
 * Both are nullable and added defensively: this codebase's migrations are
 * idempotent deltas on top of a data-only schema dump, so per-column
 * hasColumn() guards protect against columns a manual patch may have already
 * created. appointments.journal_id (Phase B) is intentionally left in place —
 * historical rows still point at it and nothing is backfilled.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('appointments', 'fee_receipt_journal_id')) {
            Schema::table('appointments', function (Blueprint $table): void {
                $table->unsignedBigInteger('fee_receipt_journal_id')
                    ->nullable()
                    ->after('journal_id')
                    ->comment('Cash receipt journal for an OPD fee (Phase C two-stage posting)');

                $table->foreign('fee_receipt_journal_id')
                    ->references('id')
                    ->on('journals')
                    ->nullOnDelete();

                $table->index('fee_receipt_journal_id', 'appointments_fee_receipt_journal_id_idx');
            });
        }

        if (! Schema::hasColumn('appointments', 'fee_recognition_journal_id')) {
            Schema::table('appointments', function (Blueprint $table): void {
                $table->unsignedBigInteger('fee_recognition_journal_id')
                    ->nullable()
                    ->after('fee_receipt_journal_id')
                    ->comment('Revenue recognition journal for a staged OPD fee (Phase C)');

                $table->foreign('fee_recognition_journal_id')
                    ->references('id')
                    ->on('journals')
                    ->nullOnDelete();

                $table->index('fee_recognition_journal_id', 'appointments_fee_recognition_journal_id_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('appointments', 'fee_recognition_journal_id')) {
            Schema::table('appointments', function (Blueprint $table): void {
                $table->dropIndex('appointments_fee_recognition_journal_id_idx');
                $table->dropForeign(['fee_recognition_journal_id']);
                $table->dropColumn('fee_recognition_journal_id');
            });
        }

        if (Schema::hasColumn('appointments', 'fee_receipt_journal_id')) {
            Schema::table('appointments', function (Blueprint $table): void {
                $table->dropIndex('appointments_fee_receipt_journal_id_idx');
                $table->dropForeign(['fee_receipt_journal_id']);
                $table->dropColumn('fee_receipt_journal_id');
            });
        }
    }
};
