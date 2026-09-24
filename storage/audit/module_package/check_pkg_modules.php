<?php
use Illuminate\Support\Facades\DB;

echo "=== Packages ===\n";
foreach (DB::table('subscription_packages')->get() as $p) {
    echo "  #{$p->id} {$p->slug} ({$p->name})\n";
}

echo "\n=== package_modules (sales/purchase) ===\n";
$rows = DB::table('package_modules')
    ->join('subscription_packages', 'subscription_packages.id', '=', 'package_modules.package_id')
    ->whereIn('package_modules.module_key', ['sales', 'purchase'])
    ->orderBy('subscription_packages.slug')
    ->orderBy('package_modules.module_key')
    ->get(['subscription_packages.slug', 'package_modules.module_key', 'package_modules.enabled']);
if ($rows->isEmpty()) { echo "  (none)\n"; }
foreach ($rows as $r) { echo "  {$r->slug}: {$r->module_key} enabled={$r->enabled}\n"; }

echo "\n=== free package all modules ===\n";
$free = DB::table('subscription_packages')->whereRaw('LOWER(slug) = ?', ['free'])->first();
if ($free) {
    $mods = DB::table('package_modules')->where('package_id', $free->id)->where('enabled', 1)->pluck('module_key');
    echo "  enabled: ".implode(', ', $mods->all())."\n";
} else {
    echo "  no free package\n";
}

echo "\n=== role_permissions for sales/purchase by role ===\n";
$roles = DB::table('roles')->whereNull('institute_id')->get(['id', 'slug']);
foreach ($roles as $role) {
    $count = DB::table('role_permissions')
        ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
        ->where('role_permissions.role_id', $role->id)
        ->where('permissions.slug', 'like', 'sales%')
        ->count()
        + DB::table('role_permissions')
        ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
        ->where('role_permissions.role_id', $role->id)
        ->where('permissions.slug', 'like', 'purchase%')
        ->count();
    if ($count > 0 || in_array($role->slug, ['institute-owner', 'institute-admin', 'branch-manager', 'accountant', 'teacher', 'receptionist'], true)) {
        echo "  {$role->slug}: {$count} sales+purchase perms\n";
    }
}

echo "\n=== owner has sales.view? ===\n";
$owner = DB::table('roles')->where('slug', 'institute-owner')->whereNull('institute_id')->first();
if ($owner) {
    $has = DB::table('role_permissions')
        ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
        ->where('role_permissions.role_id', $owner->id)
        ->whereIn('permissions.slug', ['sales.view', 'purchase.view', 'purchase.manage'])
        ->pluck('permissions')->count();
    echo "  count of sales.view/purchase.view/purchase.manage: {$has}\n";
}
