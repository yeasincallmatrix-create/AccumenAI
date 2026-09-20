<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Seed the accounting-module permissions.
 *
 * Mirrors TaxPermissionSeeder: the permissions table comes from the SQL
 * dump with zero accounting entries (accounts.view, journals.post,
 * reports.financial.view, settings.accounting.manage), causing
 * AccountingEngineTest's permission-seed test to fail.
 *
 * Idempotent via firstOrCreate by slug — safe to re-run.
 * Does NOT attach to roles (that is RolePermissionSeeder territory).
 */
class AccountingPermissionSeeder extends Seeder
{
    public static function permissions(): array
    {
        return [
            ['slug' => 'accounts.view',              'module' => 'accounting', 'name' => 'View Chart of Accounts'],
            ['slug' => 'accounts.create',            'module' => 'accounting', 'name' => 'Create Accounts'],
            ['slug' => 'accounts.edit',              'module' => 'accounting', 'name' => 'Edit Accounts'],
            ['slug' => 'accounts.delete',            'module' => 'accounting', 'name' => 'Delete Accounts'],
            ['slug' => 'journals.post',              'module' => 'accounting', 'name' => 'Post Journals'],
            ['slug' => 'journals.reverse',           'module' => 'accounting', 'name' => 'Reverse Journals'],
            ['slug' => 'journals.void',              'module' => 'accounting', 'name' => 'Void Journals'],
            ['slug' => 'reports.financial.view',     'module' => 'accounting', 'name' => 'View Financial Reports'],
            ['slug' => 'settings.accounting.manage', 'module' => 'accounting', 'name' => 'Manage Accounting Settings'],
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

        $this->command?->info('Accounting permissions seeded.');
    }
}
