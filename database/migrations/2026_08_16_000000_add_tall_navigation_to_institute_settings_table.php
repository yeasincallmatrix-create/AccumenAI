<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institute_settings', function (Blueprint $table) {
            $table->boolean('tall_navigation')->default(false)->after('sidebar_color');
        });
    }

    public function down(): void
    {
        Schema::table('institute_settings', function (Blueprint $table) {
            $table->dropColumn('tall_navigation');
        });
    }
};
