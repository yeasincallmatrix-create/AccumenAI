<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Membership;
use Illuminate\Database\Eloquent\Relations\Relation;

echo "=== Test Relation::noConstraints (same as Builder::getRelation) ===" . PHP_EOL;
try {
    $rel = Relation::noConstraints(function () {
        $instance = (new Membership)->newInstance();
        echo "  Instance class: " . get_class($instance) . PHP_EOL;
        echo "  Has branch method: " . (method_exists($instance, 'branch') ? 'YES' : 'NO') . PHP_EOL;
        return $instance->branch();
    });
    echo "  Result type: " . get_class($rel) . PHP_EOL;
} catch (\Throwable $e) {
    echo "  ERROR: " . get_class($e) . ": " . $e->getMessage() . PHP_EOL;
    echo "  File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
}

echo PHP_EOL . "=== Check if Branch can be resolved ===" . PHP_EOL;
try {
    $b = new \App\Models\Branch();
    echo "  Branch instance: " . get_class($b) . PHP_EOL;
} catch (\Throwable $e) {
    echo "  ERROR: " . get_class($e) . ": " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "=== Check Branch boot (TenantScoped) ===" . PHP_EOL;
try {
    \App\Support\TenantContext::set(42);
    $b = \App\Models\Branch::query()->first();
    echo "  Branch query OK: " . ($b ? $b->name : 'no results') . PHP_EOL;
    \App\Support\TenantContext::clear();
} catch (\Throwable $e) {
    echo "  ERROR: " . get_class($e) . ": " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "=== Simulate exact login flow ===" . PHP_EOL;
// Set tenant context like SetTenantContext middleware does for User guard
Illuminate\Support\Facades\Auth::loginUsingId(10);
$user = auth()->user();

// Get workspace
$workspaceId = session('active_institution_id');
if (!$workspaceId) {
    $fallback = DB::table('institution_user')
        ->where('user_id', $user->id)
        ->where('status', 'active')
        ->whereNull('deleted_at')
        ->orderBy('institution_id')
        ->first();
    if ($fallback) {
        $workspaceId = (int) $fallback->institution_id;
        session(['active_institution_id' => $workspaceId]);
    }
}
\App\Support\TenantContext::set($workspaceId);
$membership = \App\Support\Workspace::membership();
\App\Support\BranchContext::set($membership?->branch_id);

echo "  Workspace: $workspaceId" . PHP_EOL;
echo "  BranchContext: " . (\App\Support\BranchContext::enabled() ? \App\Support\BranchContext::id() : 'disabled') . PHP_EOL;

try {
    $memberships = Membership::query()
        ->where('user_id', $user->id)
        ->where('status', 'active')
        ->with(['institution', 'role', 'branch'])
        ->orderBy('institution_id')
        ->get();
    echo "  SUCCESS: " . $memberships->count() . " memberships loaded" . PHP_EOL;
} catch (\Throwable $e) {
    echo "  ERROR: " . get_class($e) . ": " . $e->getMessage() . PHP_EOL;
    echo "  File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
}
