<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Geo address selectors for HR employees: the same Country → Division →
 * District → Upazila cascade used by the student/patient forms (<x-address>).
 * `present_address` / `permanent_address` keep holding the street line.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            foreach (['present', 'permanent'] as $prefix) {
                if (! Schema::hasColumn('hr_employees', $prefix.'_country_id')) {
                    $table->unsignedBigInteger($prefix.'_country_id')->nullable();
                }
                foreach ([1, 2, 3] as $level) {
                    if (! Schema::hasColumn('hr_employees', $prefix.'_admin_'.$level.'_id')) {
                        $table->unsignedBigInteger($prefix.'_admin_'.$level.'_id')->nullable();
                    }
                }
                if (! Schema::hasColumn('hr_employees', $prefix.'_zip_code')) {
                    $table->string($prefix.'_zip_code', 10)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            foreach (['present', 'permanent'] as $prefix) {
                foreach ([1, 2, 3] as $level) {
                    if (Schema::hasColumn('hr_employees', $prefix.'_admin_'.$level.'_id')) {
                        $table->dropColumn($prefix.'_admin_'.$level.'_id');
                    }
                }
                if (Schema::hasColumn('hr_employees', $prefix.'_country_id')) {
                    $table->dropColumn($prefix.'_country_id');
                }
                if (Schema::hasColumn('hr_employees', $prefix.'_zip_code')) {
                    $table->dropColumn($prefix.'_zip_code');
                }
            }
        });
    }
};
