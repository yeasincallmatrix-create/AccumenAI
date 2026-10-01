<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_drive_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->unique();
            $table->unsignedBigInteger('connected_by_user_id');
            $table->string('google_user_email', 150);
            $table->string('google_user_id', 100);
            $table->text('refresh_token');           // encrypted
            $table->string('drive_folder_id', 100);  // App Folder ID
            $table->timestamp('connected_at');
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_drive_connections');
    }
};
