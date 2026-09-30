<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile sync extension — client_id → server_id mapping for
 * idempotent push (no duplicates on retry/replay).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_sync_idempotency', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id')->index();
            $table->string('client_id', 36);
            $table->string('entity', 60);
            $table->unsignedBigInteger('server_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['institute_id', 'client_id'], 'msi_client_unique');
            $table->index(['institute_id', 'entity', 'created_at'], 'msi_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_sync_idempotency');
    }
};
