<?php
if (Schema::hasColumn('institutes', 'sub_industry')) {
    DB::table('institutes')
        ->where('industry', 'healthcare')
        ->select('id', 'name', 'industry', 'sub_industry', 'package_id', 'status')
        ->get()
        ->each(fn($i) => print($i->id . ' | ' . $i->name . ' | sub: ' . ($i->sub_industry ?? 'NULL') . ' | pkg: ' . ($i->package_id ?? 'NULL') . ' | status: ' . $i->status . PHP_EOL));
} else {
    DB::table('institutes')
        ->where('industry', 'healthcare')
        ->select('id', 'name', 'industry', 'package_id', 'status')
        ->get()
        ->each(fn($i) => print($i->id . ' | ' . $i->name . ' | pkg: ' . ($i->package_id ?? 'NULL') . ' | status: ' . $i->status . PHP_EOL));
}
echo 'TOTAL healthcare: ' . DB::table('institutes')->where('industry', 'healthcare')->count() . PHP_EOL;
