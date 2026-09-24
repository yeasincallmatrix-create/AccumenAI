<?php
echo 'config(country_modules): ' . var_export(config('country_modules') ? array_keys((array)config('country_modules')) : null, true) . PHP_EOL;
echo 'config(country-tax): ' . (config('country-tax') ? 'EXISTS' : (config('country_tax') ? 'EXISTS(underscore)' : 'MISSING')) . PHP_EOL;
echo 'config(tax): ' . (config('tax') ? 'EXISTS keys: ' . implode(',', array_keys((array)config('tax'))) : 'MISSING') . PHP_EOL;
echo 'config(industry-modules) industries: ' . count(config('industry-modules', [])) . PHP_EOL;
echo PHP_EOL;
if (Schema::hasTable('institute_module_overrides')) {
    echo 'VAT overrides by country/industry:' . PHP_EOL;
    DB::table('institutes')
        ->select('country', 'industry', DB::raw('COUNT(*) as count'))
        ->whereIn('id', function ($q) {
            $q->select('institute_id')->from('institute_module_overrides')->where('module_key', 'LIKE', '%vat%')->where('enabled', true);
        })
        ->groupBy('country', 'industry')
        ->get()
        ->each(fn($r) => print(($r->country ?? 'NULL') . ' | ' . $r->industry . ': ' . $r->count . ' VAT-enabled' . PHP_EOL));
    echo 'All vat/tax module overrides:' . PHP_EOL;
    DB::table('institute_module_overrides')->where('module_key', 'LIKE', '%vat%')->orWhere('module_key', 'LIKE', '%tax%')->get()->each(fn($r) => print(json_encode($r) . PHP_EOL));
}
echo PHP_EOL . 'package_modules vat/tax:' . PHP_EOL;
if (Schema::hasTable('package_modules')) {
    DB::table('package_modules')->where('module_key', 'LIKE', '%vat%')->orWhere('module_key', 'LIKE', '%tax%')->get()->each(fn($r) => print('pkg' . $r->package_id . ' | ' . $r->module_key . ' | ' . ($r->enabled ? 'ON' : 'OFF') . PHP_EOL));
}
