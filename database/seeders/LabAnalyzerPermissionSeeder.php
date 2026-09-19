<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Lab analyzer UI permissions (Phase 5).
 *
 * Idempotent via firstOrCreate by slug — safe to re-run.
 * Attaches to institute-owner + institute-admin (global roles that
 * TestCase::seedRoles guarantees); other roles are managed by admins.
 */
class LabAnalyzerPermissionSeeder extends Seeder
{
    public static function permissions(): array
    {
        return [
            ['slug' => 'medical.laboratory.analyzers.view', 'module' => 'medical.laboratory', 'name' => 'View Lab Analyzers'],
            ['slug' => 'medical.laboratory.analyzers.create', 'module' => 'medical.laboratory', 'name' => 'Create Lab Analyzers'],
            ['slug' => 'medical.laboratory.analyzers.edit', 'module' => 'medical.laboratory', 'name' => 'Edit Lab Analyzers'],
            ['slug' => 'medical.laboratory.analyzers.delete', 'module' => 'medical.laboratory', 'name' => 'Delete Lab Analyzers'],
            ['slug' => 'medical.laboratory.analyzers.manage_credentials', 'module' => 'medical.laboratory', 'name' => 'Manage Analyzer Credentials'],
            ['slug' => 'medical.laboratory.analyzers.manage_maps', 'module' => 'medical.laboratory', 'name' => 'Manage Analyzer Parameter Maps'],
            ['slug' => 'medical.laboratory.analyzers.view_messages', 'module' => 'medical.laboratory', 'name' => 'View Analyzer Messages'],
            ['slug' => 'medical.laboratory.analyzers.retry_messages', 'module' => 'medical.laboratory', 'name' => 'Retry Analyzer Messages'],
            ['slug' => 'medical.laboratory.analyzers.view_worklist', 'module' => 'medical.laboratory', 'name' => 'View Analyzer Worklist'],
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

        $this->command?->info('Lab analyzer permissions seeded: '.count(self::permissions()));
    }
}
