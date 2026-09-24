<?php
DB::table('package_modules as pm')
    ->join('subscription_packages as p', 'p.id', '=', 'pm.package_id')
    ->where('pm.module_key', 'LIKE', 'medical%')
    ->orderBy('pm.package_id')
    ->orderBy('pm.module_key')
    ->get(['p.name as package_name', 'pm.module_key', 'pm.enabled'])
    ->each(fn($r) => print($r->package_name . ' | ' . $r->module_key . ' | ' . ($r->enabled ? 'ON' : 'OFF') . PHP_EOL));
