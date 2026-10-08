<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Employee profile fields: blood group, marital status, education,
     * expertise tags, plus separate present/permanent addresses.
     *
     * expertise is a JSON array of free-text tags entered through the chip
     * input on the employee form. The single legacy `address` column is kept
     * untouched for backward compatibility.
     */
    public function up(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_employees', 'blood_group')) {
                $table->enum('blood_group', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])->nullable()->after('gender');
            }
            if (! Schema::hasColumn('hr_employees', 'marital_status')) {
                $table->enum('marital_status', ['single', 'married', 'divorced', 'separated', 'widowed'])->nullable()->after('blood_group');
            }
            if (! Schema::hasColumn('hr_employees', 'education_qualification')) {
                $table->string('education_qualification', 500)->nullable()->after('marital_status');
            }
            if (! Schema::hasColumn('hr_employees', 'expertise')) {
                $table->json('expertise')->nullable()->after('education_qualification');
            }
            if (! Schema::hasColumn('hr_employees', 'present_address')) {
                $table->text('present_address')->nullable()->after('address');
            }
            if (! Schema::hasColumn('hr_employees', 'permanent_address')) {
                $table->text('permanent_address')->nullable()->after('present_address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            foreach (['permanent_address', 'present_address', 'expertise', 'education_qualification', 'marital_status', 'blood_group'] as $column) {
                if (Schema::hasColumn('hr_employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
