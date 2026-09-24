<?php
if (Schema::hasTable('countries')) {
    $cols = DB::select('SHOW COLUMNS FROM countries');
    foreach ($cols as $c) echo $c->Field . ' | ' . $c->Type . ' | ' . $c->Null . ' | ' . $c->Default . PHP_EOL;
    echo 'Total countries: ' . DB::table('countries')->count() . PHP_EOL;
    echo PHP_EOL . 'Sample:' . PHP_EOL;
    DB::table('countries')->limit(10)->get()->each(fn($c) => print(json_encode($c) . PHP_EOL));
} else {
    echo 'countries table MISSING' . PHP_EOL;
}
