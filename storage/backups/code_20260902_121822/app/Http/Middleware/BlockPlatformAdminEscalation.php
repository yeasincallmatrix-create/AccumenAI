<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockPlatformAdminEscalation
{
    private const BLOCKED_KEYS = ['is_owner', 'singleton_guard', 'super_admin', 'platform_admin', 'guard', 'role'];

    public function handle(Request $request, Closure $next): Response
    {
        // Block staff attempting to escalate via platform_admins routes or staff update
        $payload = $request->all();
        foreach (self::BLOCKED_KEYS as $key) {
            if (array_key_exists($key, $payload)) {
                // Only allow PlatformAdmin updating own safe fields; block is_owner/super_admin injection
                if (in_array($key, ['is_owner', 'singleton_guard', 'super_admin', 'platform_admin'], true)) {
                    try {
                        \App\Models\PlatformAuditLog::record('security', $key, 'blocked_escalation_attempt', [
                            'path' => $request->path(),
                            'key' => $key,
                            'ip' => $request->ip(),
                        ]);
                    } catch (\Throwable $e) {}
                    return response()->json(['message' => 'Privilege escalation blocked.'], 403);
                }
            }
        }
        return $next($request);
    }
}
