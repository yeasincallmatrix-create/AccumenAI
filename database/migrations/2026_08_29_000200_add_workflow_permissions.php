<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Step 51 — Education workflow automation permissions.
 *
 * Adds workflows.view + workflows.manage and grants them to the default
 * institute roles, mirroring the existing permission migrations. Idempotent:
 * only inserts missing rows.
 *
 * Grant matrix (default roles):
 *   - institute-owner / institute-admin : view + manage
 *   - branch-manager                    : view + manage
 *   - receptionist                      : view
 *   - teacher                           : view
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        ['module' => 'workflows', 'name' => 'Workflows View', 'slug' => 'workflows.view'],
        ['module' => 'workflows', 'name' => 'Workflows Manage', 'slug' => 'workflows.manage'],
    ];

    private const GRANTS = [
        'institute-owner' => ['workflows.view', 'workflows.manage'],
        'institute-admin' => ['workflows.view', 'workflows.manage'],
        'branch-manager' => ['workflows.view', 'workflows.manage'],
        'receptionist' => ['workflows.view'],
        'teacher' => ['workflows.view'],
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
            ->whereIn('slug', ['workflows.view', 'workflows.manage'])
            ->pluck('id')
            ->all();

        if ($permissionIds !== []) {
            DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }

        DB::table('permissions')->where('module', 'workflows')->delete();
    }
};
