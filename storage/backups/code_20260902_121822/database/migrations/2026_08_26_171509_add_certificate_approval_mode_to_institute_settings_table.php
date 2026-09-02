<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('institute_settings', function (Blueprint $table) {
            $table->string('certificate_approval_mode', 20)->nullable()->after('notification_settings')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('institute_settings', function (Blueprint $table) {
            $table->dropIndex(['certificate_approval_mode']);
            $table->dropColumn('certificate_approval_mode');
        });
    }
};
