<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institute_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('institute_settings', 'notification_settings')) {
                $table->json('notification_settings')->nullable()->after('language');
            }
        });
    }

    public function down(): void
    {
        Schema::table('institute_settings', function (Blueprint $table) {
            if (Schema::hasColumn('institute_settings', 'notification_settings')) {
                $table->dropColumn('notification_settings');
            }
        });
    }
};
