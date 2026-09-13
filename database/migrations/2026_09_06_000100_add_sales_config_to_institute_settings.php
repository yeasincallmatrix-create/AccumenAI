<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institute_settings', function (Blueprint $table) {
            $table->json('sales_config')->nullable()->after('notification_settings');
        });
    }

    public function down(): void
    {
        Schema::table('institute_settings', function (Blueprint $table) {
            $table->dropColumn('sales_config');
        });
    }
};
