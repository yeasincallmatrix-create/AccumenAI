<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Resolves the active UI language for the request.
 *
 * Priority:
 *   1. `?lang=en|bn` query param (persisted to the session so it survives
 *      navigation between pages).
 *   2. Installed session value (set from a previous `?lang=` visit).
 *   3. The authenticated institute user's institute setting.
 *   4. Default `en`.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $lang = $request->query('lang');

        if (is_string($lang) && in_array($lang, ['en', 'bn'], true)) {
            Session::put('mawa_lang', $lang);

            // Persist the choice on the authenticated user so it survives re-login.
            $user = Auth::guard('institute_user')->check() ? Auth::guard('institute_user')->user() : Auth::user();
            if ($user !== null && $user->preferred_language !== $lang) {
                $user->forceFill(['preferred_language' => $lang])->save();
            }
        } elseif ($request->expectsJson()) {
            $header = strtolower((string) $request->header('Accept-Language'));
            if (str_starts_with($header, 'bn')) {
                $current = 'bn';
                app()->setLocale($current);
                view()->share('currentLang', $current);
                return $next($request);
            }
        }

        $current = mawa_current_lang();

        app()->setLocale($current);

        view()->share('currentLang', $current);

        return $next($request);
    }
}
