<?php
foreach (['invoices', 'sales_invoices', 'purchase_invoices', 'bills'] as $t) {
    if (!Schema::hasTable($t)) { echo 'NO ' . $t . PHP_EOL; continue; }
    echo 'YES ' . $t . PHP_EOL;
    $cols = DB::select('SHOW COLUMNS FROM ' . $t);
    foreach ($cols as $c) {
        if (stripos($c->Field, 'tax') !== false || stripos($c->Field, 'vat') !== false || stripos($c->Field, 'country') !== false || stripos($c->Field, 'tds') !== false) {
            echo '  ' . $c->Field . ' | ' . $c->Type . PHP_EOL;
        }
    }
}
echo '--- payment methods ---' . PHP_EOL;
if (Schema::hasTable('payment_methods')) {
    $cols = Schema::getColumnListing('payment_methods');
    echo 'cols: ' . implode(', ', $cols) . PHP_EOL;
    if (in_array('country', $cols)) {
        DB::table('payment_methods')->select('country', DB::raw('COUNT(*) as count'))->groupBy('country')->get()->each(fn($r) => print(($r->country ?? 'ALL') . ': ' . $r->count . PHP_EOL));
    }
    DB::table('payment_methods')->limit(10)->get()->each(fn($r) => print(json_encode($r) . PHP_EOL));
} else {
    echo 'payment_methods MISSING' . PHP_EOL;
}
