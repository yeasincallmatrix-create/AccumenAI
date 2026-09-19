<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Seed the tax-module permissions.
 *
 * The permissions table has 230 rows from the SQL dump but zero tax
 * entries (tax.view, tax.manage, etc.), causing TaxEngineTest and
 * related suites to fail.
 *
 * Idempotent via firstOrCreate by slug — safe to re-run.
 * Does NOT attach to roles (that is RolePermissionSeeder territory).
 *
 * Run explicitly:
 *
 *   php artisan db:seed --class=TaxPermissionSeeder
 */
class TaxPermissionSeeder extends Seeder
{
    public static function permissions(): array
    {
        return [
            ['slug' => 'tax.view',     'module' => 'tax', 'name' => 'View Tax'],
            ['slug' => 'tax.manage',   'module' => 'tax', 'name' => 'Manage Tax'],
            ['slug' => 'tax.report',   'module' => 'tax', 'name' => 'Tax Report'],
            ['slug' => 'tax.settings', 'module' => 'tax', 'name' => 'Tax Settings'],
            ['slug' => 'tax.audit',    'module' => 'tax', 'name' => 'Tax Audit'],
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

        $this->command?->info('Tax permissions seeded.');
    }
}
