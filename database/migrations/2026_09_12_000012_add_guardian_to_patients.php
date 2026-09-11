<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guardian concept: a row with is_patient=false holds a shared phone
     * until its holder registers as a patient (converted, never duplicated).
     * date_of_birth turns nullable because placeholder guardians have none;
     * request validation still requires age-or-DOB for real registrations.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->boolean('is_patient')->default(true)->after('is_dependent');
            $table->string('guardian_name', 100)->nullable()->after('is_patient');
            $table->index(['is_patient']);
        });

        DB::statement('ALTER TABLE `patients` MODIFY `date_of_birth` DATE NULL');
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex(['is_patient']);
            $table->dropColumn(['is_patient', 'guardian_name']);
        });

        DB::statement('ALTER TABLE `patients` MODIFY `date_of_birth` DATE NOT NULL');
    }
};
