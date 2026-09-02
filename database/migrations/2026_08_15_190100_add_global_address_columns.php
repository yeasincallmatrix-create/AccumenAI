<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the global address columns to students and institutes.
     *
     * students   : present_* / permanent_* level-1/2/3 unit ids + a country id
     *              (the old BD-only division/district/upazila columns stay for
     *              backwards compatibility with legacy rows).
     * institutes : country_id + admin_level_1_id/2/3_id (free-text division/
     *              district/upazila columns remain for legacy rows).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('students', 'present_country_id')) {
            Schema::table('students', function (Blueprint $table) {
                $table->unsignedBigInteger('present_country_id')->nullable()->after('country');
                $table->unsignedBigInteger('present_admin_1_id')->nullable()->after('present_country_id');
                $table->unsignedBigInteger('present_admin_2_id')->nullable()->after('present_admin_1_id');
                $table->unsignedBigInteger('present_admin_3_id')->nullable()->after('present_admin_2_id');
                $table->unsignedBigInteger('permanent_country_id')->nullable()->after('permanent_zip_code');
                $table->unsignedBigInteger('permanent_admin_1_id')->nullable()->after('permanent_country_id');
                $table->unsignedBigInteger('permanent_admin_2_id')->nullable()->after('permanent_admin_1_id');
                $table->unsignedBigInteger('permanent_admin_3_id')->nullable()->after('permanent_admin_2_id');
            });
        }

        if (! Schema::hasColumn('institutes', 'country_id')) {
            Schema::table('institutes', function (Blueprint $table) {
                $table->unsignedBigInteger('country_id')->nullable()->after('country');
                $table->unsignedBigInteger('admin_level_1_id')->nullable()->after('upazila');
                $table->unsignedBigInteger('admin_level_2_id')->nullable()->after('admin_level_1_id');
                $table->unsignedBigInteger('admin_level_3_id')->nullable()->after('admin_level_2_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'present_country_id',
                'present_admin_1_id',
                'present_admin_2_id',
                'present_admin_3_id',
                'permanent_country_id',
                'permanent_admin_1_id',
                'permanent_admin_2_id',
                'permanent_admin_3_id',
            ]);
        });

        Schema::table('institutes', function (Blueprint $table) {
            $table->dropColumn([
                'country_id',
                'admin_level_1_id',
                'admin_level_2_id',
                'admin_level_3_id',
            ]);
        });
    }
};
