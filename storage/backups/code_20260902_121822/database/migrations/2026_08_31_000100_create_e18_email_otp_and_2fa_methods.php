<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Email OTP table (separate from phone_verification_otps)
        if (! Schema::hasTable('email_otps')) {
            Schema::create('email_otps', function (Blueprint $table) {
                $table->id();
                $table->string('guard', 20)->default('web');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('institute_id')->nullable();
                $table->string('email', 150);
                $table->string('otp_hash', 255);
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('expires_at');
                $table->timestamp('consumed_at')->nullable();
                $table->timestamps();

                $table->index(['guard', 'user_id', 'email']);
                $table->index(['institute_id']);
                $table->index('expires_at');
            });
        }

        // Add 2FA method columns to users
        $this->add2FaColumns('users');
        $this->add2FaColumns('institute_users');
        $this->add2FaColumns('platform_admins');
        if (Schema::hasTable('guardians')) {
            $this->add2FaColumns('guardians');
        }
    }

    protected function add2FaColumns(string $tableName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }
        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (! Schema::hasColumn($tableName, 'preferred_2fa_method')) {
                $table->string('preferred_2fa_method', 20)->nullable()->after('two_factor_confirmed_at');
            }
            if (! Schema::hasColumn($tableName, 'sms_2fa_enabled')) {
                $table->boolean('sms_2fa_enabled')->default(false)->after('preferred_2fa_method');
            }
            if (! Schema::hasColumn($tableName, 'email_2fa_enabled')) {
                $table->boolean('email_2fa_enabled')->default(false)->after('sms_2fa_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_otps');

        foreach (['users', 'institute_users', 'platform_admins', 'guardians'] as $t) {
            if (! Schema::hasTable($t)) continue;
            Schema::table($t, function (Blueprint $table) use ($t) {
                $cols = [];
                if (Schema::hasColumn($t, 'preferred_2fa_method')) $cols[] = 'preferred_2fa_method';
                if (Schema::hasColumn($t, 'sms_2fa_enabled')) $cols[] = 'sms_2fa_enabled';
                if (Schema::hasColumn($t, 'email_2fa_enabled')) $cols[] = 'email_2fa_enabled';
                if (! empty($cols)) $table->dropColumn($cols);
            });
        }
    }
};
