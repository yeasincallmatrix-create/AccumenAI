<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            if (!Schema::hasColumn('backups', 'progress_percent')) {
                $table->unsignedTinyInteger('progress_percent')->default(0);
            }
            if (!Schema::hasColumn('backups', 'progress_stage')) {
                $table->string('progress_stage', 50)->nullable();
            }
            if (!Schema::hasColumn('backups', 'progress_message')) {
                $table->string('progress_message', 500)->nullable();
            }
            if (!Schema::hasColumn('backups', 'job_id')) {
                $table->string('job_id', 100)->nullable();
            }
            // started_at already exists (Phase 1/2 schema) — guarded
            if (!Schema::hasColumn('backups', 'started_at')) {
                $table->timestamp('started_at')->nullable();
            }
            if (!Schema::hasColumn('backups', 'total_chunks')) {
                $table->unsignedInteger('total_chunks')->default(0);
            }
            if (!Schema::hasColumn('backups', 'uploaded_chunks')) {
                $table->unsignedInteger('uploaded_chunks')->default(0);
            }
        });

        Schema::table('restore_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('restore_logs', 'progress_percent')) {
                $table->unsignedTinyInteger('progress_percent')->default(0);
            }
            if (!Schema::hasColumn('restore_logs', 'progress_stage')) {
                $table->string('progress_stage', 50)->nullable();
            }
            if (!Schema::hasColumn('restore_logs', 'progress_message')) {
                $table->string('progress_message', 500)->nullable();
            }
            if (!Schema::hasColumn('restore_logs', 'job_id')) {
                $table->string('job_id', 100)->nullable();
            }
            if (!Schema::hasColumn('restore_logs', 'downloaded_chunks')) {
                $table->unsignedInteger('downloaded_chunks')->default(0);
            }
            if (!Schema::hasColumn('restore_logs', 'total_chunks')) {
                $table->unsignedInteger('total_chunks')->default(0);
            }
            // Adaptation 7: job/endpoint error surface
            if (!Schema::hasColumn('restore_logs', 'error_message')) {
                $table->text('error_message')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $cols = array_filter([
                'progress_percent', 'progress_stage', 'progress_message',
                'job_id', 'total_chunks', 'uploaded_chunks',
            ], fn ($c) => Schema::hasColumn('backups', $c));
            if ($cols) {
                $table->dropColumn($cols);
            }
        });
        Schema::table('restore_logs', function (Blueprint $table) {
            $cols = array_filter([
                'progress_percent', 'progress_stage', 'progress_message',
                'job_id', 'downloaded_chunks', 'total_chunks', 'error_message',
            ], fn ($c) => Schema::hasColumn('restore_logs', $c));
            if ($cols) {
                $table->dropColumn($cols);
            }
        });
    }
};
