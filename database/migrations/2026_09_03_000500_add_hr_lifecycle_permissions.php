<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * HR-2 lifecycle permissions.
 *
 * Reuses HR-1 permission model (module=hr). Adds lifecycle-specific slugs so sensitive
 * actions can be gated separately, but controllers also accept hr.employee.* / hr.manage as fallbacks.
 *
 * Branch-manager: view history + transfer/promotion within own branch; no termination/resignation approval.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        ['module' => 'hr', 'name' => 'View Employment History', 'slug' => 'hr.history.view'],
        ['module' => 'hr', 'name' => 'Transfer Employees',      'slug' => 'hr.transfer'],
        ['module' => 'hr', 'name' => 'Promote Employees',       'slug' => 'hr.promotion'],
        ['module' => 'hr', 'name' => 'Manage Resignations',     'slug' => 'hr.resignation'],
        ['module' => 'hr', 'name' => 'Terminate Employees',     'slug' => 'hr.termination'],
        ['module' => 'hr', 'name' => 'Reactivate Employees',    'slug' => 'hr.reactivation'],
    ];

    private const GRANTS = [
        'institute-owner' => ['hr.history.view', 'hr.transfer', 'hr.promotion', 'hr.resignation', 'hr.termination', 'hr.reactivation'],
        'institute-admin' => ['hr.history.view', 'hr.transfer', 'hr.promotion', 'hr.resignation', 'hr.termination', 'hr.reactivation'],
        'branch-manager' => ['hr.history.view', 'hr.transfer'],
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
            ->get()->map(fn ($rp) => $rp->role_id.':'.$rp->permission_id)->all();

        $pairs = [];
        foreach (self::GRANTS as $roleSlug => $permissionSlugs) {
            $roleId = $roleIds[$roleSlug] ?? null;
            if ($roleId === null) {
                continue;
            }
            foreach ($permissionSlugs as $slug) {
                $id = $permissionIds[$slug] ?? null;
                if ($id === null) {
                    continue;
                }
                $key = $roleId.':'.$id;
                if (! in_array($key, $existing, true)) {
                    $pairs[] = ['role_id' => $roleId, 'permission_id' => $id];
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
        DB::table('permissions')->where('module', 'hr')->whereIn('slug', array_column(self::PERMISSIONS, 'slug'))->delete();
    }
};
