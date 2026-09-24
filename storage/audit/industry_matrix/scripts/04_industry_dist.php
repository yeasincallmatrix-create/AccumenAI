<?php
DB::table('institutes')->select('industry', DB::raw('COUNT(*) as count'))->groupBy('industry')->get()->each(fn($r) => print($r->industry . ' | ' . $r->count . PHP_EOL));
echo 'TOTAL: ' . DB::table('institutes')->count() . PHP_EOL;
