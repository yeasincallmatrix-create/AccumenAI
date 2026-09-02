<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_verification_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('backup_id')->nullable();
            $table->string('file', 500)->nullable();
            $table->enum('status', ['pending', 'verified', 'failed'])->default('pending');
            $table->json('report')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->unsignedInteger('table_count')->default(0);
            $table->unsignedInteger('row_count')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index('backup_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_verification_logs');
    }
};
