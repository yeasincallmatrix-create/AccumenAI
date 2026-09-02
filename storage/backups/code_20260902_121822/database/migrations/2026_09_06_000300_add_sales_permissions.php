<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        ['module' => 'sales', 'name' => 'View Sales',   'slug' => 'sales.view'],
        ['module' => 'sales', 'name' => 'Create Sales', 'slug' => 'sales.create'],
        ['module' => 'sales', 'name' => 'Update Sales', 'slug' => 'sales.update'],
        ['module' => 'sales', 'name' => 'Delete Sales', 'slug' => 'sales.delete'],
        ['module' => 'sales', 'name' => 'Manage Sales', 'slug' => 'sales.manage'],
    ];

    private const GRANTS = [
        'institute-owner' => ['sales.view', 'sales.create', 'sales.update', 'sales.delete', 'sales.manage'],
        'institute-admin' => ['sales.view', 'sales.create', 'sales.update', 'sales.delete', 'sales.manage'],
        'branch-manager'  => ['sales.view', 'sales.create', 'sales.update'],
        'accountant'      => ['sales.view', 'sales.manage'],
        // receptionist / teacher intentionally no sales access
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
