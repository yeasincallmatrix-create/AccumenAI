<?php
DB::table('permissions')->where('slug', 'LIKE', 'medical.%')->orderBy('slug')->pluck('slug')->each(fn($p) => print($p . PHP_EOL));
echo 'Total medical.*: ' . DB::table('permissions')->where('slug', 'LIKE', 'medical.%')->count() . PHP_EOL;
echo 'pharmacy.*: ' . DB::table('permissions')->where('slug', 'LIKE', '%pharmacy%')->count() . PHP_EOL;
DB::table('permissions')->where('slug', 'LIKE', '%pharmacy%')->orderBy('slug')->pluck('slug')->each(fn($p) => print($p . PHP_EOL));
