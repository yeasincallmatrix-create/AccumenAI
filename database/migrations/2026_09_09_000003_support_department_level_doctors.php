<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Support department-level doctors (no sub-specialty required):
     *  - specialty_id becomes nullable (via raw ALTER — no doctrine/dbal needed)
     *  - new nullable department_id FK so the department is preserved when
     *    specialty is null (the form already submits department_id)
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE `medical_doctors` MODIFY `specialty_id` BIGINT UNSIGNED NULL');

        Schema::table('medical_doctors', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable()->after('specialty_id');
            $table->foreign('department_id')->references('id')->on('medical_departments')->onDelete('set null');
            $table->index('department_id');
        });

        // Backfill department from specialty for any existing rows.
        DB::statement('UPDATE `medical_doctors` d JOIN `medical_specialties` s ON s.id = d.specialty_id SET d.department_id = s.department_id WHERE d.department_id IS NULL');
    }

    public function down(): void
    {
        Schema::table('medical_doctors', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });

        DB::statement('ALTER TABLE `medical_doctors` MODIFY `specialty_id` BIGINT UNSIGNED NOT NULL');
    }
};
