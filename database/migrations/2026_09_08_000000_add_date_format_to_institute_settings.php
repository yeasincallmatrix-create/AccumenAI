<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-institute date display order for Settings → General → Date Format.
     * Values: dmy (DD/MM/YYYY), mdy (MM/DD/YYYY), ymd (YYYY/MM/DD).
     */
    public function up(): void
    {
        Schema::table('institute_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('institute_settings', 'date_format')) {
                $table->string('date_format', 10)->default('dmy')->after('language');
            }
        });
    }

    public function down(): void
    {
        Schema::table('institute_settings', function (Blueprint $table) {
            if (Schema::hasColumn('institute_settings', 'date_format')) {
                $table->dropColumn('date_format');
            }
        });
    }
};
