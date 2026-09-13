<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('permissions')->where('slug', 'admin.deploy')->exists()) {
            DB::table('permissions')->insert([
                'slug' => 'admin.deploy',
                'module' => 'admin',
                'name' => 'Deploy via Git/ZIP',
            ]);
        }

        // Assign to system roles that should have deploy access (institute-owner, institute-admin)
        // PlatformAdmin bypasses permission checks, but we assign for completeness
        $permId = DB::table('permissions')->where('slug', 'admin.deploy')->value('id');
        $roleIds = DB::table('roles')->whereIn('slug', ['institute-owner', 'institute-admin'])->pluck('id')->toArray();
        foreach ($roleIds as $roleId) {
            if (! DB::table('role_permissions')->where('role_id', $roleId)->where('permission_id', $permId)->exists()) {
                DB::table('role_permissions')->insert(['role_id' => $roleId, 'permission_id' => $permId]);
            }
        }
    }

    public function down(): void
    {
        $permId = DB::table('permissions')->where('slug', 'admin.deploy')->value('id');
        if ($permId) {
            DB::table('role_permissions')->where('permission_id', $permId)->delete();
            DB::table('permissions')->where('id', $permId)->delete();
        }
    }
};
