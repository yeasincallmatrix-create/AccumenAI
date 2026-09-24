<?php
if (Schema::hasColumn('institutes', 'sub_industry')) {
    DB::table('institutes')->select('industry', 'sub_industry', DB::raw('COUNT(*) as count'))->groupBy('industry', 'sub_industry')->get()->each(fn($r) => print($r->industry . '.' . ($r->sub_industry ?? 'NULL') . ' | ' . $r->count . PHP_EOL));
    echo 'SET: ' . DB::table('institutes')->whereNotNull('sub_industry')->count() . PHP_EOL;
    echo 'NULL: ' . DB::table('institutes')->whereNull('sub_industry')->count() . PHP_EOL;
} else {
    echo 'sub_industry column MISSING' . PHP_EOL;
}
