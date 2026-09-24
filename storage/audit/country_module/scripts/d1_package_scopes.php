<?php
if (Schema::hasTable('package_scopes')) {
    $cols = DB::select('SHOW COLUMNS FROM package_scopes');
    foreach ($cols as $c) echo $c->Field . ' | ' . $c->Type . PHP_EOL;
    echo PHP_EOL . 'Scopes by country:' . PHP_EOL;
    DB::table('package_scopes')->select('country_id', DB::raw('COUNT(*) as count'))->groupBy('country_id')->get()->each(fn($r) => print('Country ' . ($r->country_id ?? 'NULL') . ': ' . $r->count . PHP_EOL));
    echo PHP_EOL . 'Joined scopes:' . PHP_EOL;
    DB::table('package_scopes')
        ->join('subscription_packages as p', 'p.id', '=', 'package_scopes.package_id')
        ->leftJoin('countries as c', 'c.id', '=', 'package_scopes.country_id')
        ->select('p.name as package', 'c.name as country', 'package_scopes.scope_hash', 'package_scopes.status', 'package_scopes.country_id', 'package_scopes.industry_id', 'package_scopes.sub_industry_id')
        ->get()
        ->each(fn($r) => print($r->package . ' | ' . ($r->country ?? 'GLOBAL') . ' | hash: ' . $r->scope_hash . ' | status: ' . $r->status . ' | c=' . ($r->country_id ?? 'G') . ' i=' . ($r->industry_id ?? 'G') . ' s=' . ($r->sub_industry_id ?? 'G') . PHP_EOL));
}
