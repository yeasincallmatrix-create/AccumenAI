<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('owner_user_id');
            $table->enum('destination', ['drive', 'local'])->default('local');
            $table->string('drive_file_id', 255)->nullable();
            $table->string('filename', 255);
            $table->bigInteger('size_bytes')->default(0);
            $table->string('checksum_sha256', 64)->nullable();
            $table->boolean('encrypted')->default(true);
            $table->enum('status', ['pending', 'uploading', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
