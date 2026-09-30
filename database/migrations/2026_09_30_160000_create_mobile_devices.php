<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core mobile API v1 — registered device (FCM token) registry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('fcm_token', 255);
            $table->enum('platform', ['android', 'ios']);
            $table->string('device_name', 120);
            $table->string('app_version', 20)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['institute_id', 'fcm_token'], 'md_token_unique');
            $table->index(['institute_id', 'user_id'], 'md_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_devices');
    }
};
