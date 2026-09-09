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
        Schema::table('medical_doctors', function (Blueprint $table) {
            $table->decimal('first_visit_fee', 10, 2)->default(700.00)->after('consultation_fee');
            $table->decimal('follow_up_fee', 10, 2)->default(500.00)->after('first_visit_fee');
            $table->integer('follow_up_days')->default(30)->after('follow_up_fee');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->decimal('fee_applied', 10, 2)->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('fee_applied');
        });

        Schema::table('medical_doctors', function (Blueprint $table) {
            $table->dropColumn(['first_visit_fee', 'follow_up_fee', 'follow_up_days']);
        });
    }
};
