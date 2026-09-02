<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\EmailNormalizer;
use App\Support\PasswordHash;
use App\Support\PhoneNormalizer;
use App\Support\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Unified global-account login.
 *
 * Authenticates against the global `users` table via the `web` guard and
 * resolves the active organization through the membership model afterwards.
 * The old per-institute login (institute/login) remains available and
 * unchanged for backward compatibility.
 */
class UserLoginController extends Controller
{
    protected $redirectTo = '/';

    protected string $guardName = 'web';

    protected int $lockoutThreshold = 10;

    protected int $lockoutMinutes = 15;

    public function showLoginForm()
    {
        if (Auth::guard($this->guardName)->check()) {
            return redirect($this->redirectTo);
        }

        return view('auth.login', [
            'action' => route('login.submit'),
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

        $identifier = trim((string) ($request->input('login') ?? $request->input('email') ?? $request->input('identifier') ?? ''));
        $isEmail = str_contains($identifier, '@');
        $normalizedEmail = null;
        $normalizedPhone = null;
        if ($isEmail) {
            $normalizedEmail = EmailNormalizer::normalize($identifier);
        } else {
            $normalizedPhone = PhoneNormalizer::toE164($identifier, 'Bangladesh');
            // Fallback: if phone normalization fails, treat as email attempt to keep generic error
            if ($normalizedPhone === null) {
                $normalizedEmail = EmailNormalizer::normalize($identifier);
                $isEmail = $normalizedEmail !== null && filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) !== false;
            }
        }

        /** @var User|null $user */
        $user = null;
        if ($isEmail && $normalizedEmail) {
            $user = User::query()->where('email', $normalizedEmail)->first();
        } elseif (!$isEmail && $normalizedPhone) {
            $user = User::query()->where('phone', $normalizedPhone)->first();
        } else {
            // Fallback to raw email lookup for backward compatibility
            $user = User::query()->where('email', $identifier)->first();
        }

        if ($user !== null && $user->status === 'active') {
            if (! PasswordHash::looksValid((string) $user->getAuthPassword())) {
                $this->recordFailedAttempt($user);
                report(sprintf(
                    'login blocked: corrupted password_hash for %s #%s (%s)',
                    'user',
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

        // Two-factor challenge: verify password but require 2FA step if any method enabled (TOTP/SMS/Email).
        // Distinct from phone verification: this is login protection, not onboarding.
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
            $preferred = $twoFactorService->preferredMethod($user);
            $request->session()->put('login.2fa_method', $preferred);
            $request->session()->put('login.2fa_available', $twoFactorService->availableMethods($user));

            return redirect()->route('two-factor.login');
        }

        // Attempt via email or phone credentials
        $attempted = false;
        if ($isEmail && $normalizedEmail) {
            $attempted = Auth::guard($this->guardName)->attempt(['email' => $normalizedEmail, 'password' => $request->password, 'status' => 'active'], $request->boolean('remember'));
        } elseif (!$isEmail && $normalizedPhone) {
            $attempted = Auth::guard($this->guardName)->attempt(['phone' => $normalizedPhone, 'password' => $request->password, 'status' => 'active'], $request->boolean('remember'));
        } else {
            $attempted = Auth::guard($this->guardName)->attempt(['email' => $identifier, 'password' => $request->password, 'status' => 'active'], $request->boolean('remember'));
        }

        if ($attempted) {
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

            if (Auth::guard('platform_admin')->check()) {
                Auth::guard('platform_admin')->logout();
            }
            if (Auth::guard('institute_user')->check()) {
                Auth::guard('institute_user')->logout();
            }

            // Transparent algorithm/cost migration — rehash if needed
            try {
                app(\App\Services\Auth\PasswordService::class)->rehashIfNeeded(Auth::guard($this->guardName)->user(), (string) $request->password);
            } catch (\Throwable $e) {
                report($e);
            }

            $authed = Auth::guard($this->guardName)->user();
            if ($authed) {
                $authed->forceFill([
                    'last_login_at' => now(),
                    'last_login_ip' => $request->ip(),
                    'failed_login_count' => 0,
                    'locked_until' => null,
                    'inactivity_warning_sent_at' => null,
                    'inactivity_final_warning_sent_at' => null,
                ])->save();
                $user = $authed;
            } else {
                $user->forceFill([
                    'last_login_at' => now(),
                    'last_login_ip' => $request->ip(),
                    'failed_login_count' => 0,
                    'locked_until' => null,
                    'inactivity_warning_sent_at' => null,
                    'inactivity_final_warning_sent_at' => null,
                ])->save();
            }

            // Resolve the active organization through the membership model.
            $workspaceId = Workspace::resolveAfterLogin($user, $request->integer('institution_id') ?: null);
            Workspace::set($workspaceId);

            if ($workspaceId === null) {
                return redirect()->route('workspace.picker');
            }

            return redirect()->intended($this->redirectTo);
        }

        $this->recordFailedAttempt($user);

        throw ValidationException::withMessages([
            'email' => [trans('auth.failed')],
        ]);
    }

    protected function recordFailedAttempt(?User $user): void
    {
        if ($user === null || $user->status !== 'active') {
            return;
        }

        $user->increment('failed_login_count');

        if ($user->failed_login_count >= $this->lockoutThreshold) {
            $user->forceFill([
                'locked_until' => now()->addMinutes($this->lockoutMinutes),
                'failed_login_count' => 0,
            ])->save();
        }
    }

    protected function validateLogin(Request $request): void
    {
        $request->validate([
            'login' => ['sometimes', 'string', 'max:150'],
            'email' => ['sometimes', 'string', 'max:150'],
            'identifier' => ['sometimes', 'string', 'max:150'],
            'password' => ['required', 'string'],
        ]);

        $identifier = trim((string) ($request->input('login') ?? $request->input('email') ?? $request->input('identifier') ?? ''));
        if ($identifier === '') {
            throw ValidationException::withMessages(['email' => [trans('auth.failed')], 'login' => [trans('auth.failed')]]);
        }
    }

    protected function throttleKey(Request $request): string
    {
        return 'login:'.$this->guardName.':'.$request->ip();
    }
}
