<?php
$t = ['country_tax_configs', 'country_taxes', 'tax_rates', 'tax_regions', 'country_tax_rates', 'institute_tax_settings', 'tax_settings'];
foreach ($t as $name) {
    echo (Schema::hasTable($name) ? 'YES' : 'NO') . ' ' . $name;
    if (Schema::hasTable($name)) echo ' (' . DB::table($name)->count() . ' rows)';
    echo PHP_EOL;
}
echo '--- tax_rates by country ---' . PHP_EOL;
if (Schema::hasTable('tax_rates')) {
    $cols = Schema::getColumnListing('tax_rates');
    echo 'tax_rates columns: ' . implode(', ', $cols) . PHP_EOL;
    if (in_array('country_id', $cols)) {
        DB::table('tax_rates')->select('country_id', DB::raw('COUNT(*) as count'))->groupBy('country_id')->get()->each(fn($r) => print('Country ' . $r->country_id . ': ' . $r->count . ' rates' . PHP_EOL));
    } elseif (in_array('country', $cols)) {
        DB::table('tax_rates')->select('country', DB::raw('COUNT(*) as count'))->groupBy('country')->get()->each(fn($r) => print(($r->country ?? 'NULL') . ': ' . $r->count . ' rates' . PHP_EOL));
    }
}
echo '--- country_tax_configs full ---' . PHP_EOL;
if (Schema::hasTable('country_tax_configs')) {
    DB::table('country_tax_configs')->get()->each(fn($c) => print(json_encode($c) . PHP_EOL));
} else {
    echo 'country_tax_configs MISSING' . PHP_EOL;
}
