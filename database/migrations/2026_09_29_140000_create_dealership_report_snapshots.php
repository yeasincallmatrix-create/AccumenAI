<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dealership Phase 4 — report snapshot cache (1 table).
     *
     * institute_id NOT NULL + index (tenancy pattern). Per-tenant
     * composite unique on (institute_id, report_key, scope_hash).
     * Guarded by hasTable for idempotency.
     */
    public function up(): void
    {
        if (! Schema::hasTable('dealership_report_snapshots')) {
            Schema::create('dealership_report_snapshots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('institute_id')->index();
                $table->string('report_key', 80);
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->string('scope_hash', 64);
                $table->json('payload');
                $table->timestamp('generated_at')->nullable();
                $table->timestamps();
                $table->unique(['institute_id', 'report_key', 'scope_hash'], 'drs_scope_unique');
                $table->index(['institute_id', 'report_key', 'period_start'], 'drs_lookup_idx');
            });
        }
    }

    public function down(): void
    {
        // NO destructive rollback per project rules.
    }
};
