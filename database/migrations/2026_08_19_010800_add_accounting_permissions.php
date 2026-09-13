<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Global Accounting Engine — Step 1: accounting permission set.
 *
 * Adds the accounting module slugs and grants them to the default institute
 * roles. Idempotent: only inserts missing rows, mirroring the CRM permission
 * migration.
 *
 * Grant matrix:
 *   - institute-owner / institute-admin : full access (all 8)
 *   - branch-manager                    : view + create + post + financial reports
 *   - accountant                        : full operational + settings + export
 *   - receptionist                      : view + create
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        ['module' => 'accounting', 'name' => 'Accounts View', 'slug' => 'accounts.view'],
        ['module' => 'accounting', 'name' => 'Accounts Manage', 'slug' => 'accounts.manage'],
        ['module' => 'accounting', 'name' => 'Journal Create', 'slug' => 'journals.create'],
        ['module' => 'accounting', 'name' => 'Journal Post', 'slug' => 'journals.post'],
        ['module' => 'accounting', 'name' => 'Journal Reverse', 'slug' => 'journals.reverse'],
        ['module' => 'accounting', 'name' => 'Financial Reports View', 'slug' => 'reports.financial.view'],
        ['module' => 'accounting', 'name' => 'Financial Reports Export', 'slug' => 'reports.financial.export'],
        ['module' => 'accounting', 'name' => 'Accounting Settings Manage', 'slug' => 'settings.accounting.manage'],
    ];

    private const GRANTS = [
        'institute-owner' => ['accounts.view', 'accounts.manage', 'journals.create', 'journals.post', 'journals.reverse', 'reports.financial.view', 'reports.financial.export', 'settings.accounting.manage'],
        'institute-admin' => ['accounts.view', 'accounts.manage', 'journals.create', 'journals.post', 'journals.reverse', 'reports.financial.view', 'reports.financial.export', 'settings.accounting.manage'],
        'branch-manager' => ['accounts.view', 'journals.create', 'journals.post', 'reports.financial.view'],
        'accountant' => ['accounts.view', 'accounts.manage', 'journals.create', 'journals.post', 'journals.reverse', 'reports.financial.view', 'reports.financial.export', 'settings.accounting.manage'],
        'receptionist' => ['accounts.view', 'journals.create'],
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
                'accounts.view',
                'accounts.manage',
                'journals.create',
                'journals.post',
                'journals.reverse',
                'reports.financial.view',
                'reports.financial.export',
                'settings.accounting.manage',
            ])
            ->pluck('id')
            ->all();

        if ($permissionIds !== []) {
            DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }

        DB::table('permissions')->where('module', 'accounting')->delete();
    }
};
