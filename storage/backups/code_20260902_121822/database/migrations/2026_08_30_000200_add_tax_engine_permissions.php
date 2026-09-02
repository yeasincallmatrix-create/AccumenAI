<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        ['module' => 'tax', 'name' => 'Tax View', 'slug' => 'tax.view'],
        ['module' => 'tax', 'name' => 'Tax Manage', 'slug' => 'tax.manage'],
        ['module' => 'tax', 'name' => 'Tax Rates', 'slug' => 'tax.rates'],
        ['module' => 'tax', 'name' => 'Tax Returns', 'slug' => 'tax.returns'],
        ['module' => 'tax', 'name' => 'Tax Reports', 'slug' => 'tax.reports'],
    ];

    private const GRANTS = [
        'institute-owner' => ['tax.view', 'tax.manage', 'tax.rates', 'tax.returns', 'tax.reports'],
        'institute-admin' => ['tax.view', 'tax.manage', 'tax.rates', 'tax.returns', 'tax.reports'],
        'accountant' => ['tax.view', 'tax.manage', 'tax.rates', 'tax.returns', 'tax.reports'],
        'branch-manager' => ['tax.view', 'tax.returns'],
        'receptionist' => ['tax.view'],
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

        DB::table('permissions')->where('module', 'tax')->delete();
    }
};
