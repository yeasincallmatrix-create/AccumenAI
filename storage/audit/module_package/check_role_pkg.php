<?php
require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Force test DB
config(['database.connections.mysql.database' => 'monetix_test']);
DB::purge('mysql');

echo "DB: ".DB::connection()->getDatabaseName()."\n";

$roles = ['institute-owner','institute-admin','branch-manager','teacher','receptionist','accountant'];
echo "=== ROLE sales/purchase PERMS (monetix_test) ===\n";
foreach ($roles as $rs) {
    $role = DB::table('roles')->where('slug',$rs)->whereNull('institute_id')->first();
    if (!$role) { echo "$rs: ROLE MISSING\n"; continue; }
    $have = DB::table('role_permissions')->join('permissions','permissions.id','=','role_permissions.permission_id')
        ->where('role_id',$role->id)->whereIn('permissions.module',['sales','purchase'])->pluck('permissions.slug')->all();
    echo "$rs id={$role->id}: ".(count($have)?implode(', ', $have):'(none sales/purchase)')."\n";
}

echo "\nALL roles:\n";
foreach (DB::table('roles')->whereNull('institute_id')->get() as $r) {
    $n = DB::table('role_permissions')->where('role_id',$r->id)->count();
    echo "  {$r->slug} #{$r->id} perms=$n\n";
}

echo "\n=== PACKAGES (monetix_test) ===\n";
foreach (DB::table('subscription_packages')->get() as $p) {
    $mods = DB::table('package_modules')->where('package_id',$p->id)->where('enabled',1)->pluck('module_key')->all();
    echo "{$p->slug} #{$p->id}: ".(in_array('sales',$mods)?'sales ':'').(in_array('purchase',$mods)?'purchase ':'')."\n";
}
