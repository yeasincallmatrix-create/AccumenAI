<?php
$geo_tables = ['countries', 'geo_countries', 'geo_levels', 'geo_units', 'geo_postal_codes'];
foreach ($geo_tables as $t) {
    echo (Schema::hasTable($t) ? 'YES' : 'NO') . ' ' . $t;
    if (Schema::hasTable($t)) echo ' (' . DB::table($t)->count() . ' rows)';
    echo PHP_EOL;
}
