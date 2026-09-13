<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_backups', function (Blueprint $table) {
            $table->id();
            $table->string('filename', 255);
            $table->string('path', 500);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->enum('type', ['manual', 'pre_restore', 'scheduled', 'health_check'])->default('manual');
            $table->enum('status', ['pending', 'completed', 'failed', 'verified'])->default('pending');
            $table->unsignedInteger('migration_count')->default(0);
            $table->string('migration_version', 100)->nullable();
            $table->unsignedBigInteger('table_count')->default(0);
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_type', 50)->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('created_at');
        });

        Schema::create('system_health_audits', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['healthy', 'warning', 'critical'])->default('healthy');
            $table->unsignedTinyInteger('score')->default(100);
            $table->json('checks');
            $table->json('missing_tables')->nullable();
            $table->json('missing_seeds')->nullable();
            $table->json('orphans')->nullable();
            $table->json('missing_indexes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_health_audits');
        Schema::dropIfExists('system_backups');
    }
};
