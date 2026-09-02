<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\InstituteUser;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Services\Identity\EmailOtpService;
use App\Services\Identity\PhoneOtpService;
use App\Services\Identity\TwoFactorMethodService;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticationProvider;

class TwoFactorChallengeController extends Controller
{
    public function __construct(
        protected TwoFactorMethodService $methodService,
        protected PhoneOtpService $phoneOtp,
        protected EmailOtpService $emailOtp,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('login.id')) {
            return redirect()->route('institute.login');
        }

        $guardName = $request->session()->get('login.guard', 'web');
        $guardName = in_array($guardName, ['web', 'platform_admin', 'institute_user', 'guardian'], true) ? $guardName : 'web';

        $id = $request->session()->get('login.id');
        $user = $this->resolveUser($guardName, $id);

        if (! $user) {
            return redirect()->route(match ($guardName) {
                'web' => 'login',
                'platform_admin' => 'admin.login',
                'guardian' => 'guardian.login',
                default => 'institute.login',
            });
        }

        // Handle method switch via query param
        if ($request->has('method')) {
            $requested = $request->query('method');
            if (in_array($requested, ['totp', 'sms', 'email'], true) && $this->methodService->isMethodAvailable($user, $requested)) {
                $request->session()->put('login.2fa_method', $requested);
            }
        }

        $available = $this->methodService->availableMethods($user);
        if (empty($available)) {
            // No 2FA actually enabled - treat as normal login failure, clear session and redirect
            $request->session()->forget(['login.id','login.guard','login.remember','login.2fa_method','login.2fa_available']);
            return redirect()->route(match ($guardName) {
                'web' => 'login',
                'platform_admin' => 'admin.login',
                'guardian' => 'guardian.login',
                default => 'institute.login',
            });
        }

        $current = $request->session()->get('login.2fa_method');
        if (! $current || ! in_array($current, $available, true)) {
            $current = $this->methodService->preferredMethod($user) ?? $available[0];
            $request->session()->put('login.2fa_method', $current);
        }
        $request->session()->put('login.2fa_available', $available);

