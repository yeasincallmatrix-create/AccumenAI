<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archive_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('module', 50);
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('archived_rows')->default(0);
            $table->json('criteria')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // Archive tables (mirror original with same structure + archived_at)
        foreach (['attendance', 'audit_logs', 'activity_logs'] as $orig) {
            $archive = $orig . '_archive';
            if (Schema::hasTable($orig) && ! Schema::hasTable($archive)) {
                Schema::create($archive, function (Blueprint $table) use ($orig) {
                    // Create minimal archive structure; we will copy columns dynamically in service
                    $table->id();
                    $table->unsignedBigInteger('original_id');
                    $table->json('data');
                    $table->timestamp('original_created_at')->nullable();
                    $table->timestamp('archived_at')->useCurrent();
                    $table->index('original_id');
                    $table->index('archived_at');
                });
            }
        }

        // Also support notifications archive if not exists
        if (! Schema::hasTable('notifications_archive') && Schema::hasTable('notifications')) {
            Schema::create('notifications_archive', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('original_id');
                $table->json('data');
                $table->timestamp('original_created_at')->nullable();
                $table->timestamp('archived_at')->useCurrent();
                $table->index('original_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_archive');
        Schema::dropIfExists('activity_logs_archive');
        Schema::dropIfExists('audit_logs_archive');
        Schema::dropIfExists('attendance_archive');
        Schema::dropIfExists('archive_jobs');
    }
};
