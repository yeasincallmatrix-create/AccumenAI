<?php
$tables = ['subscription_packages', 'package_modules', 'package_features', 'package_scopes', 'package_scoped_modules', 'package_scoped_features', 'institute_module_overrides', 'institute_module_entitlements', 'module_registry', 'feature_registry', 'permissions'];
foreach ($tables as $t) {
    $exists = Schema::hasTable($t);
    echo ($exists ? 'YES' : 'NO') . ' | ' . $t;
    if ($exists) echo ' | rows: ' . DB::table($t)->count();
    echo PHP_EOL;
}
