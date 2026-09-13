<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the promotion.manage permission (institute-level academic promotion /
 * progression) and grants it to owner + admin roles, mirroring the existing
 * education.manage migration. Super Admin area is guarded by the
 * auth:platform_admin route group, so no platform role grant is needed.
 *
 * Idempotent: only inserts missing rows.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        ['module' => 'education', 'name' => 'Academic Promotion', 'slug' => 'promotion.manage'],
    ];

    private const GRANTS = [
        'institute-owner' => ['promotion.manage'],
        'institute-admin' => ['promotion.manage'],
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
        DB::table('permissions')->where('slug', 'promotion.manage')->delete();
    }
};
