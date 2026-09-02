<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_admins', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_admins', 'failed_login_count')) {
                $table->unsignedTinyInteger('failed_login_count')->default(0)->after('remember_token');
            }
            if (! Schema::hasColumn('platform_admins', 'locked_until')) {
                $table->timestamp('locked_until')->nullable()->after('failed_login_count');
            }
            if (! Schema::hasColumn('platform_admins', 'last_login_ip')) {
                $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_admins', function (Blueprint $table) {
            $table->dropColumn(['failed_login_count', 'locked_until', 'last_login_ip']);
        });
    }
};
