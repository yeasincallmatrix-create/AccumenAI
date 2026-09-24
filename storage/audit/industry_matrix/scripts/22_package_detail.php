<?php
echo 'FREE medical: ' . DB::table('package_modules')->where('package_id', 1)->where('module_key', 'LIKE', 'medical%')->count() . PHP_EOL;
echo 'BASIC medical: ' . DB::table('package_modules')->where('package_id', 2)->where('module_key', 'LIKE', 'medical%')->count() . PHP_EOL;
DB::table('package_modules')->where('package_id', 2)->where('module_key', 'LIKE', 'medical%')->get()->each(fn($r) => print('BASIC ' . $r->module_key . ' | ' . ($r->enabled ? 'ON' : 'OFF') . PHP_EOL));
DB::table('package_modules')->where('package_id', 1)->where('module_key', 'LIKE', 'medical%')->get()->each(fn($r) => print('FREE ' . $r->module_key . ' | ' . ($r->enabled ? 'ON' : 'OFF') . PHP_EOL));
echo '--- package_features medical/pharmacy ---' . PHP_EOL;
DB::table('package_features')->where('feature_key', 'LIKE', 'medical%')->orderBy('package_id')->orderBy('feature_key')->get()->each(fn($r) => print('pkg' . $r->package_id . ' | ' . $r->feature_key . ' | ' . ($r->enabled ? 'ON' : 'OFF') . PHP_EOL));
echo '--- scoped modules/features ---' . PHP_EOL;
echo 'package_scoped_modules: ' . DB::table('package_scoped_modules')->count() . PHP_EOL;
echo 'package_scoped_features: ' . DB::table('package_scoped_features')->count() . PHP_EOL;
echo '--- healthcare industry_id/sub_industry_id ---' . PHP_EOL;
DB::table('institutes')->where('industry', 'healthcare')->get(['id', 'name', 'industry_id', 'sub_industry_id', 'sub_industry', 'package_id'])->each(fn($i) => print($i->id . ' | ' . $i->name . ' | industry_id: ' . ($i->industry_id ?? 'NULL') . ' | sub_industry_id: ' . ($i->sub_industry_id ?? 'NULL') . ' | sub: ' . ($i->sub_industry ?? 'NULL') . ' | pkg: ' . ($i->package_id ?? 'NULL') . PHP_EOL));
