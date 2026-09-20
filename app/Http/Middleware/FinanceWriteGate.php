<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-resource write gate for the finance module (Phase 7 STEP 3H.1).
 *
 * Deny-list: receptionist + branch-manager are blocked on manage
 * operations (teachers are already denied finance-wide by
 * DenyTeacherFromFinance). Everyone else fails OPEN (current behaviour)
 * because the role→permission grant matrix is empty in seeded data, so
 * a broad permission middleware would 403 accountants too.
 *
 * View-level stays open: journals.create/store, all index/dashboard/
 * report GETs. Gated: CoA writes (incl. create form), parties writes
 * (except index), journal post/reverse/void, payment-method writes
 * (except index + create form).
 */
class FinanceWriteGate
{
    /**
     * Roles denied on gated finance write operations.
     */
    protected array $deniedRoles = [
        'receptionist',
        'branch-manager',
    ];

    /**
     * URI patterns that require manage access.
     *
     * Value forms:
     *   'pattern'                        → gate default denied roles
     *   'pattern' => ['except' => [...]] → gate except listed GET|HEAD URIs
     *   'pattern' => ['roles' => [...]]  → gate only these roles
     */
    protected array $gatedPatterns = [
        // Chart of Accounts writes (create form GET included; index open).
        'finance/chart-of-accounts*' => [
            'except' => [
                'finance/chart-of-accounts',
                'finance/chart-of-accounts/',
            ],
        ],

        // Parties writes (index open).
        'finance/parties*' => [
            'except' => [
                'finance/parties',
                'finance/parties/',
            ],
        ],

        // Journal write actions (create/store stay open).
        'finance/journals/*/post',
        'finance/journals/*/reverse',
        'finance/journals/*/void',

        // Payment method writes (index + create form open).
        'finance/payment-methods*' => [
            'except' => [
                'finance/payment-methods',
                'finance/payment-methods/',
                'finance/payment-methods/create',
            ],
        ],

        // Accounting dashboard (URI /accounting): denied to receptionist
        // only — branch-manager keeps branch-scoped view (Phase 8 FIX 4B).
        // No other suite references this route with these roles.
        'accounting' => [
            'roles' => ['receptionist'],
        ],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $this->shouldGate($request, $user)) {
            return $next($request);
        }

        abort(403, 'Finance manage access required.');
    }

    protected function shouldGate(Request $request, $user): bool
    {
        if (! method_exists($user, 'hasRole')) {
            return false;
        }

        return $this->matchesGatedUri($request, $user);
    }

    protected function matchesGatedUri(Request $request, $user): bool
    {
        $path = $request->path();
        // Index/create-form URIs are open for GET|HEAD only. The same URI
        // with POST|PUT|PATCH|DELETE is a write (REST collection/member
        // routes share URIs: GET /=index vs POST /=store).
        $isSafeMethod = $request->isMethod('get') || $request->isMethod('head');

        foreach ($this->gatedPatterns as $key => $value) {
            $roles = $this->deniedRoles;

            if (is_array($value)) {
                $pattern = $key;
                $except = $value['except'] ?? [];

                if ($isSafeMethod && in_array($path, $except, true)) {
                    continue;
                }

                if (! empty($value['roles'])) {
                    $roles = $value['roles'];
                }
            } else {
                $pattern = $value;
            }

            if ($this->uriMatches($path, $pattern) && $this->userHasAnyRole($user, $roles)) {
                return true;
            }
        }

        return false;
    }

    protected function userHasAnyRole($user, array $roles): bool
    {
        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    protected function uriMatches(string $path, string $pattern): bool
    {
        $regex = '#^'.str_replace('\*', '.*', preg_quote($pattern, '#')).'$#';

        return (bool) preg_match($regex, $path);
    }
}
