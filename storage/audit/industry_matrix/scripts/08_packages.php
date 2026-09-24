<?php
DB::table('subscription_packages')->orderBy('id')->get()->each(fn($p) => print($p->id . ' | ' . $p->name . ' | ' . $p->slug . ' | status: ' . $p->status . PHP_EOL));
echo 'TOTAL: ' . DB::table('subscription_packages')->count() . PHP_EOL;
