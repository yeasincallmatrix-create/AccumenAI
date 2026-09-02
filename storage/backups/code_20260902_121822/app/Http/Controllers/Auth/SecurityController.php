<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Support\PasswordHash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Fortify;

/**
 * Security page + two-factor + session management.
 *
 * Works for both guards: the route middleware pins the guard, so
 * $request->user() always resolves to the authenticated portal user.
 */
class SecurityController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $guard = $this->guardName($user);

        $sessions = DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->orderByDesc('last_activity')
            ->get();

        return view('security.index', [
            'securityUser' => $user,
            'securityGuard' => $guard,
            'sessions' => $sessions,
            'currentSessionId' => $request->session()->getId(),
        ]);
    }

    public function enable(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->confirmCurrentPassword($request, $user);

        app(EnableTwoFactorAuthentication::class)($user, $request->boolean('force'));
        try { \App\Services\Identity\IdentityAuditService::log($user->getKey(), '2fa_enabled', '2fa', ['guard'=>$this->guardName($user)]); } catch (\Throwable $e) {}

        return back()->with('status', mawa_lang('security.two_factor_enabled'));
    }

    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->confirmCurrentPassword($request, $user);

        app(ConfirmTwoFactorAuthentication::class)($user, (string) $request->input('code'));
        try { \App\Services\Identity\IdentityAuditService::log($user->getKey(), '2fa_confirmed', '2fa', ['guard'=>$this->guardName($user)]); } catch (\Throwable $e) {}

        return back()->with('status', mawa_lang('security.two_factor_confirmed'));
    }

    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->confirmCurrentPassword($request, $user);

        app(DisableTwoFactorAuthentication::class)($user);
        try { \App\Services\Identity\IdentityAuditService::log($user->getKey(), '2fa_disabled', '2fa', ['guard'=>$this->guardName($user)]); } catch (\Throwable $e) {}

        return back()->with('status', mawa_lang('security.two_factor_disabled'));
    }

    public function qrCode(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->two_factor_secret) {
            abort(404);
        }

        // Secret only during enrollment – never log, audit generically
        try { \App\Services\Identity\IdentityAuditService::log($user->getKey(), '2fa_qr_viewed', '2fa', ['guard'=>$this->guardName($user)]); } catch (\Throwable $e) {}
        return response()->json([
            'svg' => $user->twoFactorQrCodeSvg(),
            'setup_key' => Fortify::currentEncrypter()->decrypt($user->two_factor_secret),
            'recovery_codes' => $user->two_factor_recovery_codes ? $user->recoveryCodes() : [],
        ]);
    }

    public function recoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'codes' => $user->two_factor_recovery_codes ? $user->recoveryCodes() : [],
        ]);
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        app(GenerateNewRecoveryCodes::class)($request->user());

        return back()->with('status', mawa_lang('security.recovery_codes_regenerated'));
    }

    // E18: SMS 2FA
    public function enableSms(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->confirmCurrentPassword($request, $user);
        if (empty($user->phone) || empty($user->phone_verified_at)) {
            return back()->withErrors(['phone' => 'Mobile number not verified. Please verify your mobile number first.']);
        }
        $user->forceFill(['sms_2fa_enabled' => true])->save();
        if (empty($user->preferred_2fa_method)) {
            $user->forceFill(['preferred_2fa_method' => 'sms'])->save();
        }
        try { \App\Services\Identity\IdentityAuditService::log($user->getKey(), '2fa_sms_enabled', '2fa', ['guard'=>$this->guardName($user)]); } catch (\Throwable $e) {}
        return back()->with('status', 'SMS two-step verification enabled.');
    }

    public function disableSms(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->confirmCurrentPassword($request, $user);
        $user->forceFill(['sms_2fa_enabled' => false])->save();
        if (($user->preferred_2fa_method ?? null) === 'sms') {
            // Fallback to remaining method or null
            $svc = app(\App\Services\Identity\TwoFactorMethodService::class);
            $remaining = $svc->availableMethods($user);
            $user->forceFill(['preferred_2fa_method' => $remaining[0] ?? null])->save();
        }
        try { \App\Services\Identity\IdentityAuditService::log($user->getKey(), '2fa_sms_disabled', '2fa', ['guard'=>$this->guardName($user)]); } catch (\Throwable $e) {}
        return back()->with('status', 'SMS two-step verification disabled.');
    }

    public function enableEmail(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->confirmCurrentPassword($request, $user);
        if (empty($user->email) || empty($user->email_verified_at)) {
            return back()->withErrors(['email' => 'Email not verified. Please verify your email first.']);
        }
        $user->forceFill(['email_2fa_enabled' => true])->save();
        if (empty($user->preferred_2fa_method)) {
            $user->forceFill(['preferred_2fa_method' => 'email'])->save();
        }
        try { \App\Services\Identity\IdentityAuditService::log($user->getKey(), '2fa_email_enabled', '2fa', ['guard'=>$this->guardName($user)]); } catch (\Throwable $e) {}
        return back()->with('status', 'Email two-step verification enabled.');
    }

    public function disableEmail(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->confirmCurrentPassword($request, $user);
        $user->forceFill(['email_2fa_enabled' => false])->save();
        if (($user->preferred_2fa_method ?? null) === 'email') {
            $svc = app(\App\Services\Identity\TwoFactorMethodService::class);
            $remaining = $svc->availableMethods($user);
            $user->forceFill(['preferred_2fa_method' => $remaining[0] ?? null])->save();
        }
        try { \App\Services\Identity\IdentityAuditService::log($user->getKey(), '2fa_email_disabled', '2fa', ['guard'=>$this->guardName($user)]); } catch (\Throwable $e) {}
        return back()->with('status', 'Email two-step verification disabled.');
    }

    public function updatePreferred(Request $request): RedirectResponse
    {
        $request->validate(['method' => ['required','string','in:totp,sms,email']]);
        $user = $request->user();
        $this->confirmCurrentPassword($request, $user);
        $method = $request->input('method');
        $svc = app(\App\Services\Identity\TwoFactorMethodService::class);
        if (! $svc->isMethodAvailable($user, $method)) {
            return back()->withErrors(['method' => 'Selected method not available.']);
        }
        $user->forceFill(['preferred_2fa_method' => $method])->save();
        try { \App\Services\Identity\IdentityAuditService::log($user->getKey(), '2fa_method_changed', '2fa', ['guard'=>$this->guardName($user), 'method'=>$method]); } catch (\Throwable $e) {}
        return back()->with('status', 'Preferred two-step method updated.');
    }

    public function revokeSessions(Request $request): RedirectResponse
    {
        $user = $request->user();
        $guard = $this->guardName($user);

        $request->validate(['password' => ['required', 'string']]);

        try {
            Auth::guard($guard)->logoutOtherDevices($request->input('password'));
        } catch (InvalidArgumentException) {
            return back()->withErrors(['password' => mawa_lang('security.wrong_password')]);
        }

        DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return back()->with('status', mawa_lang('security.other_sessions_logged_out'));
    }

    public function flushAllSessions(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        if (! PasswordHash::safeCheck($request->input('password'), $request->user()->getAuthPassword())) {
            return back()->withErrors(['password' => mawa_lang('security.wrong_password')]);
        }

        $currentSessionId = $request->session()->getId();

        DB::table('sessions')->where('id', '!=', $currentSessionId)->delete();

        return back()->with('status', 'All other sessions have been terminated.');
    }

    protected function confirmCurrentPassword(Request $request, mixed $user): void
    {
        $request->validate(['password' => ['required', 'string']]);

        if (! PasswordHash::safeCheck($request->input('password'), $user->getAuthPassword())) {
            throw ValidationException::withMessages(['password' => mawa_lang('security.wrong_password')]);
        }
    }

    protected function guardName(mixed $user): string
    {
        if ($user instanceof PlatformAdmin) return 'platform_admin';
        if ($user instanceof Guardian) return 'guardian';
        if ($user instanceof User) return 'web';
        return 'institute_user';
    }
}
