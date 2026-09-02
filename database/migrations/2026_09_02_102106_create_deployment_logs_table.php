<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('deployment_logs')) {
            // Already created by 2026_09_02_000200 — ensure compatible columns (code_backup_path alias + updated_at)
            if (! Schema::hasColumn('deployment_logs', 'code_backup_path')) {
                Schema::table('deployment_logs', function (Blueprint $table) {
                    $table->string('code_backup_path', 500)->nullable()->after('log');
                });
                // sync data from backup_path
                try { \Illuminate\Support\Facades\DB::statement('UPDATE deployment_logs SET code_backup_path = backup_path WHERE code_backup_path IS NULL'); } catch (\Throwable $e) {}
            }
            if (! Schema::hasColumn('deployment_logs', 'backup_path')) {
                Schema::table('deployment_logs', function (Blueprint $table) {
                    $table->string('backup_path', 500)->nullable()->after('log');
                });
                try { \Illuminate\Support\Facades\DB::statement('UPDATE deployment_logs SET backup_path = code_backup_path WHERE backup_path IS NULL'); } catch (\Throwable $e) {}
            }
            if (! Schema::hasColumn('deployment_logs', 'updated_at')) {
                Schema::table('deployment_logs', function (Blueprint $table) {
                    $table->timestamp('updated_at')->nullable()->after('created_at');
                });
            }
            return;
        }
        Schema::create('deployment_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_user_id')->nullable()->index();
            $table->enum('type', ['git', 'zip'])->index();
            $table->string('version', 255)->nullable();
            $table->enum('status', ['success', 'failed', 'rolled_back'])->default('success')->index();
            $table->longText('log')->nullable();
            $table->string('code_backup_path', 500)->nullable();
            $table->string('backup_path', 500)->nullable();
            $table->string('db_backup_path', 500)->nullable();
            $table->timestamps();

            $table->foreign('admin_user_id')->references('id')->on('platform_admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployment_logs');
    }
};
