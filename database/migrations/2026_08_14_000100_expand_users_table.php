<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expand the (currently unused) global `users` table into the single account
 * table for the workspace architecture. The columns absorbed from
 * institute_users become the global identity: auth, 2FA, profile, security.
 *
 * Additive only — nothing is dropped; institute_users stays during transition.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->nullable()->after('id');
            $table->string('first_name', 60)->nullable()->after('name');
            $table->string('last_name', 60)->nullable()->after('first_name');
            $table->string('preferred_language', 10)->default('en')->after('phone');
            $table->string('photo', 255)->nullable()->after('preferred_language');
            $table->timestamp('email_verified_at')->nullable()->after('photo');
            $table->text('two_factor_secret')->nullable()->after('email_verified_at');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->string('remember_token', 100)->nullable()->after('two_factor_confirmed_at');
            $table->unsignedTinyInteger('failed_login_count')->default(0)->after('remember_token');
            $table->timestamp('locked_until')->nullable()->after('failed_login_count');
            $table->timestamp('last_login_at')->nullable()->after('locked_until');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->softDeletes()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'uuid',
                'first_name',
                'last_name',
                'preferred_language',
                'photo',
                'email_verified_at',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'remember_token',
                'failed_login_count',
                'locked_until',
                'last_login_at',
                'last_login_ip',
            ]);
            $table->dropSoftDeletes();
        });
    }
};
