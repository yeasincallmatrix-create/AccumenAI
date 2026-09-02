<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Simulate what happens during login: set user context without full middleware
Illuminate\Support\Facades\Auth::loginUsingId(10);

// Simulate what SetTenantContext does for User guard
$user = auth()->user();
echo "User type: " . get_class($user) . PHP_EOL;

$workspaceId = \App\Support\Workspace::id();
echo "Workspace ID: " . ($workspaceId ?? 'null') . PHP_EOL;

if ($workspaceId === null) {
    // Simulate the auto-resolve from SetTenantContext
    $fallback = DB::table('institution_user')
        ->where('user_id', $user->id)
        ->where('status', 'active')
        ->whereNull('deleted_at')
        ->orderBy('institution_id')
        ->first();
    if ($fallback) {
        $workspaceId = (int) $fallback->institution_id;
        \App\Support\Workspace::set($workspaceId);
    }
}

echo "Workspace ID after resolve: " . ($workspaceId ?? 'null') . PHP_EOL;

// Now try what AppServiceProvider does
echo PHP_EOL . "=== Attempting the exact query from AppServiceProvider ===" . PHP_EOL;
try {
    $memberships = \App\Models\Membership::query()
        ->where('user_id', $user->id)
        ->where('status', 'active')
        ->with(['institution', 'role', 'branch'])
        ->orderBy('institution_id')
        ->get();
    echo "SUCCESS: Loaded " . $memberships->count() . " memberships" . PHP_EOL;
    foreach ($memberships as $m) {
        echo "  ID={$m->id} inst={$m->institution_id} branch_id=" . ($m->branch_id ?? 'NULL') . PHP_EOL;
    }
} catch (\Throwable $e) {
    echo "ERROR: " . get_class($e) . ": " . $e->getMessage() . PHP_EOL;
    echo "File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
}
