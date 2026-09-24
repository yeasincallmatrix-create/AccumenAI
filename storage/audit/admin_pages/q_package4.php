<?php
echo '=== ALL PACKAGES ===' . PHP_EOL;
DB::table('subscription_packages')->get()->each(fn ($p) => print($p->id . ': ' . $p->name . ' | slug: ' . $p->slug . ' | status: ' . ($p->status ?? 'n/a') . ' | price_m: ' . ($p->price_monthly ?? 'n/a') . PHP_EOL));
echo PHP_EOL . '=== PACKAGE 4 ===' . PHP_EOL;
$pkg = DB::table('subscription_packages')->where('id', 4)->first();
echo ($pkg ? json_encode($pkg, JSON_PRETTY_PRINT) : 'Package 4 NOT FOUND') . PHP_EOL;
echo PHP_EOL . '=== MODULES IN PACKAGE 4 ===' . PHP_EOL;
DB::table('package_modules')->where('package_id', 4)->orderBy('module_key')->get()->each(fn ($m) => print(($m->enabled ? '[Y]' : '[N]') . ' ' . $m->module_key . PHP_EOL));
echo 'Total pkg4 modules: ' . DB::table('package_modules')->where('package_id', 4)->count() . PHP_EOL;
echo 'Medical modules pkg4: ' . DB::table('package_modules')->where('package_id', 4)->where('module_key', 'like', 'medical%')->count() . PHP_EOL;
DB::table('package_modules')->where('package_id', 4)->where('module_key', 'like', 'medical%')->get()->each(fn ($m) => print('  medical: ' . ($m->enabled ? 'ON' : 'OFF') . ' ' . $m->module_key . PHP_EOL));
$pharma = DB::table('package_modules')->where('package_id', 4)->where('module_key', 'like', '%pharmacy%')->get();
echo 'Pharmacy in pkg4: ' . ($pharma->count() ? $pharma->pluck('module_key')->implode(', ') : 'NONE') . PHP_EOL;
echo PHP_EOL . '=== PACKAGE 4 FEATURES ===' . PHP_EOL;
if (Schema::hasTable('package_features')) {
    DB::table('package_features')->where('package_id', 4)->orderBy('feature_key')->get()->each(fn ($pf) => print(($pf->enabled ? '[Y]' : '[N]') . ' ' . $pf->feature_key . PHP_EOL));
    echo 'Total pkg4 features: ' . DB::table('package_features')->where('package_id', 4)->count() . PHP_EOL;
    $pfm = DB::table('package_features')->where('package_id', 4)->where('feature_key', 'like', 'medical%')->get();
    echo 'Medical features pkg4: ' . $pfm->count() . ' => ' . $pfm->pluck('feature_key')->implode(', ') . PHP_EOL;
} else {
    echo 'package_features MISSING' . PHP_EOL;
}
echo PHP_EOL . '=== PACKAGE SCOPES (package 4) ===' . PHP_EOL;
if (Schema::hasTable('package_scopes')) {
    $scopes = DB::table('package_scopes')->where('package_id', 4)->get();
    echo 'Count: ' . $scopes->count() . PHP_EOL;
    $scopes->each(fn ($s) => print(json_encode($s) . PHP_EOL));
}
echo PHP_EOL . '=== PACKAGE SCOPED MODULES (package 4 scopes) ===' . PHP_EOL;
if (Schema::hasTable('package_scoped_modules') && Schema::hasTable('package_scopes')) {
    $ids = DB::table('package_scopes')->where('package_id', 4)->pluck('id');
    $sm = DB::table('package_scoped_modules')->whereIn('package_scope_id', $ids)->get();
    echo 'Count: ' . $sm->count() . PHP_EOL;
    $sm->each(fn ($m) => print(($m->enabled ? '[Y]' : '[N]') . ' scope=' . $m->package_scope_id . ' ' . $m->module_key . PHP_EOL));
}
