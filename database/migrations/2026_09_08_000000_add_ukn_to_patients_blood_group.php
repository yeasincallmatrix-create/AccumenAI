<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'UKN'];

    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $list = implode(',', array_map(fn ($g) => "'{$g}'", self::GROUPS));
            DB::statement("ALTER TABLE `patients` MODIFY `blood_group` ENUM({$list}) NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            // Move UKN rows back to NULL before shrinking the enum.
            DB::table('patients')->where('blood_group', 'UKN')->update(['blood_group' => null]);
            DB::statement("ALTER TABLE `patients` MODIFY `blood_group` ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NULL");
        }
    }
};
