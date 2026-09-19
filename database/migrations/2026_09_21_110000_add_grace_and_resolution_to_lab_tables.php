<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 6: token rotation grace period + dead-letter resolution.
     * Additive columns only; all nullable; reversible.
     */
    public function up(): void
    {
        // 1. Token rotation grace period
        Schema::table('lab_device_credentials', function (Blueprint $table) {
            if (! Schema::hasColumn('lab_device_credentials', 'previous_token_hash')) {
                $table->string('previous_token_hash', 64)->nullable()->after('token_hash');
                $table->string('previous_token_prefix', 12)->nullable()->after('previous_token_hash');
                $table->timestamp('previous_token_expires_at')->nullable()->after('previous_token_prefix');
            }
        });

        // 2. Dead-letter resolution fields
        Schema::table('lab_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('lab_messages', 'resolution_status')) {
                $table->string('resolution_status', 30)->nullable()->after('status');
                // values: retried, resolved_manual, discarded, escalated
                $table->text('resolution_notes')->nullable()->after('resolution_status');
                $table->unsignedBigInteger('resolved_by')->nullable()->after('resolution_notes');
                $table->timestamp('resolved_at')->nullable()->after('resolved_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lab_device_credentials', function (Blueprint $table) {
            foreach (['previous_token_hash', 'previous_token_prefix', 'previous_token_expires_at'] as $col) {
                if (Schema::hasColumn('lab_device_credentials', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::table('lab_messages', function (Blueprint $table) {
            foreach (['resolution_status', 'resolution_notes', 'resolved_by', 'resolved_at'] as $col) {
                if (Schema::hasColumn('lab_messages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
