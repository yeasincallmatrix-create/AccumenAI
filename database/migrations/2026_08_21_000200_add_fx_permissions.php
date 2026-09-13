<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * STEP 19 — Multi-Currency & FX Accounting: permission set.
 *
 * Adds the FX module slugs and grants them to the default institute roles.
 * Idempotent: only inserts missing rows, mirroring the accounting permission
 * migration (2026_08_19_010800).
 *
 * Grant matrix:
 *   - institute-owner / institute-admin / accountant : manage rates + run revaluation
 *   - branch-manager                                 : run revaluation only
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        ['module' => 'accounting', 'name' => 'FX Rates Manage', 'slug' => 'fx.rates.manage'],
        ['module' => 'accounting', 'name' => 'FX Revaluation Run', 'slug' => 'fx.revaluation.run'],
    ];

    private const GRANTS = [
        'institute-owner' => ['fx.rates.manage', 'fx.revaluation.run'],
        'institute-admin' => ['fx.rates.manage', 'fx.revaluation.run'],
        'accountant' => ['fx.rates.manage', 'fx.revaluation.run'],
        'branch-manager' => ['fx.revaluation.run'],
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
            ->whereIn('slug', ['fx.rates.manage', 'fx.revaluation.run'])
            ->pluck('id')
            ->all();

        if ($permissionIds !== []) {
            DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }

        DB::table('permissions')->whereIn('slug', ['fx.rates.manage', 'fx.revaluation.run'])->delete();
    }
};
