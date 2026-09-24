<?php
$out = [];
$out['package_tables'] = \DB::select("SHOW TABLES LIKE 'package%'");
$out['subcat_tables'] = \DB::select("SHOW TABLES LIKE '%subcat%'");
$out['industry_tables'] = \DB::select("SHOW TABLES LIKE '%industry%'");
$out['institutes_package_cols'] = \DB::select("SHOW COLUMNS FROM institutes LIKE '%package%'");
$out['sub_industries_cols'] = \DB::select("SHOW COLUMNS FROM sub_industries");
$out['sub_industries_count'] = \DB::table('sub_industries')->count();
$out['package_scope_cols'] = \DB::select("SHOW COLUMNS FROM package_scopes");
$out['module_registry_count'] = \DB::table('module_registry')->count();
$out['tax_modules'] = \DB::select("SELECT `key`, parent_key, type, is_core, status FROM module_registry WHERE `key` LIKE 'tax%' OR `key` LIKE '%vat%' OR `key` LIKE '%gst%' OR `key` LIKE '%tds%' OR `key` LIKE '%invoice%' OR `key` LIKE '%billing%'");
$out['core_modules'] = \DB::select("SELECT `key`, type, is_core, status FROM module_registry WHERE type='core' ORDER BY `key`");
file_put_contents(__DIR__ . '/../q11_q13_raw.txt', print_r($out, true));
echo "OK\n";
