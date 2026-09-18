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
     * Resolve the institute ID from authoritative context only (SEC-05).
     *
     * Strict order: TenantContext::id() when enabled, then the session
     * workspace (Workspace::id()), otherwise null. Tenant identity must
     * NEVER come from request input — $request->input('institute_id')
     * is client-controlled and would allow audit log poisoning — so no
     * request-derived value is read here at all (neither institute_id
     * nor tenant_id, from query, body, or route). The audit table has no
     * non-authoritative forensics field, so request values are dropped
     * rather than recorded. Null (platform-admin / CLI / pre-login) is
     * correct and makes the caller skip the record.
     */
    private function resolveInstituteId(Request $request): ?int
    {
        // 1. Authoritative tenant context (set by SetTenantContext after auth).
        if (class_exists(\App\Support\TenantContext::class) && \App\Support\TenantContext::enabled()) {
            $tenantId = \App\Support\TenantContext::id();
            if ($tenantId !== null) {
                return (int) $tenantId;
            }
        }

        // 2. Session workspace (verified membership institution id).
        if (class_exists(\App\Support\Workspace::class)) {
            try {
                $workspaceId = \App\Support\Workspace::id();
            } catch (\Throwable $e) {
                $workspaceId = null;
            }
            if ($workspaceId !== null) {
                return (int) $workspaceId;
            }
        }

        // 3. No tenant context — never fall back to request input.
        return null;
    }
}
