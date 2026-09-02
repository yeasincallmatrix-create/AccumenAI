<?php

namespace App\Http\Middleware;

use App\Models\AccountingAuditTrail;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * STEP 99 — Audit Activity Log Middleware.
 *
 * Logs significant user actions to the accounting_audit_trails table:
 * authentication events, permission denials, and module access attempts.
 */
class AuditActivityLog
{
    /**
     * Actions that should be recorded in the audit log.
     */
    private const LOGGED_ACTIONS = [
        'login',
        'logout',
        'failed_login',
        'permission_denied',
        'module_access',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $this->logActivity($request, $response);

        return $response;
    }

    /**
     * Log the request activity if it matches a trackable action.
     */
    private function logActivity(Request $request, Response $response): void
    {
        $action = $this->resolveAction($request, $response);

        if ($action === null) {
            return;
        }

        $user = $request->user();
        $instituteId = $this->resolveInstituteId($request);

        if ($instituteId === null) {
            return;
        }

        AccountingAuditTrail::create([
            'institute_id' => $instituteId,
            'actor_type' => $user !== null ? 'user' : 'guest',
            'actor_id' => $user?->id,
            'action' => $action,
            'entity_type' => $this->resolveEntityType($request),
            'entity_id' => null,
            'before_payload' => null,
            'after_payload' => [
                'method' => $request->method(),
                'path' => $request->path(),
                'status_code' => $response->getStatusCode(),
                'ip' => $request->ip(),
            ],
            'branch_id' => null,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Determine the audit action from the request/response pair.
     */
    private function resolveAction(Request $request, Response $response): ?string
    {
        $path = $request->path();
        $method = $request->method();

        // Authentication events
        if ($path === 'login' && $method === 'POST') {
            return $response->getStatusCode() === 302 ? 'login' : 'failed_login';
        }

        if ($path === 'logout' && $method === 'POST') {
            return 'logout';
        }

        // Permission denied (403)
        if ($response->getStatusCode() === 403) {
            return 'permission_denied';
        }

        // Module access attempts (prefix-based)
        if (in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])) {
            $modulePrefixes = [
                'students' => 'students',
                'teachers' => 'teachers',
                'courses' => 'courses',
                'finance' => 'finance',
                'sales' => 'sales',
                'purchase' => 'purchase',
                'hr' => 'hr',
                'inventory' => 'inventory',
                'crm' => 'crm',
                'accounting' => 'accounting',
            ];

            foreach ($modulePrefixes as $prefix => $module) {
                if (str_starts_with($path, $prefix)) {
                    return 'module_access:' . $module;
                }
            }
        }

        return null;
    }

    /**
     * Resolve the entity type from the request path.
     */
    private function resolveEntityType(Request $request): string
    {
        $segments = explode('/', $request->path());

        return $segments[0] ?? 'unknown';
    }

    /**
     * Resolve the institute ID from the request context.
     */
    private function resolveInstituteId(Request $request): ?int
    {
        $user = $request->user();

        if (method_exists($user, 'institute_id')) {
            return (int) $user->institute_id;
        }

        if (app()->bound('request') && $request->has('institute_id')) {
            return (int) $request->input('institute_id');
        }

        // Try to resolve from tenant context
        if (class_exists(\App\Support\TenantContext::class)) {
            $tenantId = \App\Support\TenantContext::id();
            if ($tenantId !== null) {
                return (int) $tenantId;
            }
        }

        return null;
    }
}
