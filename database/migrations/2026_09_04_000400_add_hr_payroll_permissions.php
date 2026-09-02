<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * HR-5 — Payroll permissions.
 *
 * Adds hr.salary.* and hr.payroll.* perms and grants to default roles.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        ['module' => 'hr', 'name' => 'Salary View', 'slug' => 'hr.salary.view'],
        ['module' => 'hr', 'name' => 'Salary Manage', 'slug' => 'hr.salary.manage'],
        ['module' => 'hr', 'name' => 'Payroll View', 'slug' => 'hr.payroll.view'],
        ['module' => 'hr', 'name' => 'Payroll Manage', 'slug' => 'hr.payroll.manage'],
        ['module' => 'hr', 'name' => 'Payroll Approve', 'slug' => 'hr.payroll.approve'],
        ['module' => 'hr', 'name' => 'Payroll Pay', 'slug' => 'hr.payroll.pay'],
        ['module' => 'hr', 'name' => 'Payslip View Own', 'slug' => 'hr.payslip.own'],
    ];

    private const GRANTS = [
        'institute-owner' => ['hr.salary.view', 'hr.salary.manage', 'hr.payroll.view', 'hr.payroll.manage', 'hr.payroll.approve', 'hr.payroll.pay', 'hr.payslip.own'],
        'institute-admin' => ['hr.salary.view', 'hr.salary.manage', 'hr.payroll.view', 'hr.payroll.manage', 'hr.payroll.approve', 'hr.payroll.pay', 'hr.payslip.own'],
        'branch-manager' => ['hr.salary.view', 'hr.payroll.view', 'hr.payroll.manage', 'hr.payroll.approve', 'hr.payroll.pay', 'hr.payslip.own'],
        'teacher' => ['hr.payslip.own'],
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
        $existing = DB::table('role_permissions')->get()->map(fn ($rp) => $rp->role_id.':'.$rp->permission_id)->all();
        $pairs = [];
        foreach (self::GRANTS as $roleSlug => $permissionSlugs) {
            $roleId = $roleIds[$roleSlug] ?? null;
            if ($roleId === null) continue;
            foreach ($permissionSlugs as $permissionSlug) {
                $permissionId = $permissionIds[$permissionSlug] ?? null;
                if ($permissionId === null) continue;
                $key = $roleId.':'.$permissionId;
                if (! in_array($key, $existing, true)) {
                    $pairs[] = ['role_id' => $roleId, 'permission_id' => $permissionId];
                    $existing[] = $key;
                }
            }
        }
        if ($pairs !== []) DB::table('role_permissions')->insert($pairs);
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('slug', collect(self::PERMISSIONS)->pluck('slug')->all())->pluck('id')->all();
        if ($permissionIds !== []) DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('slug', collect(self::PERMISSIONS)->pluck('slug')->all())->delete();
    }
};
