<?php
foreach (['industries', 'sub_industries'] as $t) {
    if (Schema::hasTable($t)) {
        echo 'YES ' . $t . ' rows: ' . DB::table($t)->count() . PHP_EOL;
        if ($t === 'industries') {
            DB::table($t)->orderBy('id')->get(['id', 'slug', 'name', 'status'])->each(fn($r) => print($r->id . ' | ' . $r->slug . ' | ' . $r->name . ' | ' . $r->status . PHP_EOL));
        }
        if ($t === 'sub_industries') {
            DB::table($t)->select('industry_id', DB::raw('COUNT(*) as c'))->groupBy('industry_id')->get()->each(fn($r) => print('industry_id=' . $r->industry_id . ' | ' . $r->c . PHP_EOL));
            DB::table($t)->where('slug', 'pharmacy')->orWhere('slug', 'clinic')->orWhere('slug', 'hospital')->get()->each(fn($r) => print(json_encode($r) . PHP_EOL));
        }
    } else {
        echo 'NO ' . $t . PHP_EOL;
    }
}
