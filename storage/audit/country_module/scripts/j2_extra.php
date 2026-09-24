<?php
foreach (['payment_gateways', 'institute_payment_gateways'] as $t) {
    if (Schema::hasTable($t)) {
        echo 'YES ' . $t . ' (' . DB::table($t)->count() . ' rows)' . PHP_EOL;
        $cols = Schema::getColumnListing($t);
        echo 'cols: ' . implode(', ', $cols) . PHP_EOL;
        foreach ($cols as $c) {
            if (stripos($c, 'country') !== false || stripos($c, 'region') !== false) {
                echo '  country-col: ' . $c . PHP_EOL;
            }
        }
        DB::table($t)->limit(5)->get()->each(fn($r) => print(json_encode($r) . PHP_EOL));
    } else {
        echo 'NO ' . $t . PHP_EOL;
    }
}
echo '--- tax_jurisdictions ---' . PHP_EOL;
if (Schema::hasTable('tax_jurisdictions')) {
    echo 'rows: ' . DB::table('tax_jurisdictions')->count() . PHP_EOL;
    DB::table('tax_jurisdictions')->limit(10)->get()->each(fn($r) => print(json_encode($r) . PHP_EOL));
}
echo '--- country_currency_map ---' . PHP_EOL;
if (Schema::hasTable('country_currency_map')) {
    echo 'rows: ' . DB::table('country_currency_map')->count() . PHP_EOL;
    DB::table('country_currency_map')->limit(5)->get()->each(fn($r) => print(json_encode($r) . PHP_EOL));
}
echo '--- package_modules FREE vat etc ---' . PHP_EOL;
DB::table('package_modules')->whereIn('package_id', [1,2,3,4])->whereIn('module_key', ['vat', 'tds', 'tax', 'sales', 'purchase'])->orderBy('package_id')->orderBy('module_key')->get()->each(fn($r) => print('pkg' . $r->package_id . ' | ' . $r->module_key . ' | ' . ($r->enabled ? 'ON' : 'OFF') . PHP_EOL));
echo '--- feature_registry vat/tax ---' . PHP_EOL;
DB::table('feature_registry')->where('feature_key', 'LIKE', '%vat%')->orWhere('feature_key', 'LIKE', '%tax%')->get()->each(fn($f) => print($f->module_key . ' | ' . $f->feature_key . ' | ' . $f->name . ' | ' . $f->status . PHP_EOL));
echo '--- permissions vat/tax ---' . PHP_EOL;
DB::table('permissions')->where('slug', 'LIKE', '%vat%')->orWhere('slug', 'LIKE', 'tax.%')->orWhere('slug', 'LIKE', 'tds.%')->count();
echo 'count vat/tax/tds perms: ' . DB::table('permissions')->where('slug', 'LIKE', '%vat%')->orWhere('slug', 'LIKE', 'tax.%')->orWhere('slug', 'LIKE', 'tds.%')->count() . PHP_EOL;
DB::table('permissions')->where('slug', 'LIKE', '%vat%')->orWhere('slug', 'LIKE', 'tax.%')->orderBy('slug')->limit(30)->pluck('slug')->each(fn($p) => print($p . PHP_EOL));
