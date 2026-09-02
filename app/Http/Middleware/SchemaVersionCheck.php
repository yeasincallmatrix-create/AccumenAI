<?php

namespace App\Http\Middleware;

use App\Services\System\SchemaVersionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

/**
 * Step 103 — Schema Version Protection Middleware
 * Shows admin warning if DB schema mismatched, does not block normal users.
 */
class SchemaVersionCheck
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($request->user() && $this->isSuperAdmin($request->user())) {
            $service = app(SchemaVersionService::class);
            $compare = $service->compare();
            if ($compare['mismatch']) {
                // Share warning with views
                View::share('schemaVersionWarning', 'Database schema update required — '.$compare['pending_count'].' pending migrations');
                // Also set header for debugging
                if (method_exists($response, 'header')) {
                    $response->header('X-Schema-Warning', 'update required');
                }
            }
        }

        return $response;
    }

    private function isSuperAdmin($user): bool
    {
        // Platform admin or institute owner with platform access
        if ($user instanceof \App\Models\PlatformAdmin) return true;
        if (method_exists($user, 'hasRole')) {
            try { return $user->hasRole('platform-admin') || $user->hasPermission('system.manage'); } catch (\Throwable $e) {}
        }
        // Check via auth guard platform_admin
        if (auth('platform_admin')->check()) return true;
        return false;
    }
}
