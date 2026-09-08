<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Core staff/team permission.
 *
 * The `staff.manage` slug gates /staff/invite (see routes/web.php) but was
 * missing from the permissions table, leaving the page owner-only in
 * practice. Idempotent via firstOrCreate — safe to re-run.
 *
 * Run explicitly:
 *
 *   php artisan db:seed --class=StaffPermissionSeeder
 */
class StaffPermissionSeeder extends Seeder
{
    public function run(): void
    {
        Permission::firstOrCreate(
            ['slug' => 'staff.manage'],
            ['module' => 'staff', 'name' => 'Manage Staff']
        );

        // Role-management UI gate.
        Permission::firstOrCreate(
            ['slug' => 'roles.manage'],
            ['module' => 'staff', 'name' => 'Manage Roles']
        );

        // Placeholder permissions referenced by minimal industry templates.
        Permission::firstOrCreate(
            ['slug' => 'dashboard.view'],
            ['module' => 'dashboard', 'name' => 'View Dashboard']
        );
        Permission::firstOrCreate(
            ['slug' => 'settings.view'],
            ['module' => 'settings', 'name' => 'View Settings']
        );

        $this->command->info('Staff permissions seeded successfully!');
    }
}
