<?php
echo 'feature_registry columns: ';
print_r(array_map(fn($c) => $c->Field, Schema::getColumnListing('feature_registry') ? array_map(fn($f) => (object)['Field' => $f], Schema::getColumnListing('feature_registry')) : []));
echo PHP_EOL;
DB::table('feature_registry')
    ->where('feature_key', 'LIKE', 'medical%')
    ->orWhere('feature_key', 'LIKE', 'education%')
    ->orWhere('feature_key', 'LIKE', 'training%')
    ->orWhere('feature_key', 'LIKE', 'pharmacy%')
    ->orderBy('module_key')
    ->orderBy('feature_key')
    ->get()
    ->each(fn($f) => print($f->module_key . ' | ' . $f->feature_key . ' | ' . $f->name . ' | ' . $f->status . PHP_EOL));
echo 'TOTAL medical.*: ' . DB::table('feature_registry')->where('feature_key', 'LIKE', 'medical%')->count() . PHP_EOL;
echo 'TOTAL pharmacy*: ' . DB::table('feature_registry')->where('feature_key', 'LIKE', '%pharmacy%')->count() . PHP_EOL;
