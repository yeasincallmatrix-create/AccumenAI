<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Global Inventory Engine — STEP 16: inventory permission set.
 *
 * Adds the inventory module slugs and grants them to the default institute
 * roles, mirroring the accounting permission migration. Idempotent: only
 * inserts missing rows and role-permission pairs.
 *
 * Grant matrix:
 *   - institute-owner / institute-admin : full access (all 9)
 *   - branch-manager                    : view + create + update + adjust + transfer + count + reports (no approve/post)
 *   - accountant                        : full operational (incl. approve/post) + reports
 *   - receptionist                      : view + create only
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        ['module' => 'inventory', 'name' => 'Inventory View', 'slug' => 'inventory.view'],
        ['module' => 'inventory', 'name' => 'Inventory Create', 'slug' => 'inventory.create'],
        ['module' => 'inventory', 'name' => 'Inventory Update', 'slug' => 'inventory.update'],
        ['module' => 'inventory', 'name' => 'Inventory Adjust', 'slug' => 'inventory.adjust'],
        ['module' => 'inventory', 'name' => 'Inventory Transfer', 'slug' => 'inventory.transfer'],
        ['module' => 'inventory', 'name' => 'Inventory Count', 'slug' => 'inventory.count'],
        ['module' => 'inventory', 'name' => 'Inventory Approve', 'slug' => 'inventory.approve'],
        ['module' => 'inventory', 'name' => 'Inventory Post', 'slug' => 'inventory.post'],
        ['module' => 'inventory', 'name' => 'Inventory Reports View', 'slug' => 'inventory.reports.view'],
    ];

    private const GRANTS = [
        'institute-owner' => ['inventory.view', 'inventory.create', 'inventory.update', 'inventory.adjust', 'inventory.transfer', 'inventory.count', 'inventory.approve', 'inventory.post', 'inventory.reports.view'],
        'institute-admin' => ['inventory.view', 'inventory.create', 'inventory.update', 'inventory.adjust', 'inventory.transfer', 'inventory.count', 'inventory.approve', 'inventory.post', 'inventory.reports.view'],
        'branch-manager' => ['inventory.view', 'inventory.create', 'inventory.update', 'inventory.adjust', 'inventory.transfer', 'inventory.count', 'inventory.reports.view'],
        'accountant' => ['inventory.view', 'inventory.create', 'inventory.update', 'inventory.adjust', 'inventory.transfer', 'inventory.count', 'inventory.approve', 'inventory.post', 'inventory.reports.view'],
        'receptionist' => ['inventory.view', 'inventory.create'],
    ];

    public function up(): void
    {
        $permissionIds = DB::table('permissions')->pluck('id', 'slug');

        foreach (self::PERMISSIONS as $permission) {
            if (! $permissionIds->has($permission['slug'])) {
                DB::table('permissions')->insert($permission);
            }
        }

        $permissionIds = DB::table('permissions')->pluck('id', 'slug');
        $roleIds = DB::table('roles')->pluck('id', 'slug');

        $existing = DB::table('role_permissions')
            ->get()
            ->map(fn ($rp) => $rp->role_id.':'.$rp->permission_id)
            ->all();

        $pairs = [];
        foreach (self::GRANTS as $roleSlug => $permissionSlugs) {
            $roleId = $roleIds[$roleSlug] ?? null;
            if ($roleId === null) {
                continue;
            }

            foreach ($permissionSlugs as $permissionSlug) {
                $permissionId = $permissionIds[$permissionSlug] ?? null;
                if ($permissionId === null) {
                    continue;
                }

                $key = $roleId.':'.$permissionId;
                if (! in_array($key, $existing, true)) {
                    $pairs[] = ['role_id' => $roleId, 'permission_id' => $permissionId];
                    $existing[] = $key;
                }
            }
        }

        if ($pairs !== []) {
            DB::table('role_permissions')->insert($pairs);
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('slug', [
                'inventory.view',
                'inventory.create',
                'inventory.update',
                'inventory.adjust',
                'inventory.transfer',
                'inventory.count',
                'inventory.approve',
                'inventory.post',
                'inventory.reports.view',
            ])
            ->pluck('id')
            ->all();

        if ($permissionIds !== []) {
            DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }

        DB::table('permissions')->where('module', 'inventory')->delete();
    }
};
