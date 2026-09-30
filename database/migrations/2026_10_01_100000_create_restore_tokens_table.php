<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restore_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('backup_id');
            $table->unsignedBigInteger('user_id');
            $table->char('token_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
            $table->index('expires_at');
        });

        Schema::create('restore_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('backup_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('mode', ['merge', 'full_wipe'])->default('merge');
            $table->json('records_affected')->nullable();
            $table->string('rollback_path', 500)->nullable();
            $table->enum('status', ['pending', 'completed', 'failed', 'rolled_back'])->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restore_tokens');
        Schema::dropIfExists('restore_logs');
    }
};
