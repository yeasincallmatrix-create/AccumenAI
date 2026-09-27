<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Phase B — aging report permissions (5 modules × view/manage = 10).
 *
 * Follows AccountingPermissionSeeder: the permissions table comes from the
 * SQL dump with no aging entries, so CheckPermission middleware would 403
 * the aging routes for non-owner roles until these exist.
 *
 * Slug convention matches the accounting module (short slugs, module
 * column carries the namespace): ar_aging.view, ap_aging.manage, ...
 *
 * Idempotent via firstOrCreate by slug — safe to re-run.
 * Does NOT attach to roles (that is RolePermissionSeeder territory; owners
 * are auto-granted through Membership::hasPermission()).
 */
class AccountingAgingPermissionSeeder extends Seeder
{
    public static function permissions(): array
    {
        return [
            ['slug' => 'ar_aging.view',       'name' => 'View AR Aging Report'],
            ['slug' => 'ar_aging.manage',     'name' => 'Manage AR Aging Report'],
            ['slug' => 'ap_aging.view',       'name' => 'View AP Aging Report'],
            ['slug' => 'ap_aging.manage',     'name' => 'Manage AP Aging Report'],
            ['slug' => 'invoice_aging.view',  'name' => 'View Invoice Aging'],
            ['slug' => 'invoice_aging.manage','name' => 'Manage Invoice Aging'],
            ['slug' => 'aging_summary.view',  'name' => 'View Aging Summary'],
            ['slug' => 'aging_summary.manage','name' => 'Manage Aging Summary'],
            ['slug' => 'aging_config.view',   'name' => 'View Aging Configuration'],
            ['slug' => 'aging_config.manage', 'name' => 'Manage Aging Configuration'],
        ];
    }

    public function run(): void
    {
        $inserted = 0;

        foreach (self::permissions() as $perm) {
            $created = Permission::firstOrCreate(
                ['slug' => $perm['slug']],
                ['module' => 'accounting', 'name' => $perm['name']],
            );
            if ($created->wasRecentlyCreated) {
                $inserted++;
            }
        }

        $this->command?->info("{$inserted} aging permissions inserted.");
    }
}
