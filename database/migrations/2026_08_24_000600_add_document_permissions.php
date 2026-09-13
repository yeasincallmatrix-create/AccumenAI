<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Step 46 — Document Management permissions.
 *
 * Adds documents.view + documents.manage and grants them to the default
 * institute roles, mirroring the existing permission migrations. Idempotent:
 * only inserts missing rows.
 *
 * Grant matrix (default roles):
 *   - institute-owner / institute-admin : view + manage
 *   - branch-manager                    : view + manage
 *   - receptionist                      : view + manage (handles students)
 *   - teacher                           : view only
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        ['module' => 'documents', 'name' => 'Documents View', 'slug' => 'documents.view'],
        ['module' => 'documents', 'name' => 'Documents Manage', 'slug' => 'documents.manage'],
    ];

    private const GRANTS = [
        'institute-owner' => ['documents.view', 'documents.manage'],
        'institute-admin' => ['documents.view', 'documents.manage'],
        'branch-manager' => ['documents.view', 'documents.manage'],
        'receptionist' => ['documents.view', 'documents.manage'],
        'teacher' => ['documents.view'],
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
            ->whereIn('slug', ['documents.view', 'documents.manage'])
            ->pluck('id')
            ->all();

        if ($permissionIds !== []) {
            DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }

        DB::table('permissions')->where('module', 'documents')->delete();
    }
};
