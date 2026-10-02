<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restore_previews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('backup_id');
            $table->unsignedBigInteger('user_id');
            $table->string('mode', 20);   // merge | smart
            $table->json('diff');
            $table->integer('total_insert')->default(0);
            $table->integer('total_update')->default(0);
            $table->integer('total_soft_delete')->default(0);
            $table->integer('total_kept')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'backup_id']);
            $table->index('expires_at');
        });

        Schema::create('restore_rollbacks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('restore_log_id');
            $table->string('rollback_token', 64)->unique();
            $table->json('snapshot_data');
            $table->integer('total_rows')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('expires_at');
        });

        Schema::create('restore_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('restore_log_id');
            $table->unsignedBigInteger('user_id');
            $table->string('mode', 20);
            $table->json('affected_tables');
            $table->integer('total_affected')->default(0);
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('restore_log_id');
        });

        // Gate B: extend mode enum in place (no second column).
        DB::statement(
            "ALTER TABLE restore_logs MODIFY mode ENUM('merge','full_wipe','smart') NOT NULL DEFAULT 'merge'"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE restore_logs MODIFY mode ENUM('merge','full_wipe') NOT NULL DEFAULT 'merge'"
        );

        Schema::dropIfExists('restore_audits');
        Schema::dropIfExists('restore_rollbacks');
        Schema::dropIfExists('restore_previews');
    }
};
