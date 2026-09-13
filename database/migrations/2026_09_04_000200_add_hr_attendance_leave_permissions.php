<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * HR-4 attendance & leave permissions.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        ['module' => 'hr', 'name' => 'View Attendance',        'slug' => 'hr.attendance.view'],
        ['module' => 'hr', 'name' => 'Manage Attendance',      'slug' => 'hr.attendance.manage'],
        ['module' => 'hr', 'name' => 'View Leave',             'slug' => 'hr.leave.view'],
        ['module' => 'hr', 'name' => 'Create Leave',           'slug' => 'hr.leave.create'],
        ['module' => 'hr', 'name' => 'Update Leave',           'slug' => 'hr.leave.update'],
        ['module' => 'hr', 'name' => 'Manage Leave',           'slug' => 'hr.leave.manage'],
        ['module' => 'hr', 'name' => 'Manage Leave Policies',  'slug' => 'hr.leave.policy.manage'],
        ['module' => 'hr', 'name' => 'Approve Leave',          'slug' => 'hr.leave.approve'],
        ['module' => 'hr', 'name' => 'Manage Holidays',        'slug' => 'hr.holiday.manage'],
        ['module' => 'hr', 'name' => 'Manage Shifts',          'slug' => 'hr.shift.manage'],
    ];

    private const GRANTS = [
        'institute-owner' => ['hr.attendance.view', 'hr.attendance.manage', 'hr.leave.view', 'hr.leave.create', 'hr.leave.update', 'hr.leave.manage', 'hr.leave.policy.manage', 'hr.leave.approve', 'hr.holiday.manage', 'hr.shift.manage'],
        'institute-admin' => ['hr.attendance.view', 'hr.attendance.manage', 'hr.leave.view', 'hr.leave.create', 'hr.leave.update', 'hr.leave.manage', 'hr.leave.policy.manage', 'hr.leave.approve', 'hr.holiday.manage', 'hr.shift.manage'],
        'branch-manager'  => ['hr.attendance.view', 'hr.attendance.manage', 'hr.leave.view', 'hr.leave.create', 'hr.leave.approve'],
        'teacher'         => ['hr.leave.view', 'hr.leave.create'],
    ];

    public function up(): void
    {
        $ids = DB::table('permissions')->pluck('id', 'slug');
        foreach (self::PERMISSIONS as $perm) {
            if (! $ids->has($perm['slug'])) {
                DB::table('permissions')->insert($perm);
            }
        }
        $ids = DB::table('permissions')->pluck('id', 'slug');
        $roleIds = DB::table('roles')->pluck('id', 'slug');
        $existing = DB::table('role_permissions')->get()->map(fn ($r) => $r->role_id.':'.$r->permission_id)->all();
        $pairs = [];
        foreach (self::GRANTS as $role => $slugs) {
            $roleId = $roleIds[$role] ?? null;
            if ($roleId === null) continue;
            foreach ($slugs as $slug) {
                $pid = $ids[$slug] ?? null;
                if ($pid === null) continue;
                $key = $roleId.':'.$pid;
                if (! in_array($key, $existing, true)) {
                    $pairs[] = ['role_id' => $roleId, 'permission_id' => $pid];
                    $existing[] = $key;
                }
            }
        }
        if ($pairs !== []) DB::table('role_permissions')->insert($pairs);
    }

    public function down(): void
    {
        DB::table('permissions')->where('module', 'hr')->whereIn('slug', array_column(self::PERMISSIONS, 'slug'))->delete();
    }
};
