<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contract block on doctor profiles.
     *
     * employment_type / designation_id describe the engagement, while
     * doctor_fee_percentage records the share of each consultation fee the
     * doctor keeps. Discounting is opt-in: allow_discount is the toggle and
     * max_discount_percent is the ceiling the doctor may apply (null while
     * the toggle is off).
     */
    public function up(): void
    {
        Schema::table('medical_doctors', function (Blueprint $table) {
            if (! Schema::hasColumn('medical_doctors', 'employment_type')) {
                $table->string('employment_type', 30)->nullable()->after('experience_years');
            }
            if (! Schema::hasColumn('medical_doctors', 'designation_id')) {
                $table->unsignedBigInteger('designation_id')->nullable()->after('employment_type');
                $table->index('designation_id', 'medical_doctors_designation_id_index');
            }
            if (! Schema::hasColumn('medical_doctors', 'doctor_fee_percentage')) {
                $table->decimal('doctor_fee_percentage', 5, 2)->nullable()->after('designation_id');
            }
            if (! Schema::hasColumn('medical_doctors', 'allow_discount')) {
                $table->boolean('allow_discount')->default(false)->after('doctor_fee_percentage');
            }
            if (! Schema::hasColumn('medical_doctors', 'max_discount_percent')) {
                $table->decimal('max_discount_percent', 5, 2)->nullable()->after('allow_discount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('medical_doctors', function (Blueprint $table) {
            foreach (['max_discount_percent', 'allow_discount', 'doctor_fee_percentage', 'designation_id', 'employment_type'] as $column) {
                if (Schema::hasColumn('medical_doctors', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
