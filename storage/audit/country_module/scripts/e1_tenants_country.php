<?php
$cols = DB::select('SHOW COLUMNS FROM institutes');
foreach ($cols as $c) {
    if (stripos($c->Field, 'country') !== false || stripos($c->Field, 'region') !== false || stripos($c->Field, 'currency') !== false || stripos($c->Field, 'locale') !== false || stripos($c->Field, 'vat') !== false || stripos($c->Field, 'tax') !== false) {
        echo $c->Field . ' | ' . $c->Type . ' | Null: ' . $c->Null . ' | Default: ' . $c->Default . PHP_EOL;
    }
}
echo '--- tenants by country ---' . PHP_EOL;
DB::table('institutes')->select('country', DB::raw('COUNT(*) as count'))->groupBy('country')->get()->each(fn($r) => print(($r->country ?? 'NULL') . ': ' . $r->count . PHP_EOL));
echo '--- country x industry ---' . PHP_EOL;
DB::table('institutes')->select('country', 'industry', DB::raw('COUNT(*) as count'))->groupBy('country', 'industry')->get()->each(fn($r) => print(($r->country ?? 'NULL') . ' | ' . $r->industry . ': ' . $r->count . PHP_EOL));
echo '--- country_id distribution ---' . PHP_EOL;
DB::table('institutes')->select('country_id', DB::raw('COUNT(*) as count'))->groupBy('country_id')->get()->each(fn($r) => print('country_id=' . ($r->country_id ?? 'NULL') . ': ' . $r->count . PHP_EOL));
