<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-account user preferences (language, theme, dark mode, future menu
 * customization). Stored as a JSON column on each of the three account
 * tables so every preference is strictly personal to the account that
 * owns it — never shared between users.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'institute_users', 'platform_admins'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->json('preferences')->nullable()->after('preferred_language');
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'institute_users', 'platform_admins'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('preferences');
            });
        }
    }
};
