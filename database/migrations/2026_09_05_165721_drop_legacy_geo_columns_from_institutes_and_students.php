<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutes', function (Blueprint $table) {
            $table->dropColumn([
                'division',
                'district',
                'upazila',
            ]);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'present_division_id',
                'present_district_id',
                'present_upazila_id',
                'permanent_division_id',
                'permanent_district_id',
                'permanent_upazila_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('institutes', function (Blueprint $table) {
            $table->string('division', 80)->nullable()->after('country_id');
            $table->string('district', 80)->nullable()->after('division');
            $table->string('upazila', 80)->nullable()->after('district');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('present_division_id', 10)->nullable()->after('country_id');
            $table->string('present_district_id', 10)->nullable()->after('present_division_id');
            $table->string('present_upazila_id', 10)->nullable()->after('present_district_id');
            $table->string('permanent_division_id', 10)->nullable()->after('present_upazila_id');
            $table->string('permanent_district_id', 10)->nullable()->after('permanent_division_id');
            $table->string('permanent_upazila_id', 10)->nullable()->after('permanent_district_id');
        });
    }
};
