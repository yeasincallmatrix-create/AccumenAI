<?php
DB::table('module_registry')
    ->where('key', 'LIKE', '%vat%')
    ->orWhere('key', 'LIKE', '%tax%')
    ->orWhere('key', 'LIKE', '%tds%')
    ->orWhere('name', 'LIKE', '%vat%')
    ->orWhere('name', 'LIKE', '%tax%')
    ->get()
    ->each(fn($m) => print(($m->parent_key ?? 'ROOT') . ' → ' . $m->key . ' | ' . $m->name . ' | type: ' . $m->type . ' | status: ' . $m->status . PHP_EOL));
echo '--- core vat check ---' . PHP_EOL;
echo 'vat in industry-modules.core: ' . (in_array('vat', config('industry-modules.core', [])) ? 'YES' : 'NO') . PHP_EOL;
