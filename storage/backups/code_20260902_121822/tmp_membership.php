<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Membership;

echo "=== Check if branch relationship exists on Membership ===" . PHP_EOL;
$m = new Membership();
echo "Has branch method: " . (method_exists($m, 'branch') ? 'YES' : 'NO') . PHP_EOL;

echo PHP_EOL . "=== Try calling branch() directly ===" . PHP_EOL;
try {
    $rel = $m->branch();
    echo "branch() returned: " . get_class($rel) . PHP_EOL;
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "=== Try eager loading with branch ===" . PHP_EOL;
try {
    $memberships = Membership::with(['institution', 'role', 'branch'])->where('user_id', 10)->where('status', 'active')->get();
    echo "Loaded " . $memberships->count() . " memberships" . PHP_EOL;
    foreach ($memberships as $m) {
        echo "  ID={$m->id} inst_id={$m->institution_id} branch_id=" . ($m->branch_id ?? 'NULL') . " branch=" . ($m->branch?->name ?? 'null') . PHP_EOL;
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    echo "File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo "Trace: " . $e->getTraceAsString() . PHP_EOL;
}
