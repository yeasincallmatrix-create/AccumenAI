<?php
if (Schema::hasTable('package_scopes')) {
    $count = DB::table('package_scopes')->count();
    echo 'package_scopes rows: ' . $count . PHP_EOL;
    if ($count > 0) {
        DB::table('package_scopes')->limit(10)->get()->each(fn($s) => print(json_encode($s) . PHP_EOL));
    }
} else {
    echo 'package_scopes table MISSING' . PHP_EOL;
}