        // Auto-send OTP for sms/email if not throttled and not already sent recently
        // DO NOT send email when TOTP is selected (critical requirement)
        if ($current === 'sms') {
            try {
                // Attempt to send but respect 60s throttle - catch ValidationException silently for UI
                $this->phoneOtp->sendFor2FA($user);
            } catch (\Illuminate\Validation\ValidationException $e) {
                // Throttled - show message via session but do not fail page load
                if (str_contains($e->getMessage(), 'Please wait')) {
                    // ignore, UI will show resend throttle
                }
            } catch (\Throwable $e) {
                report($e);
            }
        } elseif ($current === 'email') {
            try {
                $this->emailOtp->sendForLogin($user, $guardName);
            } catch (\Illuminate\Validation\ValidationException $e) {
                // throttled - ignore
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $maskedPhone = $user->phone ? $this->methodService->maskPhone($user->phone) : null;
        $maskedEmail = $user->email ? $this->methodService->maskEmail($user->email) : null;

        return view('auth.two-factor-challenge', [
            'currentMethod' => $current,
            'availableMethods' => $available,
            'alternateMethods' => $this->methodService->alternateMethods($user, $current),
            'maskedPhone' => $maskedPhone,
            'maskedEmail' => $maskedEmail,
            'guardName' => $guardName,
            'user' => $user,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required_without:recovery_code', 'nullable', 'string', 'size:6'],
            'recovery_code' => ['required_without:code', 'nullable', 'string'],
        ], [
            'code.size' => 'Please enter the 6-digit code.',
            'code.required_without' => 'Please enter the 6-digit code.',
        ]);

        $guardName = $request->session()->get('login.guard', 'web');
        $guardName = in_array($guardName, ['web', 'platform_admin', 'institute_user', 'guardian'], true) ? $guardName : 'web';

        $id = $request->session()->get('login.id');
        $user = $this->resolveUser($guardName, $id);

        if (! $user) {
            return redirect()->route(match ($guardName) {
                'web' => 'login',
                'platform_admin' => 'admin.login',
                'guardian' => 'guardian.login',
                default => 'institute.login',
            });
        }

        $current = $request->session()->get('login.2fa_method', $this->methodService->preferredMethod($user) ?? 'totp');
        $available = $this->methodService->availableMethods($user);
        if (! in_array($current, $available, true)) {
            $current = $available[0] ?? 'totp';
        }

        // Separate rate limiting per method — platform DB → default
        $perUserKey = $current . ':user:'.$guardName.':'.$user->getKey();
        $ipKey = $current . ':ip:'.$request->ip();
        $maxUser = \App\Services\Identity\TwoFactorMethodService::maxFailedAttempts();
        $maxIp = 10;
        if (RateLimiter::tooManyAttempts($perUserKey, $maxUser) || RateLimiter::tooManyAttempts($ipKey, $maxIp)) {
            try { \App\Services\Identity\IdentityAuditService::log($user->getKey(), $current.'_throttled', '2fa', ['guard'=>$guardName]); } catch (\Throwable $e) {}
            return back()->withErrors(['code' => 'Too many attempts. Try again later.'])->withInput();
        }

        $valid = false;
        try {
            if ($current === 'totp') {
                $valid = $this->hasValidCode($user, $request);
            } elseif ($current === 'sms') {
                $code = (string) $request->input('code');
                $valid = $this->phoneOtp->verifyFor2FA($user, $code);
            } elseif ($current === 'email') {
                $code = (string) $request->input('code');
                $email = $user->email;
                $valid = $this->emailOtp->verify($user, $email, $code, $guardName);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            RateLimiter::hit($perUserKey, 60);
            RateLimiter::hit($ipKey, 60);
            $raw = $e->errors()['otp'][0] ?? $e->errors()['code'][0] ?? $e->errors()['phone'][0] ?? $e->errors()['email'][0] ?? 'Invalid code.';
            $msg = $this->friendlyMessage($raw);
            try { \App\Services\Identity\IdentityAuditService::log($user->getKey(), $current.'_failed', '2fa', ['guard'=>$guardName]); } catch (\Throwable $e2) {}
            return back()->withErrors(['code' => $msg])->withInput();
        } catch (\Throwable $e) {
            report($e);
            RateLimiter::hit($perUserKey, 60);
            RateLimiter::hit($ipKey, 60);
            return back()->withErrors(['code' => 'We couldn\'t send the verification code right now. Please try again later.'])->withInput();
        }

        if (! $valid) {
            RateLimiter::hit($perUserKey, 60);
            RateLimiter::hit($ipKey, 60);
            try { \App\Services\Identity\IdentityAuditService::log($user->getKey(), $current.'_failed', '2fa', ['guard'=>$guardName]); } catch (\Throwable $e) {}
            return back()->withErrors(['code' => 'The verification code is incorrect.'])->withInput();
        }

        RateLimiter::clear($perUserKey);
        RateLimiter::clear($ipKey);

        if (! $user->hasVerifiedEmail()) {
            $request->session()->forget(['login.id','login.guard','login.remember','login.2fa_method','login.2fa_available']);
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('verification.notice')
                ->with('status', 'Please verify your email address before continuing.');
        }

        $remember = (bool) $request->session()->pull('login.remember', false);

        foreach (['web','platform_admin','institute_user','guardian'] as $g) {
            if ($g !== $guardName && Auth::guard($g)->check()) {
                Auth::guard($g)->logout();
            }
        }

        Auth::guard($guardName)->login($user, $remember);
        Auth::shouldUse($guardName);

        $request->session()->forget('login.id');
        $request->session()->forget('login.guard');
        $request->session()->forget('login.2fa_method');
        $request->session()->forget('login.2fa_available');
        $request->session()->regenerate();

        $payload = [
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'failed_login_count' => 0,
            'locked_until' => null,
            'inactivity_warning_sent_at' => null,
        ];
        // platform_admins / institute_users / guardians lack inactivity_final_warning_sent_at (users only)
        if (\Illuminate\Support\Facades\Schema::hasColumn($user->getTable(), 'inactivity_final_warning_sent_at')) {
            $payload['inactivity_final_warning_sent_at'] = null;
        }
        $user->forceFill($payload)->save();

        try { \App\Services\Identity\IdentityAuditService::log($user->getKey(), $current.'_success', '2fa', ['guard'=>$guardName]); } catch (\Throwable $e) {}

        if ($guardName === 'institute_user') {
            TenantContext::set($user->institute_id);
        } elseif ($guardName === 'web') {
            Workspace::set(Workspace::resolveAfterLogin($user));
        } elseif ($guardName === 'guardian') {
            TenantContext::set($user->institute_id);
        }

        return redirect()->intended('/');
    }

    public function switchMethod(Request $request): RedirectResponse
    {
        $request->validate(['method' => ['required','string','in:totp,sms,email']]);
        $guardName = $request->session()->get('login.guard', 'web');
        $id = $request->session()->get('login.id');
        $user = $this->resolveUser($guardName, $id);
        if (! $user) {
            return redirect()->route('two-factor.login');
        }
        $method = $request->input('method');
        if (! $this->methodService->isMethodAvailable($user, $method)) {
            return back()->withErrors(['method' => 'Method not available.']);
        }
        $request->session()->put('login.2fa_method', $method);
        return redirect()->route('two-factor.login');
    }

    public function resend(Request $request): RedirectResponse
    {
        $guardName = $request->session()->get('login.guard', 'web');
        $id = $request->session()->get('login.id');
        $user = $this->resolveUser($guardName, $id);
        if (! $user) {
            return redirect()->route('two-factor.login');
        }
        $current = $request->session()->get('login.2fa_method', $this->methodService->preferredMethod($user));
        try {
            if ($current === 'sms') {
                $this->phoneOtp->sendFor2FA($user);
                return back()->with('status', 'Verification code sent to '.$this->methodService->maskPhone($user->phone));
            } elseif ($current === 'email') {
                $this->emailOtp->sendForLogin($user, $guardName);
                return back()->with('status', 'Verification code sent to '.$this->methodService->maskEmail($user->email));
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            $msg = $e->errors()['phone'][0] ?? $e->errors()['email'][0] ?? 'Please wait before requesting another code.';
            return back()->withErrors(['code' => $msg]);
        }
        return back()->withErrors(['code' => 'Resend not available for this method.']);
    }

    protected function resolveUser(string $guardName, mixed $id): mixed
    {
        return match ($guardName) {
            'web' => User::query()->find($id),
            'platform_admin' => PlatformAdmin::query()->find($id),
            'guardian' => Guardian::query()->withoutGlobalScopes()->find($id),
            default => InstituteUser::query()->withoutGlobalScopes()->find($id),
        };
    }

    protected function friendlyMessage(string $raw): string
    {
        $lower = strtolower($raw);
        if (str_contains($lower, 'expired')) {
            return 'This verification code has expired. Please request a new code.';
        }
        if (str_contains($lower, 'too many attempts')) {
            return 'Too many attempts. Please request a new code or try again later.';
        }
        if (str_contains($lower, 'please wait')) {
            return 'Please wait before requesting another code.';
        }
        if (str_contains($lower, 'invalid') || str_contains($lower, 'incorrect')) {
            return 'The verification code is incorrect.';
        }
        if (str_contains($lower, 'invalid or expired')) {
            return 'The verification code is incorrect.';
        }
        // Generic fallback without exposing internal details
        return 'The verification code is incorrect.';
    }

    protected function hasValidCode(mixed $user, Request $request): bool
    {
        if ($request->filled('code')) {
            if (empty($user->two_factor_secret)) return false;
            $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);
            return app(TwoFactorAuthenticationProvider::class)->verify($secret, $request->input('code'));
        }

        $recovery = (string) $request->input('recovery_code');
        if ($recovery === '' || ! $user->two_factor_recovery_codes) {
            return false;
        }

        foreach ($user->recoveryCodes() as $candidate) {
            if (hash_equals($candidate, $recovery)) {
                $user->replaceRecoveryCode($candidate);
                try { \App\Services\Identity\IdentityAuditService::log($user->getKey(), 'recovery_code_used', '2fa', []); } catch (\Throwable $e) {}
                return true;
            }
        }

        return false;
    }
}
