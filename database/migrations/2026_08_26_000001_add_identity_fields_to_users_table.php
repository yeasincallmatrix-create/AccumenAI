<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
            $table->string('pending_email', 150)->nullable()->after('phone_verified_at');
            $table->string('pending_email_token_hash', 255)->nullable()->after('pending_email');
            $table->timestamp('pending_email_expires_at')->nullable()->after('pending_email_token_hash');
            $table->string('pending_phone', 20)->nullable()->after('pending_email_expires_at');
        });

        // Unique phone index (normalized E164). Nullable allows multiple nulls on MySQL.
        Schema::table('users', function (Blueprint $table) {
            $table->unique('phone', 'uq_users_phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('uq_users_phone');
            $table->dropColumn(['phone_verified_at', 'pending_email', 'pending_email_token_hash', 'pending_email_expires_at', 'pending_phone']);
        });
    }
};
