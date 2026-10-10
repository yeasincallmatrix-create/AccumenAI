<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase A (medical ledger foundation) — link an OPD fee collection to the
 * receipt journal it posted, so the finance side can be traced back from the
 * appointment without scanning journals by ref_type/ref_id.
 *
 * The column is nullable and added defensively: this codebase's migrations
 * are idempotent deltas on top of a data-only schema dump, so hasColumn()
 * guards against a column that a manual patch may have already created.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('appointments', 'journal_id')) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table): void {
            $table->unsignedBigInteger('journal_id')
                ->nullable()
                ->after('fee_collected_at')
                ->comment('Receipt journal posted by collectFee (Phase B wiring)');

            $table->foreign('journal_id')
                ->references('id')
                ->on('journals')
                ->nullOnDelete();

            $table->index('journal_id', 'appointments_journal_id_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('appointments', 'journal_id')) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropIndex('appointments_journal_id_idx');
            $table->dropForeign(['journal_id']);
            $table->dropColumn('journal_id');
        });
    }
};
