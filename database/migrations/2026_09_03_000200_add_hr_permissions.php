<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * HR-1 permissions — industry-neutral Employee Core & Organization.
 *
 * Module: hr
 * Permissions follow the existing teacher/education pattern (slug module.action).
 * Idempotent: only inserts missing permission rows and links them to roles that already exist.
 *
 * Grants:
 *  - institute-owner / institute-admin : full HR management
 *  - branch-manager                     : view + create/update within branch scope (branch isolation via BranchContext)
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        ['module' => 'hr', 'name' => 'View Employees',        'slug' => 'hr.employee.view'],
        ['module' => 'hr', 'name' => 'Create Employees',      'slug' => 'hr.employee.create'],
        ['module' => 'hr', 'name' => 'Update Employees',      'slug' => 'hr.employee.update'],
        ['module' => 'hr', 'name' => 'Delete Employees',      'slug' => 'hr.employee.delete'],
        ['module' => 'hr', 'name' => 'Manage Employees',      'slug' => 'hr.employee.manage'],
        ['module' => 'hr', 'name' => 'View Departments',      'slug' => 'hr.department.view'],
        ['module' => 'hr', 'name' => 'Create Departments',    'slug' => 'hr.department.create'],
        ['module' => 'hr', 'name' => 'Update Departments',    'slug' => 'hr.department.update'],
        ['module' => 'hr', 'name' => 'Delete Departments',    'slug' => 'hr.department.delete'],
        ['module' => 'hr', 'name' => 'View Designations',     'slug' => 'hr.designation.view'],
        ['module' => 'hr', 'name' => 'Create Designations',   'slug' => 'hr.designation.create'],
        ['module' => 'hr', 'name' => 'Update Designations',   'slug' => 'hr.designation.update'],
        ['module' => 'hr', 'name' => 'Delete Designations',   'slug' => 'hr.designation.delete'],
        ['module' => 'hr', 'name' => 'Manage HR',             'slug' => 'hr.manage'],
    ];

    private const GRANTS = [
        'institute-owner' => [
            'hr.employee.view', 'hr.employee.create', 'hr.employee.update', 'hr.employee.delete', 'hr.employee.manage',
            'hr.department.view', 'hr.department.create', 'hr.department.update', 'hr.department.delete',
            'hr.designation.view', 'hr.designation.create', 'hr.designation.update', 'hr.designation.delete',
            'hr.manage',
        ],
        'institute-admin' => [
            'hr.employee.view', 'hr.employee.create', 'hr.employee.update', 'hr.employee.delete', 'hr.employee.manage',
            'hr.department.view', 'hr.department.create', 'hr.department.update', 'hr.department.delete',
            'hr.designation.view', 'hr.designation.create', 'hr.designation.update', 'hr.designation.delete',
            'hr.manage',
        ],
        'branch-manager' => [
            'hr.employee.view', 'hr.employee.create', 'hr.employee.update',
            'hr.department.view', 'hr.designation.view',
        ],
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
        DB::table('permissions')
            ->where('module', 'hr')
            ->whereIn('slug', array_column(self::PERMISSIONS, 'slug'))
            ->delete();
    }
};
