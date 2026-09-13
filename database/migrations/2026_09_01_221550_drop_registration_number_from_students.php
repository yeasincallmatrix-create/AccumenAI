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
        // Drop unique index if exists, then column
        try {
            Schema::table('students', function (Blueprint $table) {
                try { $table->dropUnique('uq_students_registration_number'); } catch (\Throwable $e) {}
                try { $table->dropUnique(['registration_number']); } catch (\Throwable $e) {}
                try { $table->dropIndex('students_registration_number_unique'); } catch (\Throwable $e) {}
            });
        } catch (\Throwable $e) {}

        // Use raw SQL for column drop to handle cases where index already dropped
        try {
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE `students` DROP INDEX `uq_students_registration_number`');
        } catch (\Throwable $e) {}
        try {
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE `students` DROP INDEX `students_registration_number_unique`');
        } catch (\Throwable $e) {}

        Schema::table('students', function (Blueprint $table) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('students', 'registration_number')) {
                $table->dropColumn('registration_number');
            }
        });

        // Drop legacy sequence table (no longer needed for 10-digit random reg_no)
        \Illuminate\Support\Facades\Schema::dropIfExists('reg_no_sequence');

        // Ensure reg_no is NOT NULL where possible (change to string 10 not nullable) - keep nullable false but allow existing nulls to stay if any
        // Note: Using change() requires doctrine/dbal; we use raw if needed
        try {
            Schema::table('students', function (Blueprint $table) {
                $table->string('reg_no', 10)->nullable(false)->change();
            });
        } catch (\Throwable $e) {
            // fallback raw
            try { \Illuminate\Support\Facades\DB::statement("ALTER TABLE `students` MODIFY `reg_no` varchar(10) NOT NULL"); } catch (\Throwable $e2) {}
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (!\Illuminate\Support\Facades\Schema::hasColumn('students', 'registration_number')) {
                $table->string('registration_number', 12)->nullable()->unique()->after('reg_no');
            }
        });

        if (!\Illuminate\Support\Facades\Schema::hasTable('reg_no_sequence')) {
            \Illuminate\Support\Facades\Schema::create('reg_no_sequence', function (Blueprint $table) {
                $table->id();
                $table->string('year_code', 4)->nullable();
                $table->string('zip_code', 4)->nullable();
                $table->string('trade_code', 4)->nullable();
                $table->integer('last_sequence')->default(0);
                $table->unique(['year_code', 'zip_code', 'trade_code'], 'uq_reg_no_sequence_combo');
            });
        }
    }
};
