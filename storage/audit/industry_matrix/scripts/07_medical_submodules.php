<?php
DB::table('module_registry')
    ->where('parent_key', 'medical')
    ->orderBy('sort_order')
    ->get()
    ->each(fn($m) => print($m->key . ' | ' . $m->name . ' | status: ' . $m->status . ' | coming_soon: ' . ($m->coming_soon ? 'YES' : 'NO') . PHP_EOL));
echo 'TOTAL: ' . DB::table('module_registry')->where('parent_key', 'medical')->count() . PHP_EOL;
