<?php

use App\Http\Middleware\CheckModuleAccess;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureAiEnabled;
use App\Http\Middleware\EnsureDomain;
use App\Http\Middleware\EnsureInstituteContext;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\PlatformMaintenance;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetFortifyGuard;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__.'/../routes/web.php',
            __DIR__.'/../routes/auth.php',
            __DIR__.'/../routes/guardian.php',
        ],
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => SetTenantContext::class,
            'permission' => CheckPermission::class,
            'module_access' => CheckModuleAccess::class,
            'setlocale' => SetLocale::class,
            'verified' => EnsureEmailIsVerified::class,
            'fortifyguard' => SetFortifyGuard::class,
            'ai.enabled' => EnsureAiEnabled::class,
            'ensure.institute.context' => EnsureInstituteContext::class,
            'force.json' => ForceJsonResponse::class,
            'platform.maintenance' => PlatformMaintenance::class,
            'domain' => EnsureDomain::class,
        ]);

        $middleware->web(append: [
            SetLocale::class,
            SecurityHeaders::class,
            PlatformMaintenance::class,
        ]);

        $middleware->api(append: [
            ForceJsonResponse::class,
            SetLocale::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('guardian*')) {
                return route('guardian.login');
            }
            if ($request->is('admin*')) {
                return route('admin.login');
            }

            return route('login');
        });

        // Tenant context must be bound BEFORE route model binding runs, or an
        // unauthenticated-to-tenant binding could resolve another institute's
        // records. SubstituteBindings is reordered after SetTenantContext.
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            SetTenantContext::class,
        );
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Flag corrupted password hashes (missing "$2y$..." prefix) before a
        // user hits the login 500. Alerts the default log channel on failure.
        $schedule->command('auth:audit-hashes')
            ->dailyAt('03:00')
            ->onFailure(fn () => report(new RuntimeException(
                'auth:audit-hashes found broken password hashes in the database',
            )))
            ->withoutOverlapping();

        // Retry failed notification deliveries within their retry budget.
        $schedule->command('notifications:retry')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        // Accounting health check — daily at 04:00, alerts on failure.
        $schedule->command('accounting:health-check')
            ->dailyAt('04:00')
            ->onFailure(fn () => report(new RuntimeException(
                'accounting:health-check detected issues — review logs immediately',
            )))
            ->withoutOverlapping();

        // Entitlement expiry & pending activation — hourly, without overlapping
        $schedule->command('entitlements:expire')
            ->hourly()
            ->withoutOverlapping();

        // SaaS bKash pending payment reconciliation — every 5 minutes
        $schedule->command('saas:verify-pending --limit=50')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        // Step 122 — Automated Database Backup: Daily at 01:00
        if (config('backup.daily.enabled', true)) {
            $dailyTime = config('backup.daily.schedule', '01:00');
            $schedule->command('database:backup', ['--type' => 'daily', '--verify'])
                ->dailyAt($dailyTime)
                ->withoutOverlapping()
                ->onFailure(fn () => report(new RuntimeException(
                    'database:backup (daily) failed — review backup logs immediately',
                )));
        }

        // Step 122 — Automated Database Backup: Weekly on Sunday at 02:00
        if (config('backup.weekly.enabled', true)) {
            $weeklyDay = config('backup.weekly.day', 'sunday');
            $weeklyTime = config('backup.weekly.schedule', '02:00');
            $schedule->command('database:backup', ['--type' => 'weekly', '--verify'])
                ->weeklyOn(match (strtolower($weeklyDay)) {
                    'sunday' => 0, 'monday' => 1, 'tuesday' => 2,
                    'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6,
                    default => 0,
                }, $weeklyTime)
                ->withoutOverlapping()
                ->onFailure(fn () => report(new RuntimeException(
                    'database:backup (weekly) failed — review backup logs immediately',
                )));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Friendly 429 — convert throttle errors to redirect-back with Retry-After message
        // instead of raw "Too Many Requests" page. Affects super-admin password reset (routes/auth.php:35 throttle:5,10)
        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, Request $request) {
            $retryAfter = $e->getHeaders()['Retry-After'] ?? 60;
            $seconds = (int) $retryAfter;
            $msg = $seconds > 60
                ? 'Too many attempts. Please try again in '.ceil($seconds/60).' minute(s).'
                : 'Too many attempts. Please try again in '.$seconds.' seconds.';
            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg, 'retry_after' => $seconds], 429)
                    ->withHeaders(['Retry-After' => (string) $seconds]);
            }
            // For web, redirect back with error bag so forgot-password.blade.php shows it
            return back()->withErrors(['email' => $msg])->withInput();
        });
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, Request $request) {
            if ($e->getStatusCode() === 429) {
                $retryAfter = $e->getHeaders()['Retry-After'] ?? 60;
                $seconds = (int) $retryAfter;
                $msg = $seconds > 60
                    ? 'Too many attempts. Please try again in '.ceil($seconds/60).' minute(s).'
                    : 'Too many attempts. Please try again in '.$seconds.' seconds.';
                if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $msg, 'retry_after' => $seconds], 429)
                        ->withHeaders(['Retry-After' => (string) $seconds]);
                }
                return back()->withErrors(['email' => $msg])->withInput();
            }
            return null;
        });
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], $e->status);
            }

            return null;
        });

        // Non-destructive fix for 419 on logout (TokenMismatchException):
        // When session/CSRF expired, the POST /logout form would show the
        // generic 419 page instead of logging out. We intercept it, clear
        // any residue, and redirect to the correct login portal.
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, Request $request) {
            $isLogout = $request->is('logout') || $request->is('*/logout') || $request->routeIs('logout') || $request->routeIs('logout.get') || $request->routeIs('guardian.logout') || $request->routeIs('guardian.logout.get');

            if ($isLogout) {
                // Best-effort session invalidation even if token mismatched
                try { $request->session()->invalidate(); } catch (\Throwable $_) {}
                try { $request->session()->regenerateToken(); } catch (\Throwable $_) {}
                try { \App\Support\TenantContext::clear(); } catch (\Throwable $_) {}
                try { \App\Support\Workspace::clear(); } catch (\Throwable $_) {}
                foreach (['web', 'institute_user', 'platform_admin', 'guardian'] as $guard) {
                    try { if (\Illuminate\Support\Facades\Auth::guard($guard)->check()) \Illuminate\Support\Facades\Auth::guard($guard)->logout(); } catch (\Throwable $_) {}
                }

                if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Session expired. Please sign in again.'], 419);
                }

                $referer = (string) $request->headers->get('referer', '');
                if (str_contains($referer, '/admin') || $request->is('admin*')) {
                    return redirect()->route('admin.login')->with('status', 'Session expired — please sign in again.');
                }
                if (str_contains($referer, '/guardian') || $request->is('guardian*')) {
                    return redirect()->route('guardian.login')->with('status', 'Session expired — please sign in again.');
                }
                if (str_contains($referer, '/institute')) {
                    return redirect()->route('institute.login')->with('status', 'Session expired — please sign in again.');
                }

                return redirect()->route('login')->with('status', 'Session expired — please sign in again.');
            }

            // For all other 419s, keep default behavior (renders errors/419.blade.php)
            // but for JSON callers return a friendly message instead of HTML.
            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Page expired — please refresh and try again.'], 419);
            }

            return null;
        });
    })->create();
