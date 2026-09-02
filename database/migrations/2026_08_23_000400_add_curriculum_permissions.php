<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Step 42 — curriculum permissions (education module).
 *
 * Access model:
 *   - institute-owner / institute-admin : full curriculum management.
 *   - branch-manager                   : view / manage within branch context.
 *   - teacher                          : read-only access to the curriculum of
 *                                        the courses they teach (mirrors the
 *                                        courses.view already granted).
 *   - accountant                       : no curriculum access (course fee info
 *                                        comes from courses.view only).
 *
 * Idempotent: only inserts missing rows.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        ['module' => 'education', 'name' => 'View Curricula', 'slug' => 'curriculum.view'],
        ['module' => 'education', 'name' => 'Manage Curricula', 'slug' => 'curriculum.manage'],
    ];

    private const GRANTS = [
        'institute-owner' => ['curriculum.view', 'curriculum.manage'],
        'institute-admin' => ['curriculum.view', 'curriculum.manage'],
        'branch-manager' => ['curriculum.view', 'curriculum.manage'],
        'teacher' => ['curriculum.view'],
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
            ->whereIn('slug', ['curriculum.view', 'curriculum.manage'])
            ->delete();
    }
};
