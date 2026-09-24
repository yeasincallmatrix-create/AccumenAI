<?php
// Scenario L1: Bangladesh tenant VAT
echo '=== Scenario 1: Bangladesh tenant VAT ===' . PHP_EOL;
$inst = DB::table('institutes')->where('country', 'Bangladesh')->first();
if ($inst) {
    echo 'Tenant: ' . $inst->name . ' | country: ' . $inst->country . ' | industry: ' . $inst->industry . ' | package: ' . ($inst->package_id ?? 'NULL') . PHP_EOL;
    $model = \App\Models\Institute::find($inst->id);
    if ($model) {
        $svc = app(\App\Services\ModuleAccessService::class);
        $enabled = $svc->resolveEnabled($model);
        echo 'VAT in enabled: ' . (in_array('vat', $enabled) ? 'YES' : 'NO') . PHP_EOL;
        echo 'tax in enabled: ' . (in_array('tax', $enabled) ? 'YES' : 'NO') . PHP_EOL;
        echo 'sales in enabled: ' . (in_array('sales', $enabled) ? 'YES' : 'NO') . PHP_EOL;
        echo 'Enabled count: ' . count($enabled) . PHP_EOL;
        echo 'Enabled modules: ' . implode(', ', array_keys(array_filter($enabled))) . PHP_EOL;
    }
} else {
    echo 'No Bangladesh tenant found' . PHP_EOL;
}
echo PHP_EOL . '=== Scenario 2: USA tenant ===' . PHP_EOL;
$inst = DB::table('institutes')->where('country', 'United States')->first();
if ($inst) {
    echo 'Tenant: ' . $inst->name . ' | country: ' . $inst->country . PHP_EOL;
    $model = \App\Models\Institute::find($inst->id);
    if ($model) {
        $enabled = app(\App\Services\ModuleAccessService::class)->resolveEnabled($model);
        echo 'sales_tax in enabled: ' . (in_array('sales_tax', $enabled) ? 'YES' : 'NO') . PHP_EOL;
        echo 'vat in enabled: ' . (in_array('vat', $enabled) ? 'YES' : 'NO') . PHP_EOL;
        echo 'Enabled: ' . implode(', ', array_keys(array_filter($enabled))) . PHP_EOL;
    }
} else {
    echo 'No USA tenant found' . PHP_EOL;
}
echo PHP_EOL . '=== Scenario 3: All tenants VAT status ===' . PHP_EOL;
$svc = app(\App\Services\ModuleAccessService::class);
DB::table('institutes')->orderBy('id')->get(['id', 'name', 'country', 'industry', 'package_id', 'status'])->each(function ($i) use ($svc) {
    $model = \App\Models\Institute::find($i->id);
    if (!$model) return;
    $enabled = array_keys(array_filter($svc->resolveEnabled($model)));
    echo $i->id . ' | ' . $i->name . ' | ' . ($i->country ?? 'NULL') . ' | ' . $i->industry . ' | vat=' . (in_array('vat', $enabled) ? 'Y' : 'N') . ' | sales=' . (in_array('sales', $enabled) ? 'Y' : 'N') . ' | purchase=' . (in_array('purchase', $enabled) ? 'Y' : 'N') . PHP_EOL;
});
