<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        ['module' => 'hr', 'name' => 'Performance View', 'slug' => 'hr.performance.view'],
        ['module' => 'hr', 'name' => 'Performance Manage', 'slug' => 'hr.performance.manage'],
        ['module' => 'hr', 'name' => 'Performance Review', 'slug' => 'hr.performance.review'],
        ['module' => 'hr', 'name' => 'Performance Approve', 'slug' => 'hr.performance.approve'],
        ['module' => 'hr', 'name' => 'KPI Manage', 'slug' => 'hr.kpi.manage'],
        ['module' => 'hr', 'name' => 'Training View', 'slug' => 'hr.training.view'],
        ['module' => 'hr', 'name' => 'Training Manage', 'slug' => 'hr.training.manage'],
        ['module' => 'hr', 'name' => 'Training Enrollment', 'slug' => 'hr.training.enroll'],
        ['module' => 'hr', 'name' => 'Skills Manage', 'slug' => 'hr.skills.manage'],
        ['module' => 'hr', 'name' => 'Skills View', 'slug' => 'hr.skills.view'],
    ];

    private const GRANTS = [
        'institute-owner' => ['hr.performance.view', 'hr.performance.manage', 'hr.performance.review', 'hr.performance.approve', 'hr.kpi.manage', 'hr.training.view', 'hr.training.manage', 'hr.training.enroll', 'hr.skills.manage', 'hr.skills.view'],
        'institute-admin' => ['hr.performance.view', 'hr.performance.manage', 'hr.performance.review', 'hr.performance.approve', 'hr.kpi.manage', 'hr.training.view', 'hr.training.manage', 'hr.training.enroll', 'hr.skills.manage', 'hr.skills.view'],
        'branch-manager' => ['hr.performance.view', 'hr.performance.review', 'hr.training.view', 'hr.training.enroll', 'hr.skills.view'],
        'teacher' => ['hr.performance.view', 'hr.training.view', 'hr.skills.view'],
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
        DB::table('permissions')->whereIn('slug', array_column(self::PERMISSIONS, 'slug'))->delete();
    }
};
