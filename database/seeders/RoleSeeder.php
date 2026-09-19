<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Ensure the institute-owner system role exists.
 *
 * SystemRoleSeeder seeds 7 of 8 system-level roles but omits
 * institute-owner (it was only present in the SQL dump).  Tests that
 * call Role::where('slug','institute-owner')->firstOrFail() fail when
 * the dump is absent or the DB is rebuilt via migrate:fresh.
 *
 * This seeder ONLY covers institute-owner — the other 7 roles are
 * already handled by SystemRoleSeeder (no duplication).
 *
 * Idempotent via firstOrCreate — safe to re-run.
 *
 * Run explicitly:
 *
 *   php artisan db:seed --class=RoleSeeder
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(
            ['institute_id' => null, 'slug' => 'institute-owner'],
            ['name' => 'Institute Owner', 'is_system' => true, 'status' => 'active'],
        );

        $this->command?->info('RoleSeeder: institute-owner ensured.');
    }
}
