<?php
echo '=== MODULE_REGISTRY ===' . PHP_EOL;
DB::table('module_registry')->orderBy('sort_order')->get()->each(function ($m) {
    echo ($m->parent_key ?? 'ROOT') . ' -> ' . $m->key . ' | ' . $m->name . ' | type: ' . $m->type . ' | status: ' . $m->status . ' | coming_soon: ' . (($m->coming_soon ?? false) ? 'YES' : 'NO') . ' | sort: ' . $m->sort_order . PHP_EOL;
});
echo 'Total: ' . DB::table('module_registry')->count() . PHP_EOL;
echo 'Active: ' . DB::table('module_registry')->where('status', 'active')->count() . PHP_EOL;
echo 'Inactive: ' . DB::table('module_registry')->where('status', 'inactive')->count() . PHP_EOL;
echo 'Coming soon: ' . DB::table('module_registry')->where('coming_soon', 1)->count() . PHP_EOL;
echo 'Types: ' . json_encode(DB::table('module_registry')->select('type', DB::raw('count(*) c'))->groupBy('type')->get()) . PHP_EOL;
echo PHP_EOL . '=== INDUSTRIES ===' . PHP_EOL;
if (Schema::hasTable('industries')) {
    DB::table('industries')->orderBy('sort_order')->get()->each(fn ($i) => print(json_encode($i) . PHP_EOL));
    echo 'Total: ' . DB::table('industries')->count() . PHP_EOL;
} else {
    echo 'industries table MISSING' . PHP_EOL;
}
echo PHP_EOL . '=== SUB-INDUSTRIES ===' . PHP_EOL;
if (Schema::hasTable('sub_industries')) {
    DB::table('sub_industries')->orderBy('industry_id')->get()->each(fn ($s) => print(json_encode($s) . PHP_EOL));
    echo 'Total sub-industries: ' . DB::table('sub_industries')->count() . PHP_EOL;
} else {
    echo 'sub_industries MISSING' . PHP_EOL;
}
