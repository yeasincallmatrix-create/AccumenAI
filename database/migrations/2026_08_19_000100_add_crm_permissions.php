<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the CRM Core permission set (crm.view/create/update/delete/manage) and
 * grants them to the default institute roles, mirroring the existing
 * permission migrations. Idempotent: only inserts missing rows.
 *
 * Grant matrix (default roles):
 *   - institute-owner / institute-admin : full access (all 5)
 *   - branch-manager                    : view + create + update
 *   - receptionist                      : view + create
 *   - accountant                        : view only
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        ['module' => 'crm', 'name' => 'CRM View', 'slug' => 'crm.view'],
        ['module' => 'crm', 'name' => 'CRM Create', 'slug' => 'crm.create'],
        ['module' => 'crm', 'name' => 'CRM Update', 'slug' => 'crm.update'],
        ['module' => 'crm', 'name' => 'CRM Delete', 'slug' => 'crm.delete'],
        ['module' => 'crm', 'name' => 'CRM Manage', 'slug' => 'crm.manage'],
    ];

    private const GRANTS = [
        'institute-owner' => ['crm.view', 'crm.create', 'crm.update', 'crm.delete', 'crm.manage'],
        'institute-admin' => ['crm.view', 'crm.create', 'crm.update', 'crm.delete', 'crm.manage'],
        'branch-manager' => ['crm.view', 'crm.create', 'crm.update'],
        'receptionist' => ['crm.view', 'crm.create'],
        'accountant' => ['crm.view'],
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
            ->whereIn('slug', ['crm.view', 'crm.create', 'crm.update', 'crm.delete', 'crm.manage'])
            ->pluck('id')
            ->all();

        if ($permissionIds !== []) {
            DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }

        DB::table('permissions')->where('module', 'crm')->delete();
    }
};
