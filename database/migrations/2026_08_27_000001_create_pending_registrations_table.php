<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pending_registrations')) {
            Schema::create('pending_registrations', function (Blueprint $table) {
                $table->id();
                $table->string('email', 150);
                $table->string('password_hash', 255);
                $table->string('otp_hash', 255)->nullable();
                $table->timestamp('otp_expires_at')->nullable();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->unsignedTinyInteger('resend_count')->default(0);
                $table->timestamp('last_sent_at')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->json('organization_data')->nullable();
                $table->json('address_data')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->unique('email');
                $table->index('otp_expires_at');
                $table->index('expires_at');
                $table->index('verified_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_registrations');
    }
};
