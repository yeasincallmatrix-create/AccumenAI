<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PlatformMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = Setting::get('app.maintenance', '0');
        if ($enabled !== '1' && $enabled !== 1 && $enabled !== true) {
            return $next($request);
        }

        $allowAdmin = Setting::get('app.maintenance_allow_admin', '1');
        if ($allowAdmin === '1' || $allowAdmin === 1 || $allowAdmin === true) {
            if ($request->user('platform_admin') || auth('platform_admin')->check()) {
                return $next($request);
            }
            // Also allow super-admin routes by guard check without user? fallback to session guard
            if ($request->routeIs('admin.*', 'super-admin.*', 'admin.login', 'login')) {
                // If unauthenticated super-admin trying to login, allow
                if ($request->routeIs('admin.login*') || $request->routeIs('login')) {
                    return $next($request);
                }
            }
        }

        // Return 503 maintenance response, not locking API JSON differently
        $message = Setting::get('app.maintenance_message', '') ?: 'The platform is under maintenance. Please try again shortly.';
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $message, 'maintenance' => true], 503);
        }

        return response()->view('errors.maintenance', ['message' => $message], 503);
    }
}
