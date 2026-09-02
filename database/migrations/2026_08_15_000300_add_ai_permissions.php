<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds AI feature permissions (ai.assistant, ai.analytics, ...) and grants them
 * to the roles that may use AI. Idempotent: only inserts missing rows.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        ['module' => 'ai', 'name' => 'AI Assistant', 'slug' => 'ai.assistant'],
        ['module' => 'ai', 'name' => 'AI Analytics', 'slug' => 'ai.analytics'],
        ['module' => 'ai', 'name' => 'AI Content', 'slug' => 'ai.content'],
        ['module' => 'ai', 'name' => 'AI Reports', 'slug' => 'ai.reports'],
        ['module' => 'ai', 'name' => 'AI Automation', 'slug' => 'ai.automation'],
    ];

    private const GRANTS = [
        'institute-owner' => ['ai.assistant', 'ai.analytics', 'ai.content', 'ai.reports', 'ai.automation'],
        'institute-admin' => ['ai.assistant', 'ai.analytics', 'ai.content', 'ai.reports'],
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
        DB::table('permissions')->where('module', 'ai')->delete();
    }
};
