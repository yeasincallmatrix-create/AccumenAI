<?php
if (Schema::hasTable('institute_settings')) {
    $cols = DB::select('SHOW COLUMNS FROM institute_settings');
    $found = false;
    foreach ($cols as $c) {
        if (stripos($c->Field, 'tax') !== false || stripos($c->Field, 'vat') !== false) {
            echo $c->Field . ' | ' . $c->Type . PHP_EOL;
            $found = true;
        }
    }
    if (!$found) echo 'No tax/vat columns in institute_settings' . PHP_EOL;
    echo 'columns all: ' . implode(', ', array_map(fn($c) => $c->Field, $cols)) . PHP_EOL;
} else {
    echo 'institute_settings MISSING' . PHP_EOL;
}
echo '--- accounting settings table ---' . PHP_EOL;
foreach (['accounting_settings', 'institute_accounting_settings', 'settings', 'tenant_settings'] as $t) {
    echo (Schema::hasTable($t) ? 'YES' : 'NO') . ' ' . $t;
    if (Schema::hasTable($t)) echo ' (' . DB::table($t)->count() . ' rows)';
    echo PHP_EOL;
}
if (Schema::hasTable('accounting_settings')) {
    $cols = Schema::getColumnListing('accounting_settings');
    echo 'accounting_settings cols: ' . implode(', ', $cols) . PHP_EOL;
    DB::table('accounting_settings')->limit(10)->get()->each(fn($r) => print(json_encode($r) . PHP_EOL));
}
