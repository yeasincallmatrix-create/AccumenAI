<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add attendance.view permission (seed only had attendance.manage)
        $exists = DB::table('permissions')->where('slug', 'attendance.view')->exists();
        if (! $exists) {
            DB::table('permissions')->insert([
                'module' => 'hr',
                'name' => 'View Attendance',
                'slug' => 'attendance.view',
                'created_at' => now(),
            ]);
        }

        $permId = DB::table('permissions')->where('slug', 'attendance.view')->value('id');
        $roleIds = DB::table('roles')->whereIn('slug', ['institute-owner','institute-admin','branch-manager','teacher','hr-manager'])->pluck('id', 'slug');
        $existing = DB::table('role_permissions')->pluck('permission_id', 'role_id')->toArray();

        foreach (['institute-owner','institute-admin','branch-manager','teacher','hr-manager'] as $roleSlug) {
            $roleId = $roleIds[$roleSlug] ?? null;
            if (! $roleId || ! $permId) continue;
            $has = DB::table('role_permissions')->where('role_id', $roleId)->where('permission_id', $permId)->exists();
            if (! $has) {
                DB::table('role_permissions')->insert(['role_id' => $roleId, 'permission_id' => $permId]);
            }
        }

        // Also ensure attendance.manage implies view via grants already, but keep separate permission for API
    }

    public function down(): void
    {
        // Do not remove permission to avoid breaking grants
    }
};
