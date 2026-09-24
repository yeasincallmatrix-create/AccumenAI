<?php
foreach (['%tax%', '%vat%', '%tds%', '%duty%', '%gst%', '%sales_tax%'] as $pat) {
    $tables = DB::select('SHOW TABLES LIKE ' . DB::getPdo()->quote($pat));
    foreach ($tables as $t) {
        foreach ((array)$t as $name) {
            $count = Schema::hasTable($name) ? DB::table($name)->count() : -1;
            echo 'YES ' . $name . ' (' . $count . ' rows)' . PHP_EOL;
        }
    }
}
