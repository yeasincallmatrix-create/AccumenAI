<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Step 36 — teacher management permissions (education module).
 *
 * Access model:
 *   - institute-owner / institute-admin : full teacher management.
 *   - branch-manager                   : view / create / update within branch
 *                                       (branch scope enforced by BranchContext).
 *   - teacher / other roles            : no unrestricted teacher management;
 *                                       teachers reach their own profile through
 *                                       the dedicated self-service route.
 *
 * Idempotent: only inserts missing rows.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        ['module' => 'education', 'name' => 'View Teachers', 'slug' => 'teacher.view'],
        ['module' => 'education', 'name' => 'Create Teachers', 'slug' => 'teacher.create'],
        ['module' => 'education', 'name' => 'Update Teachers', 'slug' => 'teacher.update'],
        ['module' => 'education', 'name' => 'Delete Teachers', 'slug' => 'teacher.delete'],
        ['module' => 'education', 'name' => 'Manage Teachers', 'slug' => 'teacher.manage'],
    ];

    private const GRANTS = [
        'institute-owner' => ['teacher.view', 'teacher.create', 'teacher.update', 'teacher.delete', 'teacher.manage'],
        'institute-admin' => ['teacher.view', 'teacher.create', 'teacher.update', 'teacher.delete', 'teacher.manage'],
        'branch-manager' => ['teacher.view', 'teacher.create', 'teacher.update'],
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
        DB::table('permissions')->where('module', 'education')
            ->whereIn('slug', ['teacher.view', 'teacher.create', 'teacher.update', 'teacher.delete', 'teacher.manage'])
            ->delete();
    }
};
