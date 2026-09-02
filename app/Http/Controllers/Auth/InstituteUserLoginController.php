<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\InstituteUser;
use App\Support\EmailNormalizer;
use App\Support\PasswordHash;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class InstituteUserLoginController extends Controller
{
    protected $redirectTo = '/';

    protected string $guardName = 'institute_user';

    /**
     * Failed attempts before the account itself is locked.
     */
    protected int $lockoutThreshold = 10;

    protected int $lockoutMinutes = 15;

    public function showLoginForm()
    {
        if (Auth::guard($this->guardName)->check()) {
            return redirect($this->redirectTo);
        }

        return view('auth.login', [
            'action' => route('institute.login.submit'),
            'title' => mawa_lang('auth.institute_login_title'),
            'hint' => mawa_lang('auth.institute_login_hint'),
        ]);
    }

    public function login(Request $request)
    {
        $this->validateLogin($request);

        if (Auth::guard($this->guardName)->check()) {
            return redirect($this->redirectTo);
        }

        $normalizedEmail = EmailNormalizer::normalize((string) $request->email);

        /** @var InstituteUser|null $user */
        $user = InstituteUser::withoutGlobalScopes()
            ->where('email', $normalizedEmail ?? $request->email)
            ->first();

        if ($user !== null && $user->status === 'active') {
            if (! PasswordHash::looksValid((string) $user->getAuthPassword())) {
                $this->recordFailedAttempt($user);
                report(sprintf(
                    'login blocked: corrupted password_hash for %s #%s (%s)',
                    'institute_user',
                    $user->getKey(),
                    $user->email,
                ));

                throw ValidationException::withMessages([
                    'email' => [trans('auth.failed')],
                ]);
            }

            if ($user->isLocked()) {
                throw ValidationException::withMessages([
                    'email' => [trans('auth.throttle', ['seconds' => $user->locked_until->diffInSeconds(now()) ?: 30])],
                ]);
            }
        }

        // Two-factor challenge: support TOTP + SMS + Email
        $twoFactorService = app(\App\Services\Identity\TwoFactorMethodService::class);
        $hasAny2FA = $user !== null && $user->status === 'active' && $twoFactorService->is2FAEnabled($user);
        if ($hasAny2FA) {
            if (! PasswordHash::safeCheck($request->password, $user->getAuthPassword())) {
                $this->recordFailedAttempt($user);

                throw ValidationException::withMessages([
                    'email' => [trans('auth.failed')],
                ]);
            }

            $request->session()->put('login.id', $user->getKey());
            $request->session()->put('login.remember', $request->boolean('remember'));
            $request->session()->put('login.guard', $this->guardName);
            $request->session()->put('login.2fa_method', $twoFactorService->preferredMethod($user));
            $request->session()->put('login.2fa_available', $twoFactorService->availableMethods($user));

            return redirect()->route('two-factor.login');
        }

        $credentials = array_merge(
            ['email' => $normalizedEmail ?? $request->email, 'password' => $request->password],
            ['status' => 'active']
        );

        if (Auth::guard($this->guardName)->attempt($credentials, $request->boolean('remember'))) {
            // Pin the default driver so the persisted DB session records the
            // correct user_id (used by the session-management page).
            Auth::shouldUse($this->guardName);

            $authedUser = Auth::guard($this->guardName)->user();
            if ($authedUser && ! $authedUser->hasVerifiedEmail()) {
                Auth::guard($this->guardName)->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('verification.notice')
                    ->with('status', 'Please verify your email address before continuing.');
            }

            $request->session()->regenerate();
            RateLimiter::clear($this->throttleKey($request));

            // Only one role per session — log out the platform admin if present.
            if (Auth::guard('platform_admin')->check()) {
                Auth::guard('platform_admin')->logout();
            }

            try {
                app(\App\Services\Auth\PasswordService::class)->rehashIfNeeded(Auth::guard($this->guardName)->user(), (string) $request->password);
            } catch (\Throwable $e) {
                report($e);
            }

            $user->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
                'failed_login_count' => 0,
                'locked_until' => null,
            ])->save();

            TenantContext::set($user->institute_id);

            return redirect()->intended($this->redirectTo);
        }

        $this->recordFailedAttempt($user);

        throw ValidationException::withMessages([
            'email' => [trans('auth.failed')],
        ]);
    }

    protected function recordFailedAttempt(?InstituteUser $user): void
    {
        if ($user === null || $user->status !== 'active') {
            return;
        }

        $user->increment('failed_login_count');

        $threshold = \App\Services\Platform\PlatformSettingsService::effectiveLoginThreshold($this->guardName);
        $minutes = \App\Services\Platform\PlatformSettingsService::effectiveLockoutMinutes($this->guardName);
        if ($user->failed_login_count >= $threshold) {
            $user->forceFill([
                'locked_until' => now()->addMinutes($minutes),
                'failed_login_count' => 0,
            ])->save();
        }
    }

    protected function validateLogin(Request $request): void
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);
    }

    protected function throttleKey(Request $request): string
    {
        return 'login:'.$this->guardName.':'.$request->ip();
    }
}
