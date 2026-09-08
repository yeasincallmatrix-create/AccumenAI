<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Platform-admin-area permissions (module `admin`).
 *
 * Idempotent via firstOrCreate — safe to re-run.
 *
 * Run explicitly:
 *
 *   php artisan db:seed --class=AdminPermissionSeeder
 */
class AdminPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['slug' => 'admin.system', 'module' => 'admin', 'name' => 'System Health'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['slug' => $permission['slug']],
                ['module' => $permission['module'], 'name' => $permission['name']]
            );
        }

        $this->command->info('Admin permissions seeded successfully!');
    }
}
