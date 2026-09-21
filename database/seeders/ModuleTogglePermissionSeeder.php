<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class ModuleTogglePermissionSeeder extends Seeder
{
    public static function permissions(): array
    {
        return [
            ['slug' => 'institute.settings.module.view', 'module' => 'institute-settings', 'name' => 'View Module Management'],
            ['slug' => 'institute.settings.module.toggle', 'module' => 'institute-settings', 'name' => 'Toggle Module ON/OFF'],
        ];
    }

    public function run(): void
    {
        foreach (self::permissions() as $perm) {
            Permission::firstOrCreate(
                ['slug' => $perm['slug']],
                ['module' => $perm['module'], 'name' => $perm['name']],
            );
        }

        $permissionIds = Permission::whereIn('slug', array_column(self::permissions(), 'slug'))
            ->pluck('id')
            ->toArray();

        $roles = Role::whereNull('institute_id')
            ->whereIn('slug', ['institute-owner', 'institute-admin'])
            ->get();

        foreach ($roles as $role) {
            $role->permissions()->syncWithoutDetaching($permissionIds);
        }

        $this->command?->info('Module toggle permissions seeded: ' . count(self::permissions()));
    }
}
