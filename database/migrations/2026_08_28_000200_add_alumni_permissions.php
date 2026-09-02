<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Step 48 — Alumni Management permissions.
 *
 * Adds the alumni.view / create / update / delete / manage permissions and
 * grants them to the default institute roles, mirroring the existing
 * permission migrations. Idempotent: only inserts missing rows.
 *
 * Grant matrix (default roles):
 *   - institute-owner / institute-admin : full (view, create, update, delete, manage)
 *   - branch-manager                    : view + update (their branch only)
 *   - receptionist                      : view only
 *   - teacher / accountant              : none
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        ['module' => 'alumni', 'name' => 'Alumni View', 'slug' => 'alumni.view'],
        ['module' => 'alumni', 'name' => 'Alumni Create', 'slug' => 'alumni.create'],
        ['module' => 'alumni', 'name' => 'Alumni Update', 'slug' => 'alumni.update'],
        ['module' => 'alumni', 'name' => 'Alumni Delete', 'slug' => 'alumni.delete'],
        ['module' => 'alumni', 'name' => 'Alumni Manage', 'slug' => 'alumni.manage'],
    ];

    private const GRANTS = [
        'institute-owner' => ['alumni.view', 'alumni.create', 'alumni.update', 'alumni.delete', 'alumni.manage'],
        'institute-admin' => ['alumni.view', 'alumni.create', 'alumni.update', 'alumni.delete', 'alumni.manage'],
        'branch-manager' => ['alumni.view', 'alumni.update'],
        'receptionist' => ['alumni.view'],
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
                'alumni.view',
                'alumni.create',
                'alumni.update',
                'alumni.delete',
                'alumni.manage',
            ])
            ->pluck('id')
            ->all();

        if ($permissionIds !== []) {
            DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }

        DB::table('permissions')->where('module', 'alumni')->delete();
    }
};
