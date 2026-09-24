<?php
echo '=== TENANTS BY PACKAGE ===' . PHP_EOL;
DB::table('institutes')->select('package_id', DB::raw('count(*) as c'))->groupBy('package_id')->get()->each(fn ($r) => print('pkg ' . ($r->package_id ?? 'NULL') . ': ' . $r->c . ' tenants' . PHP_EOL));
echo PHP_EOL . '=== TENANTS BY INDUSTRY ===' . PHP_EOL;
DB::table('institutes')->select('industry', DB::raw('count(*) as c'))->groupBy('industry')->get()->each(fn ($r) => print('industry ' . ($r->industry ?? 'NULL') . ': ' . $r->c . PHP_EOL));
echo PHP_EOL . '=== TENANTS ON PACKAGE 4 ===' . PHP_EOL;
DB::table('institutes')->where('package_id', 4)->limit(5)->get(['id', 'name', 'industry', 'sub_industry', 'sub_industry_id', 'package_id'])->each(fn ($i) => print('id=' . $i->id . ' | ' . $i->name . ' | industry: ' . ($i->industry ?? 'NULL') . ' | sub: ' . ($i->sub_industry ?? $i->sub_industry_id ?? 'NULL') . PHP_EOL));
echo PHP_EOL . '=== RESOLUTION SAMPLE (3 tenants) ===' . PHP_EOL;
$svc = app(\App\Services\ModuleAccessService::class);
DB::table('institutes')->limit(3)->get(['id', 'name', 'industry', 'sub_industry', 'package_id'])->each(function ($i) use ($svc) {
    echo 'Tenant: ' . $i->name . ' | industry: ' . ($i->industry ?? 'NULL') . ' | sub: ' . ($i->sub_industry ?? 'NULL') . ' | pkg: ' . ($i->package_id ?? 'NULL') . PHP_EOL;
    $inst = \App\Models\Institute::find($i->id);
    if ($inst) {
        $resolved = $svc->resolveEnabled($inst);
        $on = array_keys(array_filter($resolved));
        echo '  Enabled: ' . count($on) . ' modules' . PHP_EOL;
        echo '  List: ' . implode(', ', $on) . PHP_EOL;
        echo '  Sample5: ' . implode(', ', array_slice($on, 0, 5)) . PHP_EOL;
        $cached = $svc->getEnabledModules($inst);
        echo '  Cache key module_access:' . $i->id . ' warmed, cached count: ' . count($cached) . PHP_EOL;
    }
});
echo PHP_EOL . '=== PACKAGE 4 TENANT RESOLUTION ===' . PHP_EOL;
DB::table('institutes')->where('package_id', 4)->limit(2)->pluck('id')->each(function ($id) use ($svc) {
    $inst = \App\Models\Institute::find($id);
    if (!$inst) return;
    $resolved = $svc->resolveEnabled($inst);
    $on = array_keys(array_filter($resolved));
    echo 'Institute ' . $id . ' (' . $inst->name . '): ' . count($on) . ' enabled' . PHP_EOL;
    $med = array_values(array_filter($on, fn ($k) => str_starts_with($k, 'medical')));
    echo '  medical modules: ' . (count($med) ? implode(', ', $med) : 'NONE') . PHP_EOL;
});
