<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Global Fixed Asset Engine — STEP 17: permission set.
 *
 * Idempotent grant matrix:
 *   - institute-owner / institute-admin : full (all 14)
 *   - branch-manager                    : view/create/update/transfer/depreciate/reports/qr.view
 *   - accountant                        : full operational (incl. approve/post/dispose/impair/revalue)
 *   - receptionist                      : view only
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        ['module' => 'asset', 'name' => 'Asset View', 'slug' => 'asset.view'],
        ['module' => 'asset', 'name' => 'Asset Create', 'slug' => 'asset.create'],
        ['module' => 'asset', 'name' => 'Asset Update', 'slug' => 'asset.update'],
        ['module' => 'asset', 'name' => 'Asset Capitalize', 'slug' => 'asset.capitalize'],
        ['module' => 'asset', 'name' => 'Asset Transfer', 'slug' => 'asset.transfer'],
        ['module' => 'asset', 'name' => 'Asset Depreciate', 'slug' => 'asset.depreciate'],
        ['module' => 'asset', 'name' => 'Asset Dispose', 'slug' => 'asset.dispose'],
        ['module' => 'asset', 'name' => 'Asset Impair', 'slug' => 'asset.impair'],
        ['module' => 'asset', 'name' => 'Asset Revalue', 'slug' => 'asset.revalue'],
        ['module' => 'asset', 'name' => 'Asset Approve', 'slug' => 'asset.approve'],
        ['module' => 'asset', 'name' => 'Asset Post', 'slug' => 'asset.post'],
        ['module' => 'asset', 'name' => 'Asset Reports View', 'slug' => 'asset.reports.view'],
        ['module' => 'asset', 'name' => 'Asset QR View', 'slug' => 'asset.qr.view'],
        ['module' => 'asset', 'name' => 'Asset QR Manage', 'slug' => 'asset.qr.manage'],
    ];

    private const GRANTS = [
        'institute-owner' => ['asset.view', 'asset.create', 'asset.update', 'asset.capitalize', 'asset.transfer', 'asset.depreciate', 'asset.dispose', 'asset.impair', 'asset.revalue', 'asset.approve', 'asset.post', 'asset.reports.view', 'asset.qr.view', 'asset.qr.manage'],
        'institute-admin' => ['asset.view', 'asset.create', 'asset.update', 'asset.capitalize', 'asset.transfer', 'asset.depreciate', 'asset.dispose', 'asset.impair', 'asset.revalue', 'asset.approve', 'asset.post', 'asset.reports.view', 'asset.qr.view', 'asset.qr.manage'],
        'branch-manager' => ['asset.view', 'asset.create', 'asset.update', 'asset.transfer', 'asset.depreciate', 'asset.reports.view', 'asset.qr.view'],
        'accountant' => ['asset.view', 'asset.create', 'asset.update', 'asset.capitalize', 'asset.transfer', 'asset.depreciate', 'asset.dispose', 'asset.impair', 'asset.revalue', 'asset.approve', 'asset.post', 'asset.reports.view', 'asset.qr.view', 'asset.qr.manage'],
        'receptionist' => ['asset.view'],
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
        $slugs = array_column(self::PERMISSIONS, 'slug');

        $permissionIds = DB::table('permissions')->whereIn('slug', $slugs)->pluck('id')->all();

        if ($permissionIds !== []) {
            DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }

        DB::table('permissions')->where('module', 'asset')->delete();
    }
};
